<?php

namespace Tests\Feature\ProjectContext;

use App\Models\ProjectCache;
use App\Models\User;
use App\Services\Arkfleet\EquipmentCache;
use App\Support\ProjectContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class ProjectContextTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_unscoped_user_defaults_to_first_active_project_not_first_row_in_cache(): void
    {
        $this->seedProjectsForDefaultTest();

        $director = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $director->assignRole('president_director');

        $this->actingAs($director)
            ->withoutVite()
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('projectCode', 'MBL'));
    }

    public function test_session_current_project_overrides_active_default(): void
    {
        $this->seedProjectsForDefaultTest();

        $director = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $director->assignRole('president_director');

        $this->actingAs($director)
            ->withoutVite()
            ->withSession(['current_project' => 'SML'])
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('projectCode', 'SML'));
    }

    public function test_scoped_user_cannot_update_project_context(): void
    {
        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'MBL',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => 'SML',
            'project_name' => 'SML',
            'is_active' => true,
        ]);

        $planner = $this->makeUserWithRole('planner', 'MBL');

        $this->actingAsProject($planner)
            ->post('/project-context', ['project_code' => 'SML'])
            ->assertSessionHas('error', 'Your account is bound to a single project and cannot switch project context.');
    }

    public function test_update_rejects_project_code_not_in_cache(): void
    {
        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'MBL',
            'is_active' => true,
        ]);

        $director = $this->makeFinanceDirector();

        $this->actingAs($director)
            ->withoutVite()
            ->post('/project-context', ['project_code' => 'ZZZ'])
            ->assertSessionHasErrors('project_code');
    }

    public function test_dmbd_for_unscoped_user_lists_equipment_for_resolved_project_only(): void
    {
        $this->seedProjectsForDefaultTest();

        $director = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => null,
        ]);
        setPermissionsTeamId('');
        $director->assignRole('president_director');

        $this->mock(EquipmentCache::class, function ($mock) {
            $mock->shouldReceive('list')
                ->once()
                ->with(['project_code' => 'MBL'])
                ->andReturn([
                    'data' => [
                        ['id' => 1, 'unit_code' => 'E-001', 'project_code' => 'MBL'],
                    ],
                ]);
        });

        $this->actingAs($director)
            ->withoutVite()
            ->get('/dmbd')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('projectCode', 'MBL')
                ->has('units.data', 1)
            );
    }

    public function test_allow_switch_is_false_for_scoped_user(): void
    {
        $planner = $this->makeUserWithRole('planner', 'MBL');

        $this->assertFalse(ProjectContext::allowSwitch($planner));
    }

    public function test_allow_switch_is_true_for_unscoped_user(): void
    {
        $director = $this->makeFinanceDirector();

        $this->assertTrue(ProjectContext::allowSwitch($director));
    }

    /**
     * 000H is first alphabetically but inactive; MBL is first active by project_code.
     */
    private function seedProjectsForDefaultTest(): void
    {
        ProjectCache::create([
            'project_code' => '000H',
            'project_name' => 'Legacy Head',
            'is_active' => false,
        ]);
        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'Mine Beta L',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => 'SML',
            'project_name' => 'Site Mine L',
            'is_active' => true,
        ]);
    }
}
