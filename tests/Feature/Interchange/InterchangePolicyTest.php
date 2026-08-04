<?php

namespace Tests\Feature\Interchange;

use App\Jobs\SyncInterchangeToSap;
use App\Models\InterchangeMap;
use App\Models\User;
use App\Policies\InterchangeMapPolicy;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class InterchangePolicyTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_buyer_can_create_interchange_mapping(): void
    {
        $buyer = $this->makeUserWithRole('buyer');

        $this->actingAsProject($buyer)
            ->post('/interchange', [
                'genuine_part_number' => 'GEN-001',
                'oem_part_number' => 'OEM-001',
                'material_name' => 'Filter Element',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('interchange_maps', [
            'genuine_part_number' => 'GEN-001',
            'oem_part_number' => 'OEM-001',
            'created_by' => $buyer->id,
        ]);
    }

    public function test_planner_cannot_create_interchange_mapping(): void
    {
        $planner = $this->makeUserWithRole('planner');
        setPermissionsTeamId('MBL');

        $this->assertFalse((new InterchangeMapPolicy())->create($planner));
        $this->assertFalse(Gate::forUser($planner)->allows('create', InterchangeMap::class));

        $this->actingAsProject($planner)
            ->post('/interchange', [
                'genuine_part_number' => 'GEN-002',
                'oem_part_number' => 'OEM-002',
                'material_name' => 'Seal',
            ])
            ->assertForbidden();
    }

    public function test_plant_manager_can_signoff_but_not_own_mapping(): void
    {
        Queue::fake();

        $buyer = $this->makeUserWithRole('buyer');
        $plantMgr = $this->makeUserWithRole('plant_manager');

        $map = InterchangeMap::factory()->create([
            'created_by' => $buyer->id,
            'genuine_part_number' => 'GEN-003',
            'oem_part_number' => 'OEM-003',
        ]);

        $policy = new InterchangeMapPolicy();
        setPermissionsTeamId('MBL');
        $this->assertTrue($policy->signoff($plantMgr, $map));
        setPermissionsTeamId('MBL');
        $this->assertFalse($policy->signoff($buyer, $map));

        $this->actingAsProject($plantMgr)
            ->post("/interchange/{$map->id}/signoff")
            ->assertRedirect();

        $map->refresh();
        $this->assertSame($plantMgr->id, $map->technical_signoff_by);
        $this->assertNotNull($map->technical_signoff_at);

        Queue::assertPushed(SyncInterchangeToSap::class);
    }

    public function test_aml_manager_can_signoff_interchange(): void
    {
        $buyer = $this->makeUserWithRole('buyer');
        $amlMgr = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => 'MBL',
        ]);
        setPermissionsTeamId('MBL');
        $amlMgr->assignRole('aml_manager');

        $map = InterchangeMap::factory()->create(['created_by' => $buyer->id]);

        setPermissionsTeamId('MBL');
        $this->assertTrue((new InterchangeMapPolicy())->signoff($amlMgr, $map));
    }
}
