<?php

namespace Tests\Feature\Component;

use App\Models\Component;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ComponentHierarchyTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        config(['features.cannibal_beta' => true]);
    }

    public function test_aml_manager_can_create_parent_and_child_components(): void
    {
        $amlMgr = $this->makeUserWithRole('aml_manager');

        $this->actingAsProject($amlMgr)
            ->post('/components', [
                'level' => 'housing',
                'equipment_id' => 501,
                'component_code' => 'HSG-501',
                'description' => 'Main housing',
            ])
            ->assertRedirect();

        $parent = Component::query()->where('component_code', 'HSG-501')->first();
        $this->assertNotNull($parent);
        $this->assertNull($parent->parent_id);

        $this->actingAsProject($amlMgr)
            ->post('/components', [
                'parent_id' => $parent->id,
                'level' => 'inner',
                'equipment_id' => 501,
                'component_code' => 'INN-501-A',
                'description' => 'Inner assembly',
            ])
            ->assertRedirect();

        $child = Component::query()->where('component_code', 'INN-501-A')->first();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertCount(1, $parent->fresh()->children);
    }

    public function test_critical_component_cannot_have_critical_child(): void
    {
        $amlMgr = $this->makeUserWithRole('aml_manager');

        $criticalParent = Component::create([
            'level' => 'critical',
            'equipment_id' => 502,
            'component_code' => 'CRT-502',
            'description' => 'Critical part',
            'maintained_by' => $amlMgr->id,
        ]);

        $this->actingAsProject($amlMgr)
            ->post('/components', [
                'parent_id' => $criticalParent->id,
                'level' => 'critical',
                'equipment_id' => 502,
                'component_code' => 'CRT-502-B',
                'description' => 'Invalid child',
            ])
            ->assertSessionHasErrors('level');
    }

    public function test_aml_manager_can_update_component_status(): void
    {
        $amlMgr = $this->makeUserWithRole('aml_manager');

        $component = Component::create([
            'level' => 'housing',
            'equipment_id' => 503,
            'component_code' => 'HSG-503',
            'description' => 'Housing unit',
            'maintained_by' => $amlMgr->id,
            'status' => 'installed',
        ]);

        $this->actingAsProject($amlMgr)
            ->patch("/components/{$component->id}/status", ['status' => 'removed'])
            ->assertRedirect();

        $this->assertSame('removed', $component->fresh()->status);
    }

    public function test_planner_cannot_register_components(): void
    {
        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->post('/components', [
                'level' => 'housing',
                'equipment_id' => 504,
                'component_code' => 'HSG-504',
                'description' => 'Should fail',
            ])
            ->assertForbidden();
    }
}
