<?php

use App\Http\Controllers\AliasController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StatsController;
use Illuminate\Support\Facades\Route;

// Webhook dari Cloudflare Email Worker (proteksi via X-Webhook-Token, bukan session)
Route::post('/inbound/email', [InboundEmailController::class, 'store'])->name('inbound.email');

// Dokumentasi publik Developer API
Route::get('/api/docs', [ApiController::class, 'docs'])->name('api.docs');

// API Developer & Bot — Autentikasi Bearer token / X-Api-Token / ?token=
Route::middleware('api.token')->prefix('api')->name('api.')->group(function () {
    Route::get('/domains', [ApiController::class, 'domains'])->name('domains');
    Route::match(['GET', 'POST'], '/create', [ApiController::class, 'create'])->name('create');
    Route::match(['GET', 'POST'], '/generate', [ApiController::class, 'create'])->name('generate');
    Route::get('/alias/quick', [ApiController::class, 'quickAlias'])->name('alias.quick');
    Route::delete('/alias/{alias}', [ApiController::class, 'destroyAlias'])->name('alias.destroy');

    Route::get('/emails', [ApiController::class, 'emails'])->name('emails');
    Route::get('/emails/{email}', [ApiController::class, 'show'])->name('emails.show');
    Route::delete('/emails/{email}', [ApiController::class, 'destroy'])->name('emails.destroy');

    Route::get('/otp', [ApiController::class, 'otp'])->name('otp');
    Route::get('/wait-email', [ApiController::class, 'waitEmail'])->name('wait.email');
    Route::get('/wait-otp', [ApiController::class, 'waitOtp'])->name('wait.otp');
});

// Gambar inline (cid:) di dalam iframe tampilan email. Di luar grup auth karena
// iframe srcdoc tidak selalu membawa cookie sesi; otorisasinya = signed URL
// berumur 2 jam yang hanya dibuat saat pemiliknya membuka email itu.
Route::get('/attachment/inline/{attachment}', [InboxController::class, 'inlineAttachment'])
    ->middleware('signed')
    ->name('inbox.inline');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Langkah kedua login untuk akun ber-2FA (kode TOTP / recovery)
Route::get('/login/2fa', [AuthController::class, 'showTwoFactor'])->name('login.2fa');
Route::post('/login/2fa', [AuthController::class, 'twoFactor'])->middleware('throttle:5,1');

Route::middleware(['auth', 'force.pwd'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Inbox — akun alias otomatis dibatasi ke alamatnya sendiri di controller
    Route::get('/', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
    Route::post('/inbox/bulk', [InboxController::class, 'bulk'])->name('inbox.bulk');
    Route::get('/email/{email}', [InboxController::class, 'show'])->name('inbox.show');
    Route::delete('/email/{email}', [InboxController::class, 'destroy'])->name('inbox.destroy');
    Route::post('/email/{email}/catatan', [InboxController::class, 'saveNote'])->name('inbox.note');
    Route::patch('/email/{email}/pulihkan', [InboxController::class, 'restore'])->name('inbox.restore');
    Route::delete('/sampah', [InboxController::class, 'emptyTrash'])->name('inbox.trash.empty');
    Route::patch('/email/{email}/unread', [InboxController::class, 'markUnread'])->name('inbox.unread');
    Route::patch('/email/{email}/keep', [InboxController::class, 'toggleKeep'])->name('inbox.keep');
    Route::get('/email/{email}/eml', [InboxController::class, 'downloadEml'])->name('inbox.eml');
    Route::get('/attachment/{attachment}', [InboxController::class, 'attachment'])->name('inbox.attachment');

    // Halaman perantara anti-phishing untuk semua link di email
    Route::get('/keluar', [LinkController::class, 'keluar'])->name('link.keluar');

    // Ganti password sendiri — boleh untuk semua akun (owner & alias)
    Route::get('/pengaturan', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/pengaturan/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // ===== Khusus pemilik =====
    Route::middleware('owner')->group(function () {
        // Kelola alamat email (alias catch-all)
        Route::get('/alamat', [AliasController::class, 'index'])->name('alias.index');
        Route::get('/alamat/cepat', [AliasController::class, 'cepat'])->name('alias.cepat');
        Route::post('/alamat', [AliasController::class, 'store'])->name('alias.store');
        Route::patch('/alamat/{alias}/pin', [AliasController::class, 'togglePin'])->name('alias.pin');
        Route::patch('/alamat/{alias}/label', [AliasController::class, 'updateLabel'])->name('alias.label');
        Route::delete('/alamat/{alias}', [AliasController::class, 'destroy'])->name('alias.destroy');

        // Pengaturan aplikasi
        Route::post('/pengaturan/umum', [SettingsController::class, 'updateGeneral'])->name('settings.general');
        Route::post('/pengaturan/notifikasi', [SettingsController::class, 'updateNotify'])->name('settings.notify');
        Route::post('/pengaturan/notifikasi/tes', [SettingsController::class, 'testNotify'])->name('settings.notify.test');
        Route::post('/pengaturan/domain', [SettingsController::class, 'addDomain'])->name('settings.domain.add');
        Route::delete('/pengaturan/domain', [SettingsController::class, 'removeDomain'])->name('settings.domain.remove');

        // Blokir pengirim (tombol cepat ada di halaman detail email)
        Route::post('/pengaturan/blokir', [SettingsController::class, 'blockSender'])->name('settings.block');
        Route::delete('/pengaturan/blokir', [SettingsController::class, 'unblockSender'])->name('settings.unblock');

        // Token API untuk skrip & bookmarklet
        Route::post('/pengaturan/api-token', [SettingsController::class, 'regenerateApiToken'])->name('settings.api.token');

        // 2FA TOTP
        Route::post('/pengaturan/2fa/setup', [SettingsController::class, 'twoFactorSetup'])->name('settings.2fa.setup');
        Route::post('/pengaturan/2fa/confirm', [SettingsController::class, 'twoFactorConfirm'])->name('settings.2fa.confirm');
        Route::post('/pengaturan/2fa/disable', [SettingsController::class, 'twoFactorDisable'])->name('settings.2fa.disable');

        // Statistik pemakaian inbox
        Route::get('/statistik', [StatsController::class, 'index'])->name('stats.index');

        // Wizard setup Cloudflare via web
        Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
        Route::post('/setup/test-email', [SetupController::class, 'testEmail'])->name('setup.test');
        Route::post('/setup/token', [SetupController::class, 'regenerateToken'])->name('setup.token');
    });
});
