<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Keranjang sampah (soft delete), catatan pribadi, dan halaman statistik.
 */
class SampahCatatanStatistikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id']]);
    }

    private function buatEmail(array $override = []): Email
    {
        return Email::create(array_merge([
            'dedup_hash' => hash('sha256', uniqid('', true)),
            'domain' => 'danang.biz.id',
            'alias' => 'coba',
            'to_address' => 'coba@danang.biz.id',
            'from_address' => 'pengirim@contoh.com',
            'subject' => 'Uji Sampah',
            'size_bytes' => 1000,
            'received_at' => now(),
            'expires_at' => now()->addDays(30),
        ], $override));
    }

    public function test_hapus_masuk_sampah_lalu_bisa_dipulihkan(): void
    {
        $owner = User::factory()->create();
        $email = $this->buatEmail();

        $this->actingAs($owner)->deleteJson("/email/{$email->id}")->assertOk();

        // Hilang dari inbox, muncul di Sampah, record masih ada
        $this->assertNull(Email::find($email->id));
        $this->assertNotNull(Email::withTrashed()->find($email->id));
        $this->actingAs($owner)->get('/')->assertOk()->assertDontSee('Uji Sampah');
        $this->actingAs($owner)->get('/?trash=1')->assertOk()->assertSee('Uji Sampah');

        // Pulihkan
        $this->actingAs($owner)->patchJson("/email/{$email->id}/pulihkan")->assertOk();
        $this->assertNotNull(Email::find($email->id));
        $this->actingAs($owner)->get('/')->assertOk()->assertSee('Uji Sampah');
    }

    public function test_kosongkan_sampah_menghapus_permanen(): void
    {
        $owner = User::factory()->create();
        $a = $this->buatEmail();
        $b = $this->buatEmail();
        $a->delete();

        $this->actingAs($owner)->deleteJson('/sampah')->assertOk();

        $this->assertNull(Email::withTrashed()->find($a->id)); // benar-benar hilang
        $this->assertNotNull(Email::find($b->id));             // yang di inbox aman
    }

    public function test_akun_alias_tidak_bisa_memulihkan_email_orang_lain(): void
    {
        $aliasUser = User::factory()->create(['email' => 'kotakku@danang.biz.id', 'role' => 'alias']);
        $punyaLain = $this->buatEmail(['alias' => 'oranglain']);
        $punyaLain->delete();

        $this->actingAs($aliasUser)->patchJson("/email/{$punyaLain->id}/pulihkan")->assertForbidden();
        $this->assertNull(Email::find($punyaLain->id)); // tetap di sampah
    }

    public function test_email_di_sampah_lebih_dari_7_hari_dihapus_permanen_oleh_prune(): void
    {
        $lama = $this->buatEmail();
        $lama->delete();
        $lama->forceFill(['deleted_at' => now()->subDays(8)])->save();

        $baru = $this->buatEmail();
        $baru->delete();

        $this->artisan('model:prune', ['--model' => [Email::class]])->assertSuccessful();

        $this->assertNull(Email::withTrashed()->find($lama->id));
        $this->assertNotNull(Email::withTrashed()->find($baru->id));
    }

    public function test_aksi_massal_pulihkan_dan_hapus_permanen(): void
    {
        $owner = User::factory()->create();
        $a = $this->buatEmail();
        $b = $this->buatEmail();
        $a->delete();
        $b->delete();

        $this->actingAs($owner)->postJson('/inbox/bulk', ['action' => 'restore', 'ids' => [$a->id]])->assertOk();
        $this->assertNotNull(Email::find($a->id));

        $this->actingAs($owner)->postJson('/inbox/bulk', ['action' => 'purge', 'ids' => [$b->id]])->assertOk();
        $this->assertNull(Email::withTrashed()->find($b->id));
    }

    public function test_catatan_pribadi_tersimpan(): void
    {
        $owner = User::factory()->create();
        $email = $this->buatEmail();

        $this->actingAs($owner)->postJson("/email/{$email->id}/catatan", ['note' => 'Trial premium, expire 3 Agustus'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('Trial premium, expire 3 Agustus', $email->fresh()->note);
        $this->actingAs($owner)->get("/email/{$email->id}")->assertSee('Trial premium, expire 3 Agustus');

        // Dikosongkan → null
        $this->actingAs($owner)->postJson("/email/{$email->id}/catatan", ['note' => ''])->assertOk();
        $this->assertNull($email->fresh()->note);
    }

    public function test_halaman_statistik(): void
    {
        $owner = User::factory()->create();
        $this->buatEmail(['from_address' => 'netflix@netflix.com', 'otp_code' => '123456']);
        $this->buatEmail(['from_address' => 'netflix@netflix.com']);
        $this->buatEmail(['from_address' => 'spam@jahat.com', 'is_spam' => true]);

        $res = $this->actingAs($owner)->get('/statistik');

        $res->assertOk()
            ->assertSee('Statistik')
            ->assertSee('netflix@netflix.com')
            ->assertSee('coba@danang.biz.id');

        // Akun alias tidak boleh melihat statistik global
        $aliasUser = User::factory()->create(['email' => 'kotakku@danang.biz.id', 'role' => 'alias']);
        $this->actingAs($aliasUser)->get('/statistik')->assertForbidden();
    }
}
