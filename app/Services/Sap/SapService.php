<?php

namespace App\Services\Sap;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class SapService
{
    private Client $client;

    private CookieJar $cookieJar;

    public function __construct()
    {
        $this->cookieJar = new CookieJar();
        $this->client = new Client([
            'base_uri' => rtrim(config('services.sap.server_url'), '/').'/b1s/v1/',
            'cookies' => $this->cookieJar,
            'headers' => ['Content-Type' => 'application/json'],
            'verify' => config('services.sap.verify_ssl'),
            'timeout' => 15,
        ]);
    }

    public function login(): bool
    {
        try {
            $this->client->post('Login', [
                'json' => [
                    'CompanyDB' => config('services.sap.db_name'),
                    'UserName' => config('services.sap.user'),
                    'Password' => config('services.sap.password'),
                ],
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('SAP login failed', ['message' => $e->getMessage()]);

            return false;
        }
    }

    public function ensureSession(): void
    {
        if (count($this->cookieJar->toArray()) === 0) {
            $this->login();
        }
    }

    public function request(string $method, string $endpoint, array $options = []): array
    {
        $this->ensureSession();

        try {
            $response = $this->client->request($method, ltrim($endpoint, '/'), $options);
            $body = (string) $response->getBody();

            return $body !== '' ? json_decode($body, true) ?? [] : [];
        } catch (ClientException $e) {
            if ($e->getResponse()?->getStatusCode() === 401) {
                $this->login();

                $response = $this->client->request($method, ltrim($endpoint, '/'), $options);
                $body = (string) $response->getBody();

                return $body !== '' ? json_decode($body, true) ?? [] : [];
            }

            throw $e;
        }
    }

    public function getEntity(string $entity, array $query = []): array
    {
        return $this->request('GET', $entity, ['query' => $query]);
    }

    public function createPurchaseRequest(array $payload): array
    {
        return $this->request('POST', 'PurchaseRequests', ['json' => $payload]);
    }

    public function createPurchaseOrder(array $payload): array
    {
        return $this->request('POST', 'Orders', ['json' => $payload]);
    }

    public function getVendorMaster(string $cardCode): array
    {
        return $this->request('GET', "BusinessPartners('{$cardCode}')");
    }

    public function getPriceList(string $itemCode): array
    {
        return $this->getEntity('Items', [
            '$filter' => "ItemCode eq '{$itemCode}'",
            '$select' => 'ItemCode,ItemPrices',
        ]);
    }

    public function getPurchaseOrderStatus(string $docEntry): array
    {
        return $this->request('GET', "Orders({$docEntry})");
    }

    public function getGrpoByPoRef(string $poDocEntry): array
    {
        return $this->getEntity('PurchaseDeliveryNotes', [
            '$filter' => "BaseEntry eq {$poDocEntry}",
        ]);
    }

    public function syncInterchangeMapping(array $payload): array
    {
        return $this->request('POST', 'Items', ['json' => $payload]);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }
}
