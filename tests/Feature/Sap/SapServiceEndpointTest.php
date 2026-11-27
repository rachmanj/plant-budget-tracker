<?php

namespace Tests\Feature\Sap;

use App\Services\Sap\SapService;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class SapServiceEndpointTest extends TestCase
{
    public function test_create_purchase_order_posts_to_purchase_orders_endpoint(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory([
            new Response(200, ['Set-Cookie' => 'B1SESSION=test; Path=/'], '{}'),
            new Response(201, [], json_encode(['DocEntry' => 1])),
        ], $history);

        $service->createPurchaseOrder([
            'CardCode' => 'V001',
            'DocumentLines' => [['ItemDescription' => 'Part', 'Quantity' => 1]],
        ]);

        $postRequest = $this->findPostRequest($history);
        $this->assertNotNull($postRequest);
        $path = $postRequest->getUri()->getPath();
        $this->assertStringContainsString('PurchaseOrders', $path);
        $this->assertSame('PurchaseOrders', $this->entitySegmentFromPath($path));
    }

    public function test_create_purchase_request_posts_to_purchase_requests_endpoint(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory([
            new Response(200, ['Set-Cookie' => 'B1SESSION=test; Path=/'], '{}'),
            new Response(201, [], json_encode(['DocEntry' => 2])),
        ], $history);

        $service->createPurchaseRequest([
            'RequriedDate' => '2026-01-01',
            'DocumentLines' => [['ItemDescription' => 'Part', 'Quantity' => 1]],
        ]);

        $postRequest = $this->findPostRequest($history);
        $this->assertNotNull($postRequest);
        $path = $postRequest->getUri()->getPath();
        $this->assertStringContainsString('PurchaseRequests', $path);
        $this->assertSame('PurchaseRequests', $this->entitySegmentFromPath($path));
    }

    public function test_default_client_has_sap_service_layer_base_uri(): void
    {
        $service = new SapService;
        $reflection = new \ReflectionClass($service);
        $clientProp = $reflection->getProperty('client');
        $clientProp->setAccessible(true);
        /** @var Client $client */
        $client = $clientProp->getValue($service);
        $baseUri = (string) $client->getConfig('base_uri');

        $this->assertStringContainsString('/b1s/v1/', $baseUri);
    }

    public function test_create_purchase_order_does_not_post_to_sales_orders_endpoint(): void
    {
        $history = [];
        $service = $this->makeServiceWithHistory([
            new Response(200, ['Set-Cookie' => 'B1SESSION=test; Path=/'], '{}'),
            new Response(201, [], json_encode(['DocEntry' => 3])),
        ], $history);

        $service->createPurchaseOrder(['CardCode' => 'V001', 'DocumentLines' => []]);

        foreach ($history as $transaction) {
            $request = $transaction['request'];
            if (strcasecmp($request->getMethod(), 'POST') !== 0) {
                continue;
            }
            if (str_contains($request->getUri()->getPath(), 'Login')) {
                continue;
            }
            $segment = $this->entitySegmentFromPath($request->getUri()->getPath());
            $this->assertSame('PurchaseOrders', $segment);
            $this->assertNotSame('Orders', $segment);
        }
    }

    private function entitySegmentFromPath(string $path): string
    {
        $trimmed = trim($path, '/');
        $parts = explode('/', $trimmed);

        return (string) end($parts);
    }

    /**
     * @param  array<int, Response>  $responses
     * @param  array<int, array<string, mixed>>  $history
     */
    private function makeServiceWithHistory(array $responses, array &$history): SapService
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $cookieJar = new CookieJar();
        $client = new Client([
            'handler' => $stack,
            'cookies' => $cookieJar,
            'base_uri' => 'https://sap.example.test/b1s/v1/',
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $service = new SapService;
        $service->useClient($client);

        return $service;
    }

    /**
     * @param  array<int, array<string, mixed>>  $history
     */
    private function findPostRequest(array $history): ?\Psr\Http\Message\RequestInterface
    {
        foreach ($history as $transaction) {
            $request = $transaction['request'];
            if (strcasecmp($request->getMethod(), 'POST') === 0
                && ! str_contains($request->getUri()->getPath(), 'Login')) {
                return $request;
            }
        }

        return null;
    }
}