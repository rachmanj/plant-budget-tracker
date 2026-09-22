<?php

namespace App\Services\Arkfleet;

use App\Models\ProjectCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArkfleetClient
{
    public function __construct(
        private readonly ArkfleetResponseNormalizer $normalizer,
    ) {}

    public function getEquipment(array $filters = []): array
    {
        $items = $this->fetchEquipmentList();
        $filtered = $this->applyEquipmentFilters($items, $filters);

        return [
            'data' => $filtered,
            'meta' => [
                'total' => count($filtered),
                'count' => count($items),
            ],
        ];
    }

    public function getEquipmentById(int $id): array
    {
        foreach ($this->fetchEquipmentList() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return ['data' => $item, 'meta' => null];
            }
        }

        return ['data' => [], 'meta' => null];
    }

    public function getEquipmentStats(?string $projectCode = null): array
    {
        $filters = $projectCode !== null ? ['project_code' => $projectCode] : [];
        $items = $this->applyEquipmentFilters($this->fetchEquipmentList(), $filters);

        $byStatus = [];
        $byPlantType = [];

        foreach ($items as $item) {
            $status = (string) ($item['unitstatus'] ?? 'UNKNOWN');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            $plantType = (string) ($item['plant_type'] ?? 'UNKNOWN');
            $byPlantType[$plantType] = ($byPlantType[$plantType] ?? 0) + 1;
        }

        return [
            'data' => [
                'total' => count($items),
                'by_status' => $byStatus,
                'by_plant_type' => $byPlantType,
            ],
            'meta' => null,
        ];
    }

    public function getHmKmReadings(int $equipmentId, array $filters = []): array
    {
        return $this->request('GET', "equipment/{$equipmentId}/hm-km-readings", ['query' => $filters]);
    }

    public function getProjects(array $filters = []): array
    {
        $body = $this->request('GET', 'projects');
        $mapped = array_map(fn (array $item) => $this->mapProjectItem($item), $body['data'] ?? []);

        if (! empty($filters['active_only'])) {
            $mapped = array_values(array_filter($mapped, fn (array $project) => $project['is_active']));
        }

        return ['data' => $mapped, 'meta' => null];
    }

    public function getProject(string $code): array
    {
        foreach ($this->getProjects()['data'] ?? [] as $project) {
            if (($project['project_code'] ?? $project['code'] ?? '') === $code) {
                return $project;
            }
        }

        return [];
    }

    public function getPlantTypes(): array
    {
        return $this->request('GET', 'plant-types');
    }

    public function getUnitStatuses(): array
    {
        return $this->request('GET', 'unit-statuses');
    }

    public function getAssetCategories(): array
    {
        return $this->request('GET', 'asset-categories');
    }

    public function patchEquipmentStatus(int $equipmentId, string $status): array
    {
        return $this->request('PATCH', "equipment/{$equipmentId}/status", [
            'json' => ['status' => $status],
        ]);
    }

    public function patchComponentStatus(int $equipmentId, int $componentId, string $status): array
    {
        return $this->request('PATCH', "equipment/{$equipmentId}/components/{$componentId}/status", [
            'json' => ['status' => $status],
        ]);
    }

    public function applyEquipmentFilters(array $items, array $filters): array
    {
        if (isset($filters['project_code'])) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => ($item['project_code'] ?? '') === $filters['project_code']
            ));
        }

        if (isset($filters['unitstatus'])) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => ($item['unitstatus'] ?? '') === $filters['unitstatus']
            ));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active']) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => ($item['is_active'] ?? false) === true
            ));
        }

        if (isset($filters['plant_type'])) {
            $items = array_values(array_filter(
                $items,
                fn (array $item) => ($item['plant_type'] ?? '') === $filters['plant_type']
            ));
        }

        return $items;
    }

    private function fetchEquipmentList(): array
    {
        $body = $this->request('GET', 'equipments');

        return array_map(
            fn (array $item) => $this->mapEquipmentItem($item),
            $body['data'] ?? []
        );
    }

    private function mapEquipmentItem(array $item): array
    {
        return array_merge($item, [
            'unit_code' => $item['unit_no'] ?? $item['unit_code'] ?? null,
            'is_active' => ($item['unitstatus'] ?? '') === 'ACTIVE',
            'is_rfu' => false,
        ]);
    }

    private function mapProjectItem(array $item): array
    {
        $code = $item['project_code'] ?? $item['code'] ?? '';
        $name = $item['bowheer'] ?? $item['name'] ?? $item['project_name'] ?? $code;

        return array_merge($item, [
            'project_code' => $code,
            'code' => $code,
            'name' => $name,
            'project_name' => $name,
            'location' => $item['location'] ?? null,
            'is_active' => in_array($code, $this->activeProjectCodes(), true),
        ]);
    }

    public function activeProjectCodes(): array
    {
        try {
            $codes = ProjectCache::query()
                ->where('is_active', true)
                ->pluck('project_code')
                ->all();

            if ($codes !== []) {
                return $codes;
            }
        } catch (\Throwable) {
            // fall back to config when cache table is unavailable
        }

        return config('services.arkfleet.active_projects', []);
    }

    private function httpClient(): PendingRequest
    {
        $config = config('services.arkfleet');
        $client = Http::baseUrl(rtrim((string) $config['base_url'], '/').'/')
            ->acceptJson()
            ->timeout((float) $config['timeout']);

        $token = $config['token'] ?? null;
        if (is_string($token) && $token !== '') {
            $client = $client->withToken($token);
        }

        return $client;
    }

    private function request(string $method, string $uri, array $options = []): array
    {
        $retries = (int) config('services.arkfleet.retries', 2);
        $attempt = 0;

        while (true) {
            try {
                $response = $this->sendRequest($method, $uri, $options);

                return $this->normalizer->normalize($response);
            } catch (ConnectionException|RequestException $e) {
                $attempt++;

                Log::warning('ARKFLEET API request failed', [
                    'uri' => $uri,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                ]);

                if ($attempt > $retries) {
                    throw $e;
                }
            }
        }
    }

    private function sendRequest(string $method, string $uri, array $options = []): \Illuminate\Http\Client\Response
    {
        $client = $this->httpClient();
        $query = $options['query'] ?? [];
        $json = $options['json'] ?? null;

        $response = match (strtoupper($method)) {
            'GET' => $client->get($uri, $query),
            'PATCH' => $client->patch($uri, $json ?? []),
            'POST' => $client->post($uri, $json ?? []),
            'PUT' => $client->put($uri, $json ?? []),
            'DELETE' => $client->delete($uri, $query),
            default => throw new \InvalidArgumentException("Unsupported HTTP method [{$method}]"),
        };

        $response->throw();

        return $response;
    }
}
