<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fitur baru: aksi massal, simpan permanen, tandai belum dibaca, unduh .eml,
 * proteksi tautan, gambar inline CID, 2FA TOTP, dan pemangkas kuota.
 */
class FiturBaruTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'token-rahasia-test';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id'],
            'tempmail.webhook_token' => $this->token,
        ]);
    }

    private function kirim(string $raw, string $to = 'coba@danang.biz.id'): void
    {
        $this->call('POST', '/inbound/email', [], [], [], $this->transformHeadersToServerVars([
            'X-Webhook-Token' => $this->token,
            'X-Envelope-To' => $to,
            'X-Envelope-From' => 'pengirim@contoh.com',
            'Content-Type' => 'message/rfc822',
        ]), $raw)->assertStatus(201);
    }

    private function buatEmail(array $override = []): Email
    {
        return Email::create(array_merge([
            'dedup_hash' => hash('sha256', uniqid('', true)),
            'domain' => 'danang.biz.id',
            'alias' => 'coba',
            'to_address' => 'coba@danang.biz.id',
            'from_address' => 'pengirim@contoh.com',
            'subject' => 'Uji',
            'size_bytes' => 1000,
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ], $override));
    }

    public function test_aksi_massal_hapus_dan_tandai(): void
    {
        $owner = User::factory()->create();
        $a = $this->buatEmail();
        $b = $this->buatEmail();
        $c = $this->buatEmail();

        // Tandai dibaca
        $this->actingAs($owner)->postJson('/inbox/bulk', ['action' => 'read', 'ids' => [$a->id, $b->id]])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($a->fresh()->is_read);
        $this->assertTrue($b->fresh()->is_read);
        $this->assertFalse($c->fresh()->is_read);

        // Hapus massal
        $this->actingAs($owner)->postJson('/inbox/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id, $c->id]])
            ->assertOk();
        $this->assertSame(0, Email::count());
    }

    public function test_aksi_massal_akun_alias_tidak_menembus_kotak_lain(): void
    {
        $aliasUser = User::factory()->create(['email' => 'kotakku@danang.biz.id', 'role' => 'alias']);
        $punyaku = $this->buatEmail(['alias' => 'kotakku']);
        $punyaLain = $this->buatEmail(['alias' => 'oranglain']);

        $this->actingAs($aliasUser)->postJson('/inbox/bulk', ['action' => 'delete', 'ids' => [$punyaku->id, $punyaLain->id]])
            ->assertOk();

        $this->assertNull(Email::find($punyaku->id));      // masuk sampah
        $this->assertNotNull(Email::find($punyaLain->id)); // milik orang lain selamat
    }

    public function test_simpan_permanen_dan_kembali_normal(): void
    {
        $owner = User::factory()->create();
        $email = $this->buatEmail();

        $this->actingAs($owner)->patchJson("/email/{$email->id}/keep")->assertOk();
        $this->assertNull($email->fresh()->expires_at);

        $this->actingAs($owner)->patchJson("/email/{$email->id}/keep")->assertOk();
        $this->assertNotNull($email->fresh()->expires_at);
    }

    public function test_tandai_belum_dibaca(): void
    {
        $owner = User::factory()->create();
        $email = $this->buatEmail(['is_read' => true]);

        $this->actingAs($owner)->patchJson("/email/{$email->id}/unread")->assertOk();
        $this->assertFalse($email->fresh()->is_read);
    }

    public function test_unduh_eml_sumber_asli(): void
    {
        $owner = User::factory()->create();
        $raw = "From: a@b.com\r\nTo: coba@danang.biz.id\r\nSubject: Arsip\r\n\r\nIsi email.";
        $this->kirim($raw);

        $email = Email::firstOrFail();
        $res = $this->actingAs($owner)->get("/email/{$email->id}/eml");

        $res->assertOk();
        $res->assertHeader('content-type', 'message/rfc822');
    }

    public function test_link_di_email_diarahkan_ke_halaman_perantara(): void
    {
        $owner = User::factory()->create();
        $raw = implode("\r\n", [
            'From: promo@toko.com',
            'To: coba@danang.biz.id',
            'Subject: Promo',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<p><a href="https://toko.example.com/promo?utm_source=email&id=9">Klik di sini</a></p>',
        ]);
        $this->kirim($raw);

        $email = Email::firstOrFail();
        $res = $this->actingAs($owner)->get("/email/{$email->id}");
        $res->assertOk();
        // href asli diganti route perantara
        $res->assertSee('/keluar?u=', false);
        $res->assertDontSee('href="https://toko.example.com', false);

        // Halaman perantara menampilkan tujuan + membuang parameter pelacak
        $u = rtrim(strtr(base64_encode('https://toko.example.com/promo?utm_source=email&id=9'), '+/', '-_'), '=');
        $perantara = $this->actingAs($owner)->get('/keluar?u=' . $u);
        $perantara->assertOk()
            ->assertSee('toko.example.com')
            ->assertSee('id=9')
            ->assertDontSee('utm_source');
    }

    public function test_gambar_inline_cid_dirender_lewat_route_lampiran(): void
    {
        $owner = User::factory()->create();
        $png = base64_encode("\x89PNG\r\n\x1a\nisi-palsu");
        $raw = implode("\r\n", [
            'From: a@b.com',
            'To: coba@danang.biz.id',
            'Subject: Inline',
            'Content-Type: multipart/related; boundary="btx"',
            '',
            '--btx',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<p>Logo: <img src="cid:logo123"></p>',
            '--btx',
            'Content-Type: image/png; name="logo.png"',
            'Content-ID: <logo123>',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: inline; filename="logo.png"',
            '',
            $png,
            '--btx--',
        ]);
        $this->kirim($raw);

        $email = Email::with('attachments')->firstOrFail();
        $att = $email->attachments->firstWhere('content_id', 'logo123');
        $this->assertNotNull($att);

        // Detail email: cid: sudah di-rewrite ke route inline (tidak diblokir placeholder)
        $res = $this->actingAs($owner)->get("/email/{$email->id}");
        $res->assertOk()->assertSee('/attachment/inline/' . $att->id, false);

        // Route inline menyajikan gambar dengan content-type aslinya lewat signed URL
        $this->actingAs($owner)->get($att->inlineUrl())
            ->assertOk()->assertHeader('content-type', 'image/png');

        // Tanpa tanda tangan (atau tanda tangan diutak-atik) → ditolak
        $this->actingAs($owner)->get("/attachment/inline/{$att->id}")->assertForbidden();
    }

    public function test_lampiran_svg_tidak_pernah_dirender_inline(): void
    {
        // SVG bisa memuat <script> dan akan berjalan di origin aplikasi (stored XSS),
        // jadi harus jatuh ke jalur unduh, bukan ditampilkan inline
        $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $raw = implode("\r\n", [
            'From: jahat@contoh.com',
            'To: coba@danang.biz.id',
            'Subject: Logo',
            'Content-Type: multipart/related; boundary="bts"',
            '',
            '--bts',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<p><img src="cid:logo9"></p>',
            '--bts',
            'Content-Type: image/svg+xml; name="logo.svg"',
            'Content-ID: <logo9>',
            'Content-Transfer-Encoding: base64',
            '',
            $svg,
            '--bts--',
        ]);
        $this->kirim($raw);

        $owner = User::factory()->create();
        $att = Email::with('attachments')->firstOrFail()->attachments->firstOrFail();

        $res = $this->actingAs($owner)->get($att->inlineUrl());

        $res->assertOk();
        $this->assertStringNotContainsString('svg', strtolower($res->headers->get('content-type')));
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));
    }

    public function test_alur_2fa_totp_lengkap(): void
    {
        $owner = User::factory()->create(['password' => bcrypt('rahasia-123')]);

        // Setup: minta secret
        $setup = $this->actingAs($owner)->postJson('/pengaturan/2fa/setup');
        $setup->assertOk();
        $secret = $setup->json('secret');

        // Konfirmasi dengan kode valid (dihitung dari secret yang sama)
        $kode = $this->kodeTotp($secret);
        $confirm = $this->actingAs($owner)->postJson('/pengaturan/2fa/confirm', ['code' => $kode]);
        $confirm->assertOk()->assertJson(['success' => true]);
        $this->assertCount(8, $confirm->json('recovery_codes'));
        $this->assertNotNull($owner->fresh()->totp_secret);

        // Login: password benar → diarahkan ke langkah 2FA, belum masuk
        auth()->logout();
        $this->postJson('/login', ['email' => $owner->email, 'password' => 'rahasia-123'])
            ->assertOk()->assertJson(['redirect' => route('login.2fa')]);
        $this->assertGuest();

        // Kode salah ditolak; kode benar masuk
        $this->postJson('/login/2fa', ['code' => '000000'])->assertStatus(422);
        $this->postJson('/login/2fa', ['code' => $this->kodeTotp($secret)])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertAuthenticatedAs($owner);
    }

    public function test_kuota_penyimpanan_memangkas_email_tertua(): void
    {
        config(['tempmail.max_storage_mb' => 1]);

        $lama = $this->buatEmail(['size_bytes' => 600 * 1024, 'received_at' => now()->subDays(3)]);
        $permanen = $this->buatEmail(['size_bytes' => 300 * 1024, 'received_at' => now()->subDays(2), 'expires_at' => null]);
        $baru = $this->buatEmail(['size_bytes' => 300 * 1024, 'received_at' => now()]);

        $this->artisan('tempmail:trim-storage')->assertSuccessful();

        $this->assertNull($lama->fresh());          // tertua terpangkas
        $this->assertNotNull($permanen->fresh());   // simpan permanen kebal
        $this->assertNotNull($baru->fresh());
    }

    // Hitung kode TOTP valid dari secret — memakai algoritma service yang sama
    private function kodeTotp(string $secret): string
    {
        $service = new TotpService();
        $m = new \ReflectionMethod($service, 'code');
        $m->setAccessible(true);

        return $m->invoke($service, $secret, (int) floor(time() / 30));
    }
}
