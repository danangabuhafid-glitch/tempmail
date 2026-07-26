<?php

namespace App\Http\Controllers;

use App\Models\BlockedSender;
use App\Models\Email;
use App\Models\Setting;
use App\Services\PushNotifier;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        // Request AJAX cukup dijawab partial (refresh setelah tambah/hapus)
        if ($request->ajax()) {
            abort_unless($request->user()->isOwner(), 403);

            if ($request->query('partial') === 'blocked') {
                return view('settings._blocked')->render();
            }

            return view('settings._domains')->render();
        }

        return view('settings.index');
    }

    // Simpan pengaturan umum: masa retensi + kuota penyimpanan
    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_storage_mb' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ]);

        Setting::setValue('retention_days', (string) $data['retention_days']);
        Setting::setValue('max_storage_mb', (string) ($data['max_storage_mb'] ?? 0));

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Pengaturan umum disimpan.']);
        }

        return redirect()->route('settings.index')->with('success', 'Pengaturan umum disimpan.');
    }

    // Simpan tujuan notifikasi push (Telegram / ntfy) — kosongkan untuk mematikan
    public function updateNotify(Request $request)
    {
        $data = $request->validate([
            'telegram_token' => ['nullable', 'string', 'max:100'],
            'telegram_chat_id' => ['nullable', 'string', 'max:50'],
            'ntfy_url' => ['nullable', 'url', 'max:255'],
        ]);

        Setting::setValue('notify_telegram_token', trim((string) ($data['telegram_token'] ?? '')));
        Setting::setValue('notify_telegram_chat_id', trim((string) ($data['telegram_chat_id'] ?? '')));
        Setting::setValue('notify_ntfy_url', trim((string) ($data['ntfy_url'] ?? '')));

        return response()->json(['success' => true, 'message' => 'Pengaturan notifikasi disimpan.']);
    }

    // Kirim notifikasi percobaan memakai pengaturan yang TERSIMPAN
    public function testNotify(PushNotifier $notifier)
    {
        if (blank(config('tempmail.notify_telegram_token')) && blank(config('tempmail.notify_ntfy_url'))) {
            return response()->json(['success' => false, 'message' => 'Isi & simpan dulu pengaturan Telegram/ntfy-nya.'], 422);
        }

        $contoh = new Email([
            'subject' => 'Uji notifikasi TempMail',
            'from_name' => 'TempMail',
            'from_address' => 'tes@tempmail.local',
            'to_address' => 'kamu@' . (config('tempmail.domains')[0] ?? 'domainmu'),
            'otp_code' => '123456',
        ]);
        $notifier->emailBaru($contoh);

        return response()->json(['success' => true, 'message' => 'Notifikasi uji dikirim — cek HP kamu.']);
    }

    // ===== Blokir pengirim =====

    public function blockSender(Request $request)
    {
        $data = $request->validate([
            'pattern' => ['required', 'string', 'max:250', 'regex:/^([a-z0-9._%+\-]+@)?([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ], [
            'pattern.regex' => 'Isi alamat lengkap (spam@contoh.com) atau domain (contoh.com).',
        ]);

        $pattern = Str::lower(trim($data['pattern']));
        BlockedSender::firstOrCreate(['pattern' => $pattern]);

        return response()->json(['success' => true, 'message' => "{$pattern} diblokir — email berikutnya langsung dibuang."]);
    }

    public function unblockSender(Request $request)
    {
        BlockedSender::where('pattern', Str::lower(trim((string) $request->input('pattern'))))->delete();

        return response()->json(['success' => true, 'message' => 'Blokir dicabut.']);
    }

    // ===== 2FA TOTP (khusus owner) =====

    // Langkah 1: buat secret sementara di session, kirim otpauth URI untuk QR
    public function twoFactorSetup(Request $request, TotpService $totp)
    {
        $secret = $totp->generateSecret();
        $request->session()->put('2fa.setup_secret', $secret);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'otpauth' => $totp->otpauthUri($secret, $request->user()->email),
        ]);
    }

    // Langkah 2: verifikasi kode dari aplikasi authenticator → 2FA aktif
    public function twoFactorConfirm(Request $request, TotpService $totp)
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        $secret = (string) $request->session()->get('2fa.setup_secret');
        if ($secret === '' || !$totp->verify($secret, $request->code)) {
            return response()->json(['success' => false, 'message' => 'Kode tidak cocok. Scan ulang QR lalu coba lagi.'], 422);
        }

        // Kode recovery sekali pakai — simpan hash-nya saja, tampilkan sekali
        $plain = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)));
        $request->user()->update([
            'totp_secret' => $secret,
            'totp_recovery_codes' => json_encode($plain->map(fn ($c) => hash('sha256', $c))->values()),
        ]);
        $request->session()->forget('2fa.setup_secret');

        return response()->json([
            'success' => true,
            'message' => '2FA aktif! Simpan kode recovery berikut.',
            'recovery_codes' => $plain,
        ]);
    }

    public function twoFactorDisable(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ], [
            'current_password.current_password' => 'Password salah.',
        ]);

        $request->user()->update(['totp_secret' => null, 'totp_recovery_codes' => null]);

        return response()->json(['success' => true, 'message' => '2FA dimatikan.', 'reload' => true]);
    }

    // Buat/ganti token API (Bearer) untuk skrip & bot
    public function regenerateApiToken()
    {
        Setting::setValue('api_token', Str::random(48));

        return response()->json(['success' => true, 'message' => 'Token API baru dibuat.', 'reload' => true]);
    }

    // ===== Domain =====

    // Tambah satu domain yang dilayani
    public function addDomain(Request $request)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:253', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i'],
        ], [
            'domain.regex' => 'Format domain tidak valid, contoh: danang.biz.id',
        ]);

        $domain = strtolower(trim($data['domain']));
        $domains = config('tempmail.domains');

        if (in_array($domain, $domains, true)) {
            return response()->json(['success' => false, 'message' => "Domain {$domain} sudah ada di daftar."], 422);
        }

        $domains[] = $domain;
        Setting::setValue('domains', implode(',', $domains));

        return response()->json(['success' => true, 'message' => "Domain {$domain} ditambahkan. Jangan lupa setup Cloudflare untuk domain ini."]);
    }

    // Hapus satu domain dari daftar (minimal harus tersisa satu)
    public function removeDomain(Request $request)
    {
        $domain = strtolower(trim((string) $request->input('domain')));
        $domains = config('tempmail.domains');

        if (!in_array($domain, $domains, true)) {
            return response()->json(['success' => false, 'message' => 'Domain tidak ditemukan di daftar.'], 422);
        }
        if (count($domains) <= 1) {
            return response()->json(['success' => false, 'message' => 'Minimal harus ada satu domain yang dilayani.'], 422);
        }

        $domains = array_values(array_filter($domains, fn ($d) => $d !== $domain));
        Setting::setValue('domains', implode(',', $domains));

        return response()->json(['success' => true, 'message' => "Domain {$domain} dihapus. Email lama untuk domain itu tetap tersimpan."]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'Password saat ini salah.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $request->user()->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Password berhasil diganti.']);
        }

        return redirect()->route('settings.index')->with('success', 'Password berhasil diganti.');
    }
}
