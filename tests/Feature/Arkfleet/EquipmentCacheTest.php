<?php

namespace Tests\Feature\Arkfleet;

use App\Services\Arkfleet\ArkfleetClient;
use App\Services\Arkfleet\EquipmentCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EquipmentCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.arkfleet.base_url' => 'http://192.168.32.15/ark-fleet/api',
            'services.arkfleet.token' => '',
        ]);
    }

    public function test_cache_hit_returns_data_without_stale_flag_on_success(): void
    {
        Cache::flush();

        Http::fake([
            '*/equipments' => Http::sequence()
                ->push(['count' => 1, 'data' => [$this->legacyItem(['project_code' => '021C'])]])
                ->push(['count' => 1, 'data' => [$this->legacyItem(['project_code' => '021C'])]]),
        ]);

        $cache = app(EquipmentCache::class);

        $first = $cache->list(['project_code' => '021C']);
        $this->assertFalse($first['stale']);
        $this->assertCount(1, $first['data']);

        $second = $cache->list(['project_code' => '021C']);
        $this->assertFalse($second['stale']);
        $this->assertCount(1, $second['data']);
    }

    public function test_stale_fallback_when_arkfleet_unreachable(): void
    {
        Cache::flush();

        Http::fake([
            '*/equipments' => Http::response(['count' => 1, 'data' => [$this->legacyItem()]]),
        ]);

        $cache = app(EquipmentCache::class);
        $cache->list(['project_code' => '021C']);

        Http::fake(function () {
            throw new ConnectionException(new \Exception('Connection refused'));
        });

        $stale = $cache->list(['project_code' => '021C']);
        $this->assertTrue($stale['stale']);
        $this->assertSame('AC 001', $stale['data'][0]['unit_code']);
    }

    public function test_empty_fallback_when_arkfleet_unreachable_and_no_cache(): void
    {
        Cache::flush();

        Http::fake(function () {
            throw new ConnectionException(new \Exception('Connection refused'));
        });

        $cache = app(EquipmentCache::class);
        $result = $cache->list(['project_code' => '021C']);

        $this->assertTrue($result['stale']);
        $this->assertSame([], $result['data'] ?? []);
    }

    public function test_project_filter_uses_cached_full_list(): void
    {
        Cache::flush();

        Http::fake([
            '*/equipments' => Http::response([
                'count' => 2,
                'data' => [
                    $this->legacyItem(['project_code' => '021C']),
                    $this->legacyItem(['id' => 2, 'unit_no' => 'AC 002', 'project_code' => '022C']),
                ],
            ]),
        ]);

        $cache = app(EquipmentCache::class);

        $all = $cache->list([]);
        $this->assertCount(2, $all['data']);

        $filtered = $cache->list(['project_code' => '022C']);
        $this->assertCount(1, $filtered['data']);
        $this->assertSame('022C', $filtered['data'][0]['project_code']);
    }

    private function legacyItem(array $overrides = []): array
    {
        return array_merge([
            'id' => 229,
            'unit_no' => 'AC 001',
            'description' => 'Air Compressor Yanmar TF55',
            'project_code' => '021C',
            'plant_type' => 'SUPPORT',
            'unitstatus' => 'ACTIVE',
        ], $overrides);
    }
}
