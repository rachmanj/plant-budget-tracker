<?php

namespace Tests\Feature\Cannibal;

use App\Models\CannibalRequest;
use App\Models\DmbdEntry;
use App\Models\RequestApproval;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class CannibalApprovalTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['features.cannibal_beta' => true]);
    }

    public function test_cannibal_request_runs_full_approval_chain(): void
    {
        $planner = $this->makeUserWithRole('planner');
        $plantMgr = $this->makeUserWithRole('plant_manager');
        $amlMgr = $this->makeUserWithRole('aml_manager');
        $opsDir = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $opsDir->assignRole('operation_director');
        $presDir = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $presDir->assignRole('president_director');

        $dmbd = DmbdEntry::factory()->create([
            'equipment_id' => 601,
            'unit_code_cache' => 'E-601',
            'operational_status' => 'breakdown',
            'reported_by' => $planner->id,
        ]);

        $this->actingAsProject($planner)
            ->post('/cannibal-requests', [
                'source_equipment_id' => 601,
                'target_equipment_id' => 602,
                'dmbd_entry_id' => $dmbd->id,
                'reason' => 'Urgent cannibal for breakdown unit',
            ])
            ->assertRedirect(route('cannibal-requests.index'));

        $cannibal = CannibalRequest::query()->first();
        $this->assertSame('pending_plant_mgr', $cannibal->status);

        $chain = [
            ['user' => $plantMgr, 'role' => 'plant_manager', 'next' => 'pending_aml_mgr'],
            ['user' => $amlMgr, 'role' => 'aml_manager', 'next' => 'pending_ops_dir'],
            ['user' => $opsDir, 'role' => 'operation_director', 'next' => 'pending_presdir'],
            ['user' => $presDir, 'role' => 'president_director', 'next' => 'approved'],
        ];

        foreach ($chain as $step) {
            $approval = RequestApproval::query()
                ->where('approvable_id', $cannibal->id)
                ->where('required_role', $step['role'])
                ->where('decision', 'pending')
                ->first();

            $teamId = $step['user']->project_code_scope ?? '';
            setPermissionsTeamId($teamId);

            $this->actingAs($step['user'])
                ->withoutVite()
                ->withSession(['current_project' => $teamId ?: 'MBL'])
                ->post("/approvals/{$approval->id}/decide", ['decision' => 'approved'])
                ->assertRedirect();

            $cannibal->refresh();
            $this->assertSame($step['next'], $cannibal->status);
        }
    }

    public function test_cannibal_requires_breakdown_dmbd_entry(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $dmbd = DmbdEntry::factory()->create([
            'equipment_id' => 701,
            'operational_status' => 'rfu',
            'reported_by' => $planner->id,
        ]);

        $this->actingAsProject($planner)
            ->post('/cannibal-requests', [
                'source_equipment_id' => 701,
                'target_equipment_id' => 702,
                'dmbd_entry_id' => $dmbd->id,
                'reason' => 'Invalid status',
            ])
            ->assertSessionHasErrors('dmbd_entry_id');

        $this->assertDatabaseCount('cannibal_requests', 0);
    }
}
