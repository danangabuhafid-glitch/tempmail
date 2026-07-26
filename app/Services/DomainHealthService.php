<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Setting;
use App\Models\WebhookLog;
use Closure;
use Illuminate\Support\Str;

/**
 * Kesehatan per-domain penerima email.
 *
 * Dengan 2+ domain, satu domain bisa rusak sendirian (NS pindah, domain expired,
 * catch-all Cloudflare dimatikan) sementara domain lain tetap jalan — tanpa
 * monitoring ini kerusakannya tidak kelihatan. Scheduler memanggil
 * runScheduledCheck() tiap jam: cek DNS tiap domain, simpan statusnya, dan
 * kirim notifikasi saat status BERUBAH (rusak ↔ pulih) — tidak spam tiap jam.
 */
class DomainHealthService
{
    // Bisa diganti di test — bentuk: fn(string $domain) => ['ns' => [...], 'mx' => [...]]
    private Closure $resolver;

    public function __construct(private PushNotifier $notifier)
    {
        $this->resolver = function (string $domain): array {
            return [
                'ns' => array_map(fn ($r) => Str::lower($r['target'] ?? ''), dns_get_record($domain, DNS_NS) ?: []),
                'mx' => array_map(fn ($r) => Str::lower($r['target'] ?? ''), dns_get_record($domain, DNS_MX) ?: []),
            ];
        };
    }

    public function setResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    // Periksa NS & MX satu domain: sudah mengarah ke Cloudflare / Email Routing belum
    public function checkDomain(string $domain): array
    {
        try {
            $rec = ($this->resolver)($domain);
        } catch (\Throwable $e) {
            return ['ok' => false, 'ns_ok' => false, 'mx_ok' => false, 'ns' => [], 'mx' => [], 'error' => 'Gagal query DNS: ' . $e->getMessage()];
        }

        $nsOk = collect($rec['ns'])->contains(fn ($h) => str_ends_with($h, 'ns.cloudflare.com'));
        $mxOk = collect($rec['mx'])->contains(fn ($h) => str_contains($h, 'mx.cloudflare.net'));

        return [
            'ok' => $nsOk && $mxOk,
            'ns_ok' => $nsOk,
            'mx_ok' => $mxOk,
            'ns' => $rec['ns'],
            'mx' => $rec['mx'],
            'error' => null,
        ];
    }

    // Status tersimpan dari pemeriksaan terjadwal terakhir (domain => status)
    public function storedHealth(): array
    {
        return json_decode((string) Setting::getValue('domain_health', '{}'), true) ?: [];
    }

    /**
     * Statistik per-domain untuk dashboard: email terakhir, jumlah 24 jam,
     * error webhook 24 jam, plus status DNS tersimpan.
     */
    public function statsPerDomain(): array
    {
        $stored = $this->storedHealth();
        $sejak = now()->subDay();

        $stats = [];
        foreach (config('tempmail.domains') as $domain) {
            $stats[$domain] = [
                'last_email_at' => Email::where('domain', $domain)->max('received_at'),
                'emails_24h' => Email::where('domain', $domain)->where('received_at', '>', $sejak)->count(),
                'errors_24h' => WebhookLog::where('created_at', '>', $sejak)
                    ->where('status', '>=', 400)
                    ->where('envelope_to', 'like', '%@' . $domain)
                    ->count(),
                'dns' => $stored[$domain] ?? null, // null = belum pernah dicek terjadwal
            ];
        }

        return $stats;
    }

    // Dipanggil scheduler tiap jam — cek semua domain + notifikasi saat status berubah
    public function runScheduledCheck(): void
    {
        $stored = $this->storedHealth();
        $hasil = [];

        foreach (config('tempmail.domains') as $domain) {
            $cek = $this->checkDomain($domain);

            // Lookup gagal total (DNS resolver VPS bermasalah) ≠ domain rusak:
            // pertahankan status lama supaya tidak salah alarm
            if ($cek['error'] !== null) {
                $hasil[$domain] = array_merge(
                    $stored[$domain] ?? ['ok' => true, 'ns_ok' => true, 'mx_ok' => true],
                    ['checked_at' => now()->toIso8601String(), 'lookup_error' => $cek['error']]
                );
                continue;
            }

            $sebelumnyaOk = $stored[$domain]['ok'] ?? null;

            if ($sebelumnyaOk !== false && $cek['ok'] === false) {
                $detail = [];
                if (!$cek['ns_ok']) $detail[] = 'NS tidak lagi mengarah ke Cloudflare';
                if (!$cek['mx_ok']) $detail[] = 'MX Email Routing hilang';
                $this->notifier->peringatan(
                    "Domain {$domain} bermasalah",
                    implode('; ', $detail) . ". Email ke @{$domain} kemungkinan TIDAK masuk. Cek registrar/Cloudflare, lalu halaman Setup."
                );
            } elseif ($sebelumnyaOk === false && $cek['ok'] === true) {
                $this->notifier->peringatan(
                    "Domain {$domain} pulih",
                    "NS & MX kembali mengarah ke Cloudflare — email @{$domain} mengalir lagi."
                );
            }

            $hasil[$domain] = [
                'ok' => $cek['ok'],
                'ns_ok' => $cek['ns_ok'],
                'mx_ok' => $cek['mx_ok'],
                'checked_at' => now()->toIso8601String(),
                'lookup_error' => null,
            ];
        }

        Setting::setValue('domain_health', json_encode($hasil));
    }
}
