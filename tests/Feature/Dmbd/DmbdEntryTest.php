<?php

namespace Tests\Feature\Dmbd;

use App\Jobs\SyncDmbdStatusToArkfleet;
use App\Models\DmbdEntry;
use App\Services\Arkfleet\EquipmentCache;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

    public function test_index_returns_empty_units_when_arkfleet_unreachable(): void
    {
        Cache::flush();
        $this->bindUnreachableArkfleetClient();

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dmbd/Index', false)
                ->where('units.data', [])
                ->where('units.total', 0)
            );
    }

    public function test_index_handles_global_user_with_null_project_scope(): void
    {
        Cache::flush();
        $this->bindUnreachableArkfleetClient();

        $planner = $this->makeUserWithRole('planner', 'MBL');
        $planner->update(['project_code_scope' => null]);

        $this->actingAs($planner)
            ->withoutVite()
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dmbd/Index', false)
                ->where('units.data', [])
            );
    }

    public function test_index_paginates_units_server_side(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => $this->equipmentItems(60)]);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('units.total', 60)
                ->where('units.current_page', 1)
                ->where('units.per_page', 25)
                ->has('units.data', 25)
            );

        $this->actingAsProject($planner)
            ->get('/dmbd?page=3')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('units.current_page', 3)
                ->has('units.data', 10)
            );
    }

    public function test_index_forwards_search_and_project_filters_to_equipment_cache(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(['project_code' => 'MBL', 'search' => 'AC 001'])
                ->andReturn(['data' => [
                    ['id' => 1, 'unit_code' => 'AC 001', 'description' => 'Air Compressor', 'project_code' => 'MBL'],
                ]]);
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/dmbd?search=AC+001')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('units.total', 1)
                ->where('units.data.0.unit_code', 'AC 001')
                ->where('filters.search', 'AC 001')
                ->where('filters.project_code', 'MBL')
            );
    }

    public function test_index_filters_units_by_todays_status_locally(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => $this->equipmentItems(3)]);
        });

        $planner = $this->makeUserWithRole('planner');

        DmbdEntry::factory()->create([
            'equipment_id' => 1,
            'unit_code_cache' => 'E-001',
            'operational_status' => 'breakdown',
            'breakdown_note' => 'Pompa rusak',
            'reported_by' => $planner->id,
        ]);
        DmbdEntry::factory()->create([
            'equipment_id' => 2,
            'unit_code_cache' => 'E-002',
            'operational_status' => 'standby',
            'breakdown_note' => null,
            'reported_by' => $planner->id,
        ]);
        // equipment id 3 has no entry today -> defaults to rfu

        $this->actingAsProject($planner)
            ->get('/dmbd?status=breakdown')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('units.total', 1)
                ->where('units.data.0.id', 1)
                ->where('statusSummary.breakdown', 1)
                ->where('statusSummary.standby', 1)
                ->where('statusSummary.rfu', 1)
            );
    }

    public function test_entries_prop_only_includes_active_page_units(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => $this->equipmentItems(30)]);
        });

        $planner = $this->makeUserWithRole('planner');

        DmbdEntry::factory()->create([
            'equipment_id' => 1,
            'unit_code_cache' => 'E-001',
            'operational_status' => 'standby',
            'reported_by' => $planner->id,
        ]);
        DmbdEntry::factory()->create([
            'equipment_id' => 26,
            'unit_code_cache' => 'E-026',
            'operational_status' => 'breakdown',
            'breakdown_note' => 'Overheat',
            'reported_by' => $planner->id,
        ]);

        $this->actingAsProject($planner)
            ->get('/dmbd?per_page=25&page=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entries.1')
                ->missing('entries.26')
            );

        $this->actingAsProject($planner)
            ->get('/dmbd?per_page=25&page=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('entries.26')
                ->missing('entries.1')
            );
    }

    public function test_store_requires_breakdown_note_when_status_is_breakdown(): void
    {
        Queue::fake();

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('bust');
        });

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->postJson('/dmbd', [
                'equipment_id' => 501,
                'unit_code_cache' => 'E-501',
                'operational_status' => 'breakdown',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('breakdown_note');

        $this->actingAsProject($planner)
            ->post('/dmbd', [
                'equipment_id' => 501,
                'unit_code_cache' => 'E-501',
                'operational_status' => 'breakdown',
                'breakdown_note' => 'Kerusakan pompa hidrolik',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('dmbd_entries', [
            'equipment_id' => 501,
            'operational_status' => 'breakdown',
            'breakdown_note' => 'Kerusakan pompa hidrolik',
        ]);
    }

    public function test_can_update_flag_reflects_planner_role_only(): void
    {
        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')->andReturn(['data' => []]);
        });

        $planner = $this->makeUserWithRole('planner');
        $this->actingAsProject($planner)
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', true));

        $mechanic = $this->makeUserWithRole('mechanic');
        $this->actingAsProject($mechanic)
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('can.update', false));
    }

    private function bindUnreachableArkfleetClient(): void
    {
        Http::fake(function () {
            throw new ConnectionException(new \Exception('Connection refused'));
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function equipmentItems(int $count, string $projectCode = 'MBL'): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'id' => $i,
                'unit_code' => sprintf('E-%03d', $i),
                'description' => 'Unit '.$i,
                'project_code' => $projectCode,
            ];
        }

        return $items;
    }
}
