<?php

namespace Tests\Feature\Arkfleet;

use App\Services\Arkfleet\ArkfleetClient;
use App\Services\Arkfleet\ArkfleetResponseNormalizer;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArkfleetClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.arkfleet.base_url' => 'http://192.168.32.15/ark-fleet/api',
            'services.arkfleet.token' => '',
            'services.arkfleet.active_projects' => ['021C', '025C', 'APS'],
        ]);
    }

    public function test_normalizer_handles_wrapped_data_shape(): void
    {
        $normalizer = new ArkfleetResponseNormalizer();
        $response = new HttpResponse(new PsrResponse(200, [], json_encode([
            'data' => [['id' => 1, 'unit_no' => 'AC 001']],
            'count' => 1,
        ])));

        $result = $normalizer->normalize($response);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function test_normalizer_handles_raw_paginator_shape(): void
    {
        $normalizer = new ArkfleetResponseNormalizer();
        $response = new HttpResponse(new PsrResponse(200, [], json_encode([
            'current_page' => 1,
            'data' => [['project_code' => '021C', 'bowheer' => 'Coal hauling 021C']],
            'last_page' => 1,
            'per_page' => 15,
            'total' => 1,
        ])));

        $result = $normalizer->normalize($response);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertSame('021C', $result['data'][0]['project_code']);
        $this->assertSame(1, $result['meta']['current_page']);
    }

    public function test_get_equipment_maps_unit_no_to_unit_code(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 1,
                'data' => [$this->legacyEquipmentItem()],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $result = $client->getEquipment();

        $this->assertSame('AC 001', $result['data'][0]['unit_code']);
        $this->assertSame('AC 001', $result['data'][0]['unit_no']);
        $this->assertTrue($result['data'][0]['is_active']);
        $this->assertFalse($result['data'][0]['is_rfu']);
    }

    public function test_get_equipment_filters_by_project_code_client_side(): void
    {
        $items = array_merge(
            array_map(
                fn (int $id) => $this->legacyEquipmentItem(['id' => $id, 'project_code' => '022C']),
                range(1, 195)
            ),
            [$this->legacyEquipmentItem(['id' => 999, 'unit_no' => 'OT 001', 'project_code' => '008C'])]
        );

        Http::fake([
            '*/equipments' => Http::response(['count' => count($items), 'data' => $items]),
        ]);

        $client = app(ArkfleetClient::class);
        $result = $client->getEquipment(['project_code' => '022C']);

        $this->assertCount(195, $result['data']);
        $this->assertSame('022C', $result['data'][0]['project_code']);
    }

    public function test_get_equipment_filters_by_unitstatus_client_side(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 2,
                'data' => [
                    $this->legacyEquipmentItem(['unitstatus' => 'ACTIVE']),
                    $this->legacyEquipmentItem(['id' => 2, 'unit_no' => 'AC 002', 'unitstatus' => 'IN-ACTIVE']),
                ],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $result = $client->getEquipment(['unitstatus' => 'ACTIVE']);

        $this->assertCount(1, $result['data']);
        $this->assertSame('ACTIVE', $result['data'][0]['unitstatus']);
    }

    public function test_get_equipment_by_id_finds_item_in_full_list(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 2,
                'data' => [
                    $this->legacyEquipmentItem(['id' => 229]),
                    $this->legacyEquipmentItem(['id' => 230, 'unit_no' => 'AC 002']),
                ],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $equipment = $client->getEquipmentById(229);

        $this->assertSame('AC 001', $equipment['data']['unit_code']);
        $this->assertSame(229, $equipment['data']['id']);
    }

    public function test_get_equipment_by_id_returns_empty_when_not_found(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 1,
                'data' => [$this->legacyEquipmentItem()],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $equipment = $client->getEquipmentById(9999);

        $this->assertSame([], $equipment['data']);
    }

    public function test_get_projects_returns_mapped_data(): void
    {
        Http::fake([
            '*/projects' => Http::response([
                'data' => [
                    ['project_code' => '021C', 'bowheer' => 'Coal hauling 021C', 'location' => 'Site A'],
                    ['project_code' => '022C', 'bowheer' => 'Graha Panca Karsa', 'location' => 'Site B'],
                ],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $projects = $client->getProjects();

        $this->assertCount(2, $projects['data']);
        $this->assertSame('021C', $projects['data'][0]['project_code']);
        $this->assertSame('Coal hauling 021C', $projects['data'][0]['name']);
        $this->assertTrue($projects['data'][0]['is_active']);
        $this->assertFalse($projects['data'][1]['is_active']);
    }

    public function test_get_equipment_stats_computed_from_list(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 3,
                'data' => [
                    $this->legacyEquipmentItem(['unitstatus' => 'ACTIVE', 'plant_type' => 'SUPPORT']),
                    $this->legacyEquipmentItem(['id' => 2, 'unit_no' => 'AC 002', 'unitstatus' => 'ACTIVE', 'plant_type' => 'DIGGER']),
                    $this->legacyEquipmentItem(['id' => 3, 'unit_no' => 'AC 003', 'unitstatus' => 'IN-ACTIVE', 'plant_type' => 'SUPPORT']),
                ],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $stats = $client->getEquipmentStats();

        $this->assertSame(3, $stats['data']['total']);
        $this->assertSame(2, $stats['data']['by_status']['ACTIVE']);
        $this->assertSame(1, $stats['data']['by_status']['IN-ACTIVE']);
        $this->assertSame(2, $stats['data']['by_plant_type']['SUPPORT']);
        $this->assertSame(1, $stats['data']['by_plant_type']['DIGGER']);
    }

    public function test_get_equipment_stats_filters_by_project_code(): void
    {
        Http::fake([
            '*/equipments' => Http::response([
                'count' => 2,
                'data' => [
                    $this->legacyEquipmentItem(['project_code' => '022C']),
                    $this->legacyEquipmentItem(['id' => 2, 'unit_no' => 'AC 002', 'project_code' => '008C']),
                ],
            ]),
        ]);

        $client = app(ArkfleetClient::class);
        $stats = $client->getEquipmentStats('022C');

        $this->assertSame(1, $stats['data']['total']);
    }

    public function test_authorization_header_omitted_when_token_empty(): void
    {
        Http::fake([
            '*/equipments' => Http::response(['count' => 0, 'data' => []]),
        ]);

        app(ArkfleetClient::class)->getEquipment();

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('Authorization');
        });
    }

    public function test_authorization_header_included_when_token_set(): void
    {
        config(['services.arkfleet.token' => 'secret-token']);

        Http::fake([
            '*/equipments' => Http::response(['count' => 0, 'data' => []]),
        ]);

        app(ArkfleetClient::class)->getEquipment();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer secret-token');
        });
    }

    private function legacyEquipmentItem(array $overrides = []): array
    {
        return array_merge([
            'id' => 229,
            'unit_no' => 'AC 001',
            'description' => 'Air Compressor Yanmar TF55',
            'active_date' => '2004-08-17',
            'nomor_polisi' => null,
            'serial_no' => 'SN-12345',
            'chasis_no' => null,
            'engine_model' => null,
            'machine_no' => null,
            'bahan_bakar' => null,
            'warna' => null,
            'capacity' => null,
            'remarks' => null,
            'project_code' => '008C',
            'project_id' => 3,
            'plant_group' => 'Compressor',
            'plant_group_id' => 10,
            'model' => 'TF55',
            'model_id' => 271,
            'manufacture' => 'Yanmar',
            'unitstatus' => 'ACTIVE',
            'unitstatus_id' => 1,
            'asset_category' => 'Mayor',
            'asset_category_id' => 1,
            'plant_type' => 'SUPPORT',
            'plant_type_id' => 3,
        ], $overrides);
    }
}
