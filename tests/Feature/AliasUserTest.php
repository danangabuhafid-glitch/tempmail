<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Akun alias: tiap alamat yang dibuat bisa login (password acak yang dibuat
 * per batch, wajib diganti saat login pertama) dan hanya boleh melihat email
 * kotak masuknya sendiri.
 */
class AliasUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id']]);
    }

    // Buat alias lalu set password yang diketahui + lepas kewajiban ganti password,
    // supaya test fitur lain tidak terhalang redirect paksa-ganti-password
    private function buatAlias(string $alias = 'kotakku', string $domain = 'danang.biz.id'): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/alamat', [
            'mode' => 'manual',
            'alias' => $alias,
            'domain' => $domain,
        ])->assertOk();

        auth()->logout();

        $user = User::where('email', "{$alias}@{$domain}")->firstOrFail();
        $user->forceFill(['password' => 'passwordku123', 'must_change_password' => false])->save();

        return $user->fresh();
    }

    private function buatEmail(string $alias, string $domain, string $subject): Email
    {
        return Email::create([
            'dedup_hash' => hash('sha256', uniqid('', true)),
            'domain' => $domain,
            'alias' => $alias,
            'to_address' => "{$alias}@{$domain}",
            'from_address' => 'pengirim@contoh.com',
            'subject' => $subject,
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_alamat_baru_punya_akun_dengan_password_acak_dan_wajib_ganti(): void
    {
        $owner = User::factory()->create();
        $res = $this->actingAs($owner)->postJson('/alamat', [
            'mode' => 'manual',
            'alias' => 'kotakku',
            'domain' => 'danang.biz.id',
        ])->assertOk();

        // Password acak diumumkan sekali di pesan hasil
        $this->assertSame(1, preg_match('/password (\S+) \(wajib/', $res->json('message'), $m));
        $password = $m[1];

        auth()->logout();

        $aliasUser = User::where('email', 'kotakku@danang.biz.id')->firstOrFail();
        $this->assertSame('alias', $aliasUser->role);
        $this->assertTrue($aliasUser->must_change_password);

        $this->postJson('/login', ['email' => $aliasUser->email, 'password' => $password])
            ->assertOk()->assertJson(['success' => true]);

        // Sebelum ganti password, halaman lain dialihkan ke Pengaturan
        $this->get('/')->assertRedirect(route('settings.index'));

        // Setelah ganti password, inbox terbuka
        $this->post('/pengaturan/password', [
            'current_password' => $password,
            'password' => 'passwordbaruku1',
            'password_confirmation' => 'passwordbaruku1',
        ]);
        $this->assertFalse($aliasUser->fresh()->must_change_password);
        $this->get('/')->assertOk();
    }

    public function test_buat_alias_tidak_menimpa_akun_yang_sudah_ada(): void
    {
        // Akun (mis. owner) yang emailnya kebetulan sama TIDAK boleh di-reset/downgrade
        $ada = User::factory()->create(['email' => 'penting@danang.biz.id', 'role' => 'owner']);
        $passwordLama = $ada->password;

        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson('/alamat', [
            'mode' => 'manual',
            'alias' => 'penting',
            'domain' => 'danang.biz.id',
        ])->assertOk();

        $ada->refresh();
        $this->assertSame('owner', $ada->role);
        $this->assertSame($passwordLama, $ada->password);
    }

    public function test_akun_alias_hanya_melihat_email_kotaknya_sendiri(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $this->buatEmail('kotakku', 'danang.biz.id', 'Email Untukku');
        $this->buatEmail('oranglain', 'danang.biz.id', 'Email Orang Lain');

        $res = $this->actingAs($aliasUser)->get('/');

        $res->assertOk()
            ->assertSee('Email Untukku')
            ->assertDontSee('Email Orang Lain')
            ->assertDontSee('Buat Alamat Baru');
    }

    public function test_akun_alias_tidak_bisa_buka_email_orang_lain(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $milikku = $this->buatEmail('kotakku', 'danang.biz.id', 'Punyaku');
        $milikLain = $this->buatEmail('oranglain', 'danang.biz.id', 'Punya Lain');

        $this->actingAs($aliasUser)->get("/email/{$milikku->id}")->assertOk();
        $this->actingAs($aliasUser)->get("/email/{$milikLain->id}")->assertForbidden();
    }

    public function test_akun_alias_diblok_dari_fitur_pemilik(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $milikLain = $this->buatEmail('oranglain', 'danang.biz.id', 'Punya Lain');

        $this->actingAs($aliasUser)->get('/alamat')->assertForbidden();
        $this->actingAs($aliasUser)->get('/setup')->assertForbidden();
        $this->actingAs($aliasUser)->postJson('/alamat', ['alias' => 'x', 'domain' => 'danang.biz.id'])->assertForbidden();
        $this->actingAs($aliasUser)->deleteJson("/email/{$milikLain->id}")->assertForbidden();
        $this->actingAs($aliasUser)->postJson('/pengaturan/domain', ['domain' => 'baru.my.id'])->assertForbidden();

        $this->assertNotNull($milikLain->fresh()); // email orang lain tidak terhapus
    }

    public function test_akun_alias_boleh_hapus_email_kotaknya_sendiri(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $milikku = $this->buatEmail('kotakku', 'danang.biz.id', 'Punyaku');

        $this->actingAs($aliasUser)->deleteJson("/email/{$milikku->id}")
            ->assertOk()->assertJson(['success' => true]);

        // Hapus = pindah ke keranjang sampah (bisa dipulihkan 7 hari)
        $this->assertNull(Email::find($milikku->id));
        $this->assertNotNull(Email::withTrashed()->find($milikku->id));
    }

    public function test_poll_akun_alias_hanya_menghitung_kotaknya(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $this->buatEmail('kotakku', 'danang.biz.id', 'Punyaku');
        $this->buatEmail('oranglain', 'danang.biz.id', 'Punya Lain');
        $this->buatEmail('oranglain', 'danang.biz.id', 'Punya Lain 2');

        $this->actingAs($aliasUser)->getJson('/inbox/poll')
            ->assertOk()->assertJson(['unread' => 1]);
    }

    public function test_hapus_alias_ikut_mencabut_akun_loginnya(): void
    {
        $aliasUser = $this->buatAlias('kotakku');
        $owner = User::factory()->create();
        $alias = EmailAlias::where('alias', 'kotakku')->firstOrFail();

        $this->actingAs($owner)->deleteJson("/alamat/{$alias->id}")->assertOk();

        $this->assertNull(User::find($aliasUser->id));
    }

    public function test_akun_alias_bisa_ganti_password_sendiri(): void
    {
        $aliasUser = $this->buatAlias('kotakku');

        $this->actingAs($aliasUser)->postJson('/pengaturan/password', [
            'current_password' => 'passwordku123',
            'password' => 'passwordbaruku1',
            'password_confirmation' => 'passwordbaruku1',
        ])->assertOk()->assertJson(['success' => true]);
    }
}
