<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\Storage::fake('local');

        config([
            'tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id'],
            'tempmail.webhook_token' => 'token-rahasia-test',
        ]);
    }

    public function test_halaman_setup_butuh_login_dan_bisa_dirender(): void
    {
        $this->get('/setup')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)
            ->get('/setup')
            ->assertOk()
            ->assertSee('Setup Penerimaan Email')
            ->assertSee('WEBHOOK_URL');
    }

    public function test_tombol_email_uji_membuat_email_lewat_jalur_ingest(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/setup/test-email');

        $res->assertOk()->assertJsonStructure(['success', 'message', 'redirect']);
        $this->assertSame(1, Email::count());
        $this->assertSame('danangabuhafid.my.id', Email::first()->domain);
    }

    public function test_regenerate_token_menyimpan_token_baru_di_database(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/setup/token');

        $res->assertOk()->assertJson(['success' => true, 'reload' => true]);
        $this->assertNotNull(Setting::getValue('webhook_token'));
        $this->assertSame(48, strlen(Setting::getValue('webhook_token')));
    }

    public function test_masa_retensi_tersimpan_via_ajax(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/pengaturan/umum', ['retention_days' => 60]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame('60', Setting::getValue('retention_days'));
    }

    public function test_tambah_dan_hapus_domain_via_list(): void
    {
        $user = User::factory()->create();

        // Tambah domain baru
        $this->actingAs($user)->postJson('/pengaturan/domain', ['domain' => 'Baru.my.id'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('baru.my.id', Setting::getValue('domains'));

        // Duplikat ditolak
        config(['tempmail.domains' => ['danangabuhafid.my.id', 'danang.biz.id', 'baru.my.id']]);
        $this->actingAs($user)->postJson('/pengaturan/domain', ['domain' => 'baru.my.id'])
            ->assertStatus(422);

        // Format aneh ditolak sebagai JSON 422 (bukan redirect HTML)
        $this->actingAs($user)->postJson('/pengaturan/domain', ['domain' => 'bukan domain!'])
            ->assertStatus(422);

        // Hapus domain
        $this->actingAs($user)->deleteJson('/pengaturan/domain', ['domain' => 'baru.my.id'])
            ->assertOk()->assertJson(['success' => true]);
        $this->assertStringNotContainsString('baru.my.id', Setting::getValue('domains'));
    }

    public function test_domain_terakhir_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        config(['tempmail.domains' => ['danang.biz.id']]);

        $this->actingAs($user)->deleteJson('/pengaturan/domain', ['domain' => 'danang.biz.id'])
            ->assertStatus(422);
    }

    public function test_login_via_ajax_mengembalikan_redirect(): void
    {
        $user = User::factory()->create(['password' => bcrypt('rahasia-123')]);

        $gagal = $this->postJson('/login', ['email' => $user->email, 'password' => 'salah']);
        $gagal->assertStatus(422)->assertJson(['success' => false]);

        $sukses = $this->postJson('/login', ['email' => $user->email, 'password' => 'rahasia-123']);
        $sukses->assertOk()->assertJson(['success' => true])->assertJsonStructure(['redirect']);
    }

    public function test_tambah_alias_via_ajax(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/alamat', [
            'alias' => 'belanja',
            'domain' => 'danang.biz.id',
        ]);

        $res->assertOk()->assertJson(['success' => true]);

        // Duplikat → error JSON 422
        $this->actingAs($user)->postJson('/alamat', [
            'alias' => 'belanja',
            'domain' => 'danang.biz.id',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_buat_alamat_massal_dengan_prefix(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/alamat', [
            'mode' => 'prefix',
            'prefix' => 'belanja',
            'quantity' => 5,
            'domain' => 'danang.biz.id',
            'label' => 'uji massal',
        ]);

        $res->assertOk()->assertJson(['success' => true])->assertJsonStructure(['popup_html']);
        $this->assertSame(5, \App\Models\EmailAlias::count());
        $this->assertSame(5, \App\Models\EmailAlias::where('alias', 'like', 'belanja-%')->count());
    }

    public function test_buat_alamat_massal_otomatis(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/alamat', [
            'mode' => 'otomatis',
            'quantity' => 3,
            'domain' => 'danangabuhafid.my.id',
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame(3, \App\Models\EmailAlias::where('domain', 'danangabuhafid.my.id')->count());
    }

    public function test_quantity_di_atas_50_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/alamat', [
            'mode' => 'otomatis',
            'quantity' => 51,
            'domain' => 'danang.biz.id',
        ])->assertStatus(422);

        $this->assertSame(0, \App\Models\EmailAlias::count());
    }

    public function test_daftar_inbox_bisa_diminta_sebagai_partial_ajax(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get('/', ['X-Requested-With' => 'XMLHttpRequest']);

        $res->assertOk()->assertSee('latest-marker', false);
        // Partial tidak berisi kerangka halaman penuh
        $res->assertDontSee('<nav', false);
    }
}
