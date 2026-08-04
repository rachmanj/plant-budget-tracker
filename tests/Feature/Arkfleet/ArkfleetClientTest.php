<?php

namespace Tests\Feature\Arkfleet;

use App\Services\Arkfleet\ArkfleetClient;
use App\Services\Arkfleet\ArkfleetResponseNormalizer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class ArkfleetClientTest extends TestCase
{
    public function test_normalizer_handles_wrapped_data_shape(): void
    {
        $normalizer = new ArkfleetResponseNormalizer();
        $response = new Response(200, [], json_encode([
            'data' => [['id' => 1, 'unit_code' => 'E-001']],
            'meta' => ['total' => 1],
        ]));

        $result = $normalizer->normalize($response);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['meta']['total']);
    }

    public function test_normalizer_handles_raw_paginator_shape(): void
    {
        $normalizer = new ArkfleetResponseNormalizer();
        $response = new Response(200, [], json_encode([
            'current_page' => 1,
            'data' => [['code' => 'MBL', 'name' => 'Mine Site MBL']],
            'last_page' => 1,
            'per_page' => 15,
            'total' => 1,
        ]));

        $result = $normalizer->normalize($response);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertSame('MBL', $result['data'][0]['code']);
        $this->assertSame(1, $result['meta']['current_page']);
    }

    public function test_client_get_projects_normalizes_both_shapes(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'current_page' => 1,
                'data' => [['code' => 'MBL']],
                'last_page' => 1,
                'per_page' => 15,
                'total' => 1,
            ])),
            new Response(200, [], json_encode([
                'data' => ['id' => 1, 'unit_code' => 'E-001'],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $normalizer = new ArkfleetResponseNormalizer();

        $arkfleet = new ArkfleetClient($normalizer);
        $reflection = new \ReflectionClass($arkfleet);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($arkfleet, $client);

        $projects = $arkfleet->getProjects();
        $this->assertSame('MBL', $projects['data'][0]['code']);

        $equipment = $arkfleet->getEquipmentById(1);
        $this->assertSame('E-001', $equipment['data']['unit_code']);
    }
}
