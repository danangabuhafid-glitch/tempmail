<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InboundEmailTest extends TestCase
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

    private function rawEmailSederhana(): string
    {
        return implode("\r\n", [
            'From: "Netflix" <info@netflix.com>',
            'To: coba123@danangabuhafid.my.id',
            'Subject: Kode Verifikasi',
            'Message-ID: <uji-1@netflix.com>',
            'Authentication-Results: mx.cloudflare.net; spf=pass; dkim=pass',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Kode verifikasi kamu: 123456',
        ]);
    }

    private function kirim(string $raw, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->call('POST', '/inbound/email', [], [], [], $this->transformHeadersToServerVars(array_merge([
            'X-Webhook-Token' => $this->token,
            'X-Envelope-To' => 'coba123@danangabuhafid.my.id',
            'X-Envelope-From' => 'info@netflix.com',
            'Content-Type' => 'message/rfc822',
        ], $headers)), $raw);
    }

    public function test_webhook_tanpa_token_ditolak(): void
    {
        $res = $this->kirim($this->rawEmailSederhana(), ['X-Webhook-Token' => 'salah']);

        $res->assertStatus(401);
        $this->assertSame(0, Email::count());
    }

    public function test_email_masuk_tersimpan_dengan_benar(): void
    {
        $res = $this->kirim($this->rawEmailSederhana());

        $res->assertStatus(201);

        $email = Email::first();
        $this->assertNotNull($email);
        $this->assertSame('coba123', $email->alias);
        $this->assertSame('danangabuhafid.my.id', $email->domain);
        $this->assertSame('info@netflix.com', $email->from_address);
        $this->assertSame('Netflix', $email->from_name);
        $this->assertSame('Kode Verifikasi', $email->subject);
        $this->assertStringContainsString('123456', $email->text_body);
        $this->assertSame('pass', $email->spf_result);
        $this->assertSame('pass', $email->dkim_result);
        $this->assertNotNull($email->expires_at);
        $this->assertFalse($email->is_read);

        // Kode OTP terdeteksi otomatis + raw .eml tersimpan
        $this->assertSame('123456', $email->otp_code);
        $this->assertNotNull($email->raw_path);
        Storage::disk('local')->assertExists($email->raw_path);
    }

    public function test_email_sama_ke_dua_alias_tersimpan_di_dua_kotak(): void
    {
        // BCC/CC: raw identik, envelope beda — keduanya harus masuk
        $this->kirim($this->rawEmailSederhana(), ['X-Envelope-To' => 'kotak-a@danang.biz.id'])->assertStatus(201);
        $this->kirim($this->rawEmailSederhana(), ['X-Envelope-To' => 'kotak-b@danang.biz.id'])->assertStatus(201);

        $this->assertSame(2, Email::count());
    }

    public function test_pengirim_diblokir_langsung_dibuang(): void
    {
        \App\Models\BlockedSender::create(['pattern' => 'netflix.com']);

        // 200 (bukan error) supaya worker tidak retry
        $this->kirim($this->rawEmailSederhana())->assertStatus(200);
        $this->assertSame(0, Email::count());
    }

    public function test_semua_request_webhook_tercatat_di_log(): void
    {
        $this->kirim($this->rawEmailSederhana())->assertStatus(201);
        $this->kirim($this->rawEmailSederhana(), ['X-Webhook-Token' => 'salah'])->assertStatus(401);

        $this->assertSame(1, \App\Models\WebhookLog::where('status', 201)->count());
        $this->assertSame(1, \App\Models\WebhookLog::where('status', 401)->count());
    }

    public function test_rate_limit_webhook(): void
    {
        config(['tempmail.inbound_rpm' => 2]);

        $this->kirim($this->rawEmailSederhana())->assertStatus(201);
        $this->kirim($this->rawEmailSederhana(), ['X-Envelope-To' => 'b@danang.biz.id'])->assertStatus(201);
        $this->kirim($this->rawEmailSederhana(), ['X-Envelope-To' => 'c@danang.biz.id'])->assertStatus(429);
    }

    public function test_email_duplikat_tidak_digandakan(): void
    {
        $this->kirim($this->rawEmailSederhana())->assertStatus(201);
        $this->kirim($this->rawEmailSederhana())->assertStatus(201);

        $this->assertSame(1, Email::count());
    }

    public function test_domain_lain_ditolak(): void
    {
        $res = $this->kirim($this->rawEmailSederhana(), ['X-Envelope-To' => 'a@domainlain.com']);

        $res->assertStatus(422);
        $this->assertSame(0, Email::count());
    }

    public function test_lampiran_tersimpan_di_disk_privat(): void
    {
        Storage::fake('local');

        $raw = implode("\r\n", [
            'From: pengirim@contoh.com',
            'To: coba123@danang.biz.id',
            'Subject: Ada lampiran',
            'Content-Type: multipart/mixed; boundary="bts123"',
            '',
            '--bts123',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Cek lampirannya ya.',
            '--bts123',
            'Content-Type: application/pdf; name="tagihan.pdf"',
            'Content-Disposition: attachment; filename="tagihan.pdf"',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode('%PDF-1.4 isi pdf palsu'),
            '--bts123--',
        ]);

        $this->kirim($raw, ['X-Envelope-To' => 'coba123@danang.biz.id'])->assertStatus(201);

        $email = Email::first();
        $this->assertSame(1, $email->attachments()->count());

        $att = $email->attachments->first();
        $this->assertSame('tagihan.pdf', $att->filename);
        Storage::disk('local')->assertExists($att->path);
    }

    public function test_inbox_butuh_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_endpoint_poll_memberi_id_terbaru_dan_jumlah_belum_dibaca(): void
    {
        $this->kirim($this->rawEmailSederhana());
        $user = User::factory()->create();
        $email = Email::first();

        $this->actingAs($user)
            ->getJson('/inbox/poll')
            ->assertOk()
            ->assertJson(['latest_id' => $email->id, 'unread' => 1]);

        // Tanpa login tidak boleh bocor
        auth()->logout();
        $this->get('/inbox/poll')->assertRedirect('/login');
    }

    public function test_pemilik_bisa_login_dan_lihat_email(): void
    {
        $this->kirim($this->rawEmailSederhana());
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Kode Verifikasi')
            ->assertSee('coba123');
    }

    public function test_detail_email_menandai_terbaca_dan_menyaring_html(): void
    {
        $raw = implode("\r\n", [
            'From: jahat@contoh.com',
            'To: target@danangabuhafid.my.id',
            'Subject: Uji XSS',
            'Content-Type: text/html; charset=utf-8',
            '',
            '<p>Halo</p><script>alert("xss")</script><img src="https://tracker.com/pixel.png">',
        ]);
        $this->kirim($raw, ['X-Envelope-To' => 'target@danangabuhafid.my.id']);

        $user = User::factory()->create();
        $email = Email::first();

        $res = $this->actingAs($user)->get("/email/{$email->id}");

        $res->assertOk();
        // Script harus hilang, gambar remote diganti placeholder
        $res->assertDontSee('alert("xss")', false);
        $res->assertDontSee('tracker.com/pixel.png');
        $this->assertTrue($email->fresh()->is_read);
    }
}
