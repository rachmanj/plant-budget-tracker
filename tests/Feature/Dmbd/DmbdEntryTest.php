<?php

namespace Tests\Feature\Dmbd;

use App\Jobs\SyncDmbdStatusToArkfleet;
use App\Models\DmbdEntry;
use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class DmbdEntryTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_upsert_for_today_updates_existing_entry(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $first = DmbdEntry::upsertForToday(101, 'E-101', 'standby', 'Initial note', $planner->id);
        $second = DmbdEntry::upsertForToday(101, 'E-101', 'breakdown', 'Pump failure', $planner->id);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('dmbd_entries', 1);
        $this->assertDatabaseHas('dmbd_entries', [
            'id' => $first->id,
            'equipment_id' => 101,
            'operational_status' => 'breakdown',
            'breakdown_note' => 'Pump failure',
            'synced_to_arkfleet' => false,
        ]);
    }

    public function test_planner_can_store_dmbd_entry_via_http(): void
    {
        Queue::fake();

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn([]);
            $mock->shouldReceive('bust')->once()->with(202);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->post('/dmbd', [
                'equipment_id' => 202,
                'unit_code_cache' => 'E-202',
                'operational_status' => 'rfu',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dmbd_entries', [
            'equipment_id' => 202,
            'unit_code_cache' => 'E-202',
            'operational_status' => 'rfu',
            'reported_by' => $planner->id,
        ]);

        Queue::assertPushed(SyncDmbdStatusToArkfleet::class);
    }

    public function test_http_store_upserts_same_day_entry(): void
    {
        Queue::fake();

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn([]);
            $mock->shouldReceive('bust')->twice()->with(303);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->post('/dmbd', [
                'equipment_id' => 303,
                'unit_code_cache' => 'E-303',
                'operational_status' => 'standby',
            ]);

        $this->actingAsProject($planner)
            ->post('/dmbd', [
                'equipment_id' => 303,
                'unit_code_cache' => 'E-303',
                'operational_status' => 'breakdown',
                'breakdown_note' => 'Hydraulic leak',
            ]);

        $this->assertDatabaseCount('dmbd_entries', 1);
        $this->assertDatabaseHas('dmbd_entries', [
            'equipment_id' => 303,
            'operational_status' => 'breakdown',
            'breakdown_note' => 'Hydraulic leak',
        ]);
    }

    public function test_mechanic_cannot_create_dmbd_entry(): void
    {
        $mechanic = $this->makeUserWithRole('mechanic');

        $this->actingAsProject($mechanic)
            ->post('/dmbd', [
                'equipment_id' => 404,
                'unit_code_cache' => 'E-404',
                'operational_status' => 'rfu',
            ])
            ->assertForbidden();
    }
}
