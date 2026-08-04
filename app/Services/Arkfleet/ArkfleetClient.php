<?php

namespace App\Services\Arkfleet;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class ArkfleetClient
{
    private Client $client;

    public function __construct(
        private readonly ArkfleetResponseNormalizer $normalizer,
    ) {
        $config = config('services.arkfleet');

        $this->client = new Client([
            'base_uri' => rtrim($config['base_url'], '/').'/',
            'timeout' => (float) $config['timeout'],
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.($config['token'] ?? ''),
            ],
        ]);
    }

    public function getEquipment(array $filters = []): array
    {
        return $this->request('GET', 'equipment', ['query' => $filters]);
    }

    public function getEquipmentById(int $id): array
    {
        return $this->request('GET', "equipment/{$id}");
    }

    public function getEquipmentStats(?string $projectCode = null): array
    {
        $query = $projectCode ? ['project_code' => $projectCode] : [];

        return $this->request('GET', 'equipment/stats', ['query' => $query]);
    }

    public function getHmKmReadings(int $equipmentId, array $filters = []): array
    {
        return $this->request('GET', "equipment/{$equipmentId}/hm-km-readings", ['query' => $filters]);
    }

    public function getProjects(array $filters = []): array
    {
        return $this->request('GET', 'projects', ['query' => $filters]);
    }

    public function getProject(string $code): array
    {
        return $this->request('GET', "projects/{$code}");
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

    private function request(string $method, string $uri, array $options = []): array
    {
        $retries = (int) config('services.arkfleet.retries', 2);
        $attempt = 0;

        while (true) {
            try {
                $response = $this->client->request($method, $uri, $options);

                return $this->normalizer->normalize($response);
            } catch (ConnectException|RequestException $e) {
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
}
