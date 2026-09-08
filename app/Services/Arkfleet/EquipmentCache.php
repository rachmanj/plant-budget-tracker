<?php

namespace App\Services\Arkfleet;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class EquipmentCache
{
    public function __construct(
        private readonly ArkfleetClient $client,
    ) {}

    public function list(array $filters = []): array
    {
        $key = 'ark:equipment:all';
        $ttl = (int) config('services.arkfleet.cache_ttl.list', 3600);

        $result = $this->remember($key, $ttl, fn () => $this->client->getEquipment([]));

        if ($filters === []) {
            return $result;
        }

        $data = $result['data'] ?? [];
        $filtered = $this->client->applyEquipmentFilters($data, $filters);

        return array_merge($result, [
            'data' => $filtered,
            'meta' => array_merge($result['meta'] ?? [], ['total' => count($filtered)]),
        ]);
    }

    public function find(int $id): array
    {
        $key = "ark:equipment:id:{$id}";
        $ttl = (int) config('services.arkfleet.cache_ttl.detail', 21600);

        return $this->remember($key, $ttl, fn () => $this->client->getEquipmentById($id));
    }

    public function stats(?string $projectCode = null): array
    {
        $code = $projectCode ?? 'all';
        $key = "ark:equipment:stats:{$code}";
        $ttl = (int) config('services.arkfleet.cache_ttl.stats', 1800);

        return $this->remember($key, $ttl, fn () => $this->client->getEquipmentStats($projectCode));
    }

    public function bust(int $id): void
    {
        Cache::forget("ark:equipment:id:{$id}");
        Cache::forget('ark:equipment:all');
    }

    public function bustProject(string $projectCode): void
    {
        $prefix = config('cache.prefix', '');
        $store = Cache::getStore();

        if ($store instanceof \Illuminate\Cache\RedisStore) {
            $pattern = $prefix.'ark:equipment:'.$projectCode.':*';
            $connection = $store->connection();
            $keys = $connection->keys($pattern);
            foreach ($keys as $key) {
                $connection->del($key);
            }
        }

        Cache::forget('ark:equipment:all');
        Cache::forget("ark:equipment:stats:{$projectCode}");
    }

    private function remember(string $key, int $ttl, callable $callback): array
    {
        $cached = Cache::get($key);

        try {
            $fresh = $callback();
            Cache::put($key, $fresh, $ttl);

            return array_merge($fresh, ['stale' => false]);
        } catch (Throwable $e) {
            Log::warning('ARKFLEET unreachable, serving cache if available', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            if ($cached !== null) {
                return array_merge($cached, ['stale' => true]);
            }

            return ['stale' => true, 'data' => []];
        }
    }
}
