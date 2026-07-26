<?php

namespace Tests\Feature;

use App\Models\EmailAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AliasSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id']]);
    }

    public function test_bisa_menambah_alamat_baru(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/alamat', [
            'alias' => 'Belanja',
            'domain' => 'danang.biz.id',
            'label' => 'Buat marketplace',
        ]);

        $res->assertRedirect(route('alias.index'));
        $this->assertDatabaseHas('aliases', [
            'alias' => 'belanja', // dinormalisasi jadi huruf kecil
            'domain' => 'danang.biz.id',
            'label' => 'Buat marketplace',
        ]);
    }

    public function test_alias_dengan_karakter_aneh_ditolak(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/alamat', [
            'alias' => 'jaha t<script>',
            'domain' => 'danang.biz.id',
        ]);

        $res->assertSessionHasErrors('alias');
        $this->assertSame(0, EmailAlias::count());
    }

    public function test_domain_di_luar_daftar_ditolak(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/alamat', [
            'alias' => 'coba',
            'domain' => 'domainlain.com',
        ]);

        $res->assertSessionHasErrors('domain');
    }

    public function test_label_alamat_bisa_diubah(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/alamat', ['alias' => 'belanja', 'domain' => 'danang.biz.id']);
        $alias = EmailAlias::firstOrFail();

        $this->actingAs($user)->patchJson("/alamat/{$alias->id}/label", ['label' => 'Label baru'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Label baru', $alias->fresh()->label);

        // Kosongkan label
        $this->actingAs($user)->patchJson("/alamat/{$alias->id}/label", ['label' => ''])->assertOk();
        $this->assertNull($alias->fresh()->label);
    }

    public function test_ganti_password_berhasil(): void
    {
        $user = User::factory()->create(['password' => Hash::make('passwordlama1')]);

        $res = $this->actingAs($user)->post('/pengaturan/password', [
            'current_password' => 'passwordlama1',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $res->assertRedirect(route('settings.index'));
        $this->assertTrue(Hash::check('passwordbaru123', $user->fresh()->password));
    }

    public function test_ganti_password_gagal_jika_password_lama_salah(): void
    {
        $user = User::factory()->create(['password' => Hash::make('passwordlama1')]);

        $res = $this->actingAs($user)->post('/pengaturan/password', [
            'current_password' => 'tebakan-salah',
            'password' => 'passwordbaru123',
            'password_confirmation' => 'passwordbaru123',
        ]);

        $res->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('passwordlama1', $user->fresh()->password));
    }

    public function test_halaman_alamat_dan_pengaturan_butuh_login(): void
    {
        $this->get('/alamat')->assertRedirect('/login');
        $this->get('/pengaturan')->assertRedirect('/login');
    }
}
