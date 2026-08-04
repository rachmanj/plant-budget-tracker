<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_it_manager_can_assign_roles(): void
    {
        $itManager = User::factory()->create(['is_active' => true]);
        setPermissionsTeamId('');
        $itManager->assignRole('it_manager');

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($itManager)->post("/admin/users/{$target->id}/roles", [
            'roles' => [
                ['name' => 'planner', 'project_code' => 'MBL'],
            ],
        ]);

        $response->assertRedirect();

        setPermissionsTeamId('MBL');
        $this->assertTrue($target->fresh()->hasRole('planner'));
    }

    public function test_non_it_manager_cannot_assign_roles(): void
    {
        $planner = User::factory()->create(['is_active' => true]);
        setPermissionsTeamId('MBL');
        $planner->assignRole('planner');

        $target = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($planner)->post("/admin/users/{$target->id}/roles", [
            'roles' => [
                ['name' => 'planner', 'project_code' => 'MBL'],
            ],
        ]);

        $response->assertForbidden();
    }
}
