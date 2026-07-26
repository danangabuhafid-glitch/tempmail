<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Paket otomasi (API token, alias cepat, TTL) + anti-spam
 * (Authentication-Results tepercaya, folder Spam, skrining lampiran).
 */
class OtomasiDanSpamTest extends TestCase
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
            'tempmail.api_token' => 'api-token-test',
        ]);
    }

    private function kirim(string $raw, string $to = 'coba@danang.biz.id'): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/inbound/email', [], [], [], $this->transformHeadersToServerVars([
            'X-Webhook-Token' => $this->token,
            'X-Envelope-To' => $to,
            'X-Envelope-From' => 'pengirim@contoh.com',
            'Content-Type' => 'message/rfc822',
        ]), $raw);
    }

    private function rawDenganHeader(array $headerTambahan, string $body = 'Isi.'): string
    {
        return implode("\r\n", array_merge([
            'From: toko@promo.com',
            'To: coba@danang.biz.id',
            'Subject: Halo',
        ], $headerTambahan, ['', $body]));
    }

    // ===== Anti-spam =====

    public function test_authentication_results_palsu_dari_pengirim_diabaikan(): void
    {
        // Pengirim menyisipkan header palsu ber-authserv-id miliknya sendiri
        $this->kirim($this->rawDenganHeader([
            'Authentication-Results: mail.penipu.com; spf=pass; dkim=pass; dmarc=pass',
        ]))->assertStatus(201);

        $email = Email::firstOrFail();
        $this->assertNull($email->spf_result);   // header palsu TIDAK dipercaya
        $this->assertNull($email->dkim_result);
        $this->assertFalse($email->is_spam);
    }

    public function test_email_gagal_autentikasi_masuk_folder_spam(): void
    {
        $this->kirim($this->rawDenganHeader([
            'Authentication-Results: mx.cloudflare.net; spf=fail; dkim=fail; dmarc=fail',
        ]))->assertStatus(201);

        $email = Email::firstOrFail();
        $this->assertSame('fail', $email->dmarc_result);
        $this->assertTrue($email->is_spam);

        // Folder: inbox default menyembunyikan spam; ?spam=1 menampilkannya
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/')->assertOk()->assertDontSee('Halo');
        $this->actingAs($owner)->get('/?spam=1')->assertOk()->assertSee('Halo');

        // Spam tidak masuk hitungan unread poll
        $this->actingAs($owner)->getJson('/inbox/poll')->assertJson(['unread' => 0]);
    }

    public function test_lampiran_berbahaya_ditandai(): void
    {
        $raw = implode("\r\n", [
            'From: jahat@contoh.com',
            'To: coba@danang.biz.id',
            'Subject: Invoice',
            'Content-Type: multipart/mixed; boundary="btz"',
            '',
            '--btz',
            'Content-Type: text/plain',
            '',
            'Cek lampiran.',
            '--btz',
            'Content-Type: application/octet-stream; name="tagihan.pdf.exe"',
            'Content-Disposition: attachment; filename="tagihan.pdf.exe"',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode('MZ fake exe'),
            '--btz--',
        ]);
        $this->kirim($raw)->assertStatus(201);

        $att = Email::firstOrFail()->attachments()->firstOrFail();
        $this->assertTrue((bool) $att->is_flagged);
    }

    // ===== Alias TTL =====

    public function test_email_ke_alias_kedaluwarsa_ditolak(): void
    {
        EmailAlias::create(['alias' => 'burner', 'domain' => 'danang.biz.id', 'expires_at' => now()->subHour()]);

        $this->kirim($this->rawDenganHeader([]), 'burner@danang.biz.id')->assertStatus(422);
        $this->assertSame(0, Email::count());

        // Alias yang masih aktif tetap menerima
        EmailAlias::create(['alias' => 'aktif', 'domain' => 'danang.biz.id', 'expires_at' => now()->addHour()]);
        $this->kirim($this->rawDenganHeader([]), 'aktif@danang.biz.id')->assertStatus(201);
    }

    // ===== API =====

    public function test_api_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/emails')->assertStatus(401);
        $this->getJson('/api/emails', ['Authorization' => 'Bearer salah'])->assertStatus(401);
    }

    public function test_api_daftar_email_dan_otp(): void
    {
        $this->kirim($this->rawDenganHeader([], 'Kode verifikasi kamu: 482913'));

        $headers = ['Authorization' => 'Bearer api-token-test'];

        $this->getJson('/api/emails?alias=coba', $headers)
            ->assertOk()
            ->assertJsonPath('emails.0.to', 'coba@danang.biz.id')
            ->assertJsonPath('emails.0.otp', '482913');

        $this->getJson('/api/otp?alias=coba', $headers)
            ->assertOk()
            ->assertJsonPath('otp', '482913');

        // Alias tanpa OTP → 404 dengan otp null
        $this->getJson('/api/otp?alias=tidak-ada', $headers)->assertStatus(404);
    }

    public function test_api_alias_cepat_membuat_alamat_berlabel_situs(): void
    {
        $res = $this->getJson('/api/alias/quick?site=www.Netflix.com&ttl=24h', ['Authorization' => 'Bearer api-token-test']);

        $res->assertStatus(201);
        $address = $res->json('address');
        $this->assertStringStartsWith('netflix-', $address);
        $this->assertStringEndsWith('@danangabuhafid.my.id', $address);

        $alias = EmailAlias::firstOrFail();
        $this->assertSame('www.Netflix.com', $alias->label);
        $this->assertNotNull($alias->expires_at);
    }

    public function test_halaman_alias_cepat_untuk_bookmarklet(): void
    {
        $owner = User::factory()->create();

        $res = $this->actingAs($owner)->get('/alamat/cepat?site=github.com');

        $res->assertOk()->assertSee('github-');
        $this->assertSame(1, EmailAlias::count());
    }
}
