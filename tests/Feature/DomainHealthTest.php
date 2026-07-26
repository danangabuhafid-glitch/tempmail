<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WebhookLog;
use App\Services\DomainHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Monitoring kesehatan per-domain: deteksi DNS rusak, notifikasi hanya saat
 * status berubah (rusak <-> pulih), dan statistik error per domain.
 */
class DomainHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tempmail.domains' => ['sehat.my.id', 'rusak.biz.id'],
            'tempmail.notify_telegram_token' => 'token-tes',
            'tempmail.notify_telegram_chat_id' => '123',
        ]);
        Http::fake();
    }

    private function service(array $dnsPerDomain): DomainHealthService
    {
        $svc = app(DomainHealthService::class);
        $svc->setResolver(function (string $domain) use ($dnsPerDomain) {
            return $dnsPerDomain[$domain];
        });

        return $svc;
    }

    private const DNS_OK = ['ns' => ['a.ns.cloudflare.com'], 'mx' => ['route1.mx.cloudflare.net']];
    private const DNS_RUSAK = ['ns' => ['ns1.registrar-lain.com'], 'mx' => []];

    public function test_domain_rusak_terdeteksi_dan_dinotifikasi_sekali(): void
    {
        $svc = $this->service(['sehat.my.id' => self::DNS_OK, 'rusak.biz.id' => self::DNS_RUSAK]);

        $svc->runScheduledCheck();

        $health = $svc->storedHealth();
        $this->assertTrue($health['sehat.my.id']['ok']);
        $this->assertFalse($health['rusak.biz.id']['ok']);

        // Notifikasi terkirim untuk domain rusak saja
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req['text'] ?? '', 'rusak.biz.id bermasalah'));

        // Cek berikutnya: masih rusak — TIDAK ada notifikasi ulang (anti-spam)
        $svc->runScheduledCheck();
        Http::assertSentCount(1);
    }

    public function test_domain_pulih_dinotifikasi(): void
    {
        Setting::setValue('domain_health', json_encode([
            'sehat.my.id' => ['ok' => true],
            'rusak.biz.id' => ['ok' => false],
        ]));

        $svc = $this->service(['sehat.my.id' => self::DNS_OK, 'rusak.biz.id' => self::DNS_OK]);
        $svc->runScheduledCheck();

        $this->assertTrue($svc->storedHealth()['rusak.biz.id']['ok']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($req) => str_contains($req['text'] ?? '', 'rusak.biz.id pulih'));
    }

    public function test_lookup_gagal_tidak_mengubah_status_dan_tidak_salah_alarm(): void
    {
        Setting::setValue('domain_health', json_encode([
            'sehat.my.id' => ['ok' => true],
            'rusak.biz.id' => ['ok' => true],
        ]));

        $svc = app(DomainHealthService::class);
        $svc->setResolver(function () {
            throw new \RuntimeException('resolver mati');
        });

        $svc->runScheduledCheck();

        // Status lama dipertahankan; tidak ada notifikasi palsu
        $health = $svc->storedHealth();
        $this->assertTrue($health['sehat.my.id']['ok']);
        $this->assertNotEmpty($health['sehat.my.id']['lookup_error']);
        Http::assertSentCount(0);
    }

    public function test_statistik_error_dihitung_per_domain(): void
    {
        WebhookLog::catat(422, 'Domain tidak dilayani', 'x@rusak.biz.id', null, 100, '1.2.3.4');
        WebhookLog::catat(422, 'Domain tidak dilayani', 'y@rusak.biz.id', null, 100, '1.2.3.4');
        WebhookLog::catat(201, null, 'z@sehat.my.id', null, 100, '1.2.3.4');

        $svc = $this->service(['sehat.my.id' => self::DNS_OK, 'rusak.biz.id' => self::DNS_OK]);
        $stats = $svc->statsPerDomain();

        $this->assertSame(2, $stats['rusak.biz.id']['errors_24h']);
        $this->assertSame(0, $stats['sehat.my.id']['errors_24h']);
    }
}
