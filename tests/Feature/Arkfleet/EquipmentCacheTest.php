<?php

namespace Tests\Feature\Arkfleet;

use App\Services\Arkfleet\ArkfleetClient;
use App\Services\Arkfleet\ArkfleetResponseNormalizer;
use App\Services\Arkfleet\EquipmentCache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EquipmentCacheTest extends TestCase
{
    public function test_cache_hit_returns_data_without_stale_flag_on_success(): void
    {
        Cache::flush();

        $mock = new MockHandler([
            new Response(200, [], json_encode(['data' => [['id' => 1]]])),
            new Response(200, [], json_encode(['data' => [['id' => 1]]])),
        ]);

        $client = $this->makeClient($mock);
        $cache = new EquipmentCache($client);

        $first = $cache->list(['project_code' => 'MBL']);
        $this->assertFalse($first['stale']);
        $this->assertCount(1, $first['data']);

        $second = $cache->list(['project_code' => 'MBL']);
        $this->assertFalse($second['stale']);
    }

    public function test_stale_fallback_when_arkfleet_unreachable(): void
    {
        Cache::flush();

        $success = new Response(200, [], json_encode(['data' => [['id' => 1, 'unit_code' => 'E-001']]]));
        $failure = new ConnectException('Connection refused', new Request('GET', 'equipment'));

        $mock = new MockHandler([$success, $failure]);
        $client = $this->makeClient($mock);
        $cache = new EquipmentCache($client);

        $cache->list(['project_code' => 'MBL']);

        $stale = $cache->list(['project_code' => 'MBL']);
        $this->assertTrue($stale['stale']);
        $this->assertSame('E-001', $stale['data'][0]['unit_code']);
    }

    private function makeClient(MockHandler $mock): ArkfleetClient
    {
        $http = new Client(['handler' => HandlerStack::create($mock)]);
        $arkfleet = new ArkfleetClient(new ArkfleetResponseNormalizer());
        $reflection = new \ReflectionClass($arkfleet);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($arkfleet, $http);

        return $arkfleet;
    }
}
