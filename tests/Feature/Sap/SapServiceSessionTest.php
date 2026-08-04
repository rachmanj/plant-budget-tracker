<?php

namespace Tests\Feature\Sap;

use App\Services\Sap\SapService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class SapServiceSessionTest extends TestCase
{
    public function test_login_establishes_session_cookies(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'B1SESSION=abc123; Path=/'], '{}'),
        ]);

        $service = $this->makeSapServiceWithMock($mock);

        $this->assertTrue($service->login());
    }

    public function test_ensure_session_logs_in_when_no_cookies(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'B1SESSION=session1; Path=/'], '{}'),
            new Response(200, [], json_encode(['value' => []])),
        ]);

        $service = $this->makeSapServiceWithMock($mock);
        $result = $service->getEntity('Items', ['$top' => 1]);

        $this->assertArrayHasKey('value', $result);
    }

    public function test_request_retries_after_401_with_relogin(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'B1SESSION=expired; Path=/'], '{}'),
            new ClientException(
                'Unauthorized',
                new Request('GET', 'Items'),
                new Response(401, [], 'Unauthorized')
            ),
            new Response(200, ['Set-Cookie' => 'B1SESSION=fresh; Path=/'], '{}'),
            new Response(200, [], json_encode(['DocEntry' => 100])),
        ]);

        $service = $this->makeSapServiceWithMock($mock);
        $result = $service->request('GET', 'Orders(100)');

        $this->assertSame(100, $result['DocEntry']);
    }

    public function test_create_purchase_order_posts_to_orders_endpoint(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Set-Cookie' => 'B1SESSION=abc; Path=/'], '{}'),
            new Response(201, [], json_encode(['DocEntry' => 777])),
        ]);

        $service = $this->makeSapServiceWithMock($mock);
        $result = $service->createPurchaseOrder([
            'CardCode' => 'V001',
            'DocumentLines' => [['ItemDescription' => 'Test', 'Quantity' => 1]],
        ]);

        $this->assertSame(777, $result['DocEntry']);
    }

    private function makeSapServiceWithMock(MockHandler $mock): SapService
    {
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'cookies' => $cookieJar,
        ]);
        $service = new SapService();

        $reflection = new \ReflectionClass($service);
        $clientProp = $reflection->getProperty('client');
        $clientProp->setAccessible(true);
        $clientProp->setValue($service, $client);

        $jarProp = $reflection->getProperty('cookieJar');
        $jarProp->setAccessible(true);
        $jarProp->setValue($service, $cookieJar);

        return $service;
    }
}
