<?php

namespace Tests\Feature\Sap;

use App\Jobs\CreateSapPurchaseRequest;
use App\Models\PlantRequest;
use App\Models\PlantRequestLine;
use App\Models\SapSyncLog;
use App\Services\Sap\SapCircuitBreaker;
use App\Services\Sap\SapService;
use Database\Seeders\RoleAndPermissionSeeder;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class CreateSapPurchaseRequestJobTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    /** @var list<array{request: \Psr\Http\Message\RequestInterface, response?: \Psr\Http\Message\ResponseInterface}> */
    private array $httpHistory = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_payload_includes_sap_header_fields_and_item_code_lines(): void
    {
        Http::fake();

        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'approved',
        ]);
        PlantRequestLine::factory()->create([
            'plant_request_id' => $plantRequest->id,
            'part_number' => 'PN-SAP-001',
            'qty' => 3,
            'unit_price_est' => '125000.50',
        ]);

        $sapService = $this->makeSapServiceWithMock(new MockHandler([
            new Response(200, ['Set-Cookie' => 'B1SESSION=test; Path=/'], '{}'),
            new Response(201, [], json_encode(['DocNum' => 9001])),
        ]));

        $job = new CreateSapPurchaseRequest($plantRequest->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $payload = $this->lastPurchaseRequestJsonBody();

        $this->assertArrayHasKey('DocDate', $payload);
        $this->assertArrayHasKey('DocDueDate', $payload);
        $this->assertArrayHasKey('RequriedDate', $payload);
        $this->assertArrayHasKey('Comments', $payload);
        $this->assertStringContainsString("PMB plant request #{$plantRequest->id}", $payload['Comments']);
        $this->assertSame('PN-SAP-001', $payload['DocumentLines'][0]['ItemCode']);
        $this->assertSame(3, $payload['DocumentLines'][0]['Quantity']);

        $log = SapSyncLog::query()
            ->where('correlation_key', "create_pr:plant_request:{$plantRequest->id}")
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('success', $log->status);
        $this->assertSame($payload['DocDate'], $log->request_payload['DocDate']);
        $this->assertSame($payload['RequriedDate'], $log->request_payload['RequriedDate']);
        $this->assertSame($payload['DocumentLines'], $log->request_payload['DocumentLines']);
    }

    public function test_job_skips_sap_when_sync_log_already_success(): void
    {
        $finance = $this->makeFinanceDirector();
        $allocation = $this->makeAllocation($finance);

        $plantRequest = PlantRequest::factory()->create([
            'budget_allocation_id' => $allocation->id,
            'status' => 'pr_created',
            'sap_pr_no' => '10001',
        ]);

        SapSyncLog::create([
            'correlation_key' => "create_pr:plant_request:{$plantRequest->id}",
            'operation' => 'create_pr',
            'ref_type' => 'plant_request',
            'ref_id' => $plantRequest->id,
            'status' => 'success',
            'completed_at' => now(),
        ]);

        $mock = new MockHandler([]);
        $sapService = $this->makeSapServiceWithMock($mock);

        $job = new CreateSapPurchaseRequest($plantRequest->id);
        $job->handle($sapService, app(SapCircuitBreaker::class));

        $this->assertCount(0, $this->httpHistory);
    }

    private function makeSapServiceWithMock(MockHandler $mock): SapService
    {
        $this->httpHistory = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->httpHistory));

        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();
        $client = new Client([
            'handler' => $stack,
            'cookies' => $cookieJar,
            'base_uri' => 'http://sap.test/b1s/v1/',
        ]);

        $service = new SapService();
        $reflection = new \ReflectionClass($service);
        $reflection->getProperty('client')->setValue($service, $client);
        $reflection->getProperty('cookieJar')->setValue($service, $cookieJar);

        return $service;
    }

    /**
     * @return array<string, mixed>
     */
    private function lastPurchaseRequestJsonBody(): array
    {
        $purchaseRequestPost = collect($this->httpHistory)->first(function (array $transaction) {
            $request = $transaction['request'];

            return str_contains((string) $request->getUri(), 'PurchaseRequests')
                && $request->getMethod() === 'POST';
        });

        $this->assertNotNull($purchaseRequestPost, 'Expected POST PurchaseRequests to SAP');

        return json_decode((string) $purchaseRequestPost['request']->getBody(), true);
    }
}
