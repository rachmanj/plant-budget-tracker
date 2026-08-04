<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectScopedRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_plant_manager_role_is_scoped_to_assigned_project(): void
    {
        $user = User::factory()->create([
            'project_code_scope' => 'MBL',
            'is_active' => true,
        ]);

        setPermissionsTeamId('MBL');
        $user->assignRole('plant_manager');

        setPermissionsTeamId('MBL');
        $this->assertTrue($user->hasRole('plant_manager'));

        setPermissionsTeamId('SML');
        $this->assertFalse($user->fresh()->hasRole('plant_manager'));
    }

    public function test_project_scope_middleware_sets_team_context(): void
    {
        $user = User::factory()->create([
            'project_code_scope' => 'MBL',
            'is_active' => true,
        ]);

        setPermissionsTeamId('MBL');
        $user->assignRole('plant_manager');

        $this->actingAs($user)
            ->withoutVite()
            ->withSession(['current_project' => 'MBL'])
            ->get('/dashboard')
            ->assertOk();

        setPermissionsTeamId('SML');
        $this->assertFalse($user->fresh()->hasRole('plant_manager'));
    }
}
