<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\Setting;
use App\Models\WebhookLog;
use App\Services\DomainHealthService;
use App\Services\EmailIngestService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Wizard setup via web: status DNS per domain, kode worker siap salin,
 * URL webhook + token, dan uji terima email — pengganti baca SETUP.md manual.
 */
class SetupController extends Controller
{
    public function index(Request $request, DomainHealthService $domainHealth)
    {
        $domains = config('tempmail.domains');
        $webhookUrl = url('/inbound/email');
        $token = (string) config('tempmail.webhook_token');

        $workerScript = @file_get_contents(base_path('cloudflare/email-worker.js')) ?: '// file cloudflare/email-worker.js tidak ditemukan';

        // Cek DNS hanya saat diminta (tombol "Periksa DNS") supaya halaman tetap cepat
        $dnsChecks = null;
        if ($request->boolean('check')) {
            $dnsChecks = [];
            foreach ($domains as $domain) {
                $dnsChecks[$domain] = $domainHealth->checkDomain($domain);
            }

            // Dipanggil via fetch → cukup kirim partial hasil pemeriksaan
            if ($request->ajax()) {
                return view('setup._dns', compact('dnsChecks'))->render();
            }
        }

        $lastEmail = Email::orderByDesc('received_at')->first();

        // ===== Dashboard kesehatan: semua sinyal pipeline dalam satu layar =====
        $heartbeat = Setting::getValue('scheduler_last_run');
        $backupLastRun = Setting::getValue('backup_last_run');

        $health = [
            'total_emails' => Email::count(),
            'total_size_mb' => round((int) Email::sum('size_bytes') / 1048576, 1),
            'max_storage_mb' => (int) config('tempmail.max_storage_mb', 0),
            'heartbeat' => $heartbeat ? Carbon::parse($heartbeat) : null,
            // Cron dianggap mati bila heartbeat lebih tua dari 25 jam (task-nya per jam,
            // toleran untuk cron yang hanya jalan harian)
            'heartbeat_ok' => $heartbeat ? Carbon::parse($heartbeat)->gt(now()->subHours(25)) : false,
            'webhook_errors_24h' => WebhookLog::where('created_at', '>', now()->subDay())->where('status', '>=', 400)->count(),
            'webhook_ok_24h' => WebhookLog::where('created_at', '>', now()->subDay())->whereIn('status', [200, 201])->count(),
            'backup' => $backupLastRun ? Carbon::parse($backupLastRun) : null,
            'recent_logs' => WebhookLog::orderByDesc('id')->limit(15)->get(),
            // Rincian per-domain: DNS tersimpan + email terakhir + error 24 jam
            'per_domain' => $domainHealth->statsPerDomain(),
        ];

        return view('setup.index', compact('domains', 'webhookUrl', 'token', 'workerScript', 'dnsChecks', 'lastEmail', 'health'));
    }

    // Suntik email contoh lewat jalur ingest yang sama dengan webhook —
    // membuktikan parser + database bekerja tanpa menunggu Cloudflare tersambung
    public function testEmail(EmailIngestService $ingest)
    {
        $domains = config('tempmail.domains');
        if (empty($domains)) {
            return response()->json(['success' => false, 'message' => 'Belum ada domain yang dilayani — tambahkan dulu di Pengaturan.'], 422);
        }
        $domain = $domains[0];
        $alias = 'uji-' . Str::lower(Str::random(5));

        $raw = implode("\r\n", [
            'From: "Setup TempMail" <setup@tempmail.local>',
            "To: {$alias}@{$domain}",
            'Subject: Email uji dari halaman Setup',
            'Message-ID: <uji-' . Str::random(12) . '@tempmail.local>',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<h2>Berhasil!</h2><p>Pipeline penerimaan email kamu bekerja: parsing, penyimpanan, dan tampilan aman.</p>' .
            '<p>Langkah berikutnya: sambungkan Cloudflare agar email asli dari internet ikut masuk ke sini.</p>',
        ]);

        $email = $ingest->ingest($raw, "{$alias}@{$domain}", 'setup@tempmail.local');

        // ingest() mengembalikan null bila pengirimnya kebetulan sedang diblokir
        if (!$email) {
            $pesan = 'Email uji dibuang karena pengirim setup@tempmail.local sedang diblokir — cabut blokirnya di Pengaturan lalu coba lagi.';

            if (request()->expectsJson()) {
                return response()->json(['success' => false, 'message' => $pesan], 422);
            }

            return redirect()->route('setup.index')->with('success', $pesan);
        }

        $pesan = "Email uji berhasil dibuat untuk {$alias}@{$domain} — beginilah tampilan email masuk.";

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan, 'redirect' => route('inbox.show', $email)]);
        }

        return redirect()->route('inbox.show', $email)->with('success', $pesan);
    }

    // Buat token webhook baru (setelah ganti, variabel WEBHOOK_TOKEN di worker harus ikut diganti)
    public function regenerateToken()
    {
        Setting::setValue('webhook_token', Str::random(48));
        $pesan = 'Token webhook baru dibuat. Jangan lupa perbarui WEBHOOK_TOKEN di Cloudflare Worker.';

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => $pesan, 'reload' => true]);
        }

        return redirect()->route('setup.index')->with('success', $pesan);
    }

}
