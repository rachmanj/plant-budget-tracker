<?php

namespace Tests\Feature\Admin;

use App\Models\ProjectCache;
use App\Models\User;
use App\Services\Arkfleet\ArkfleetClient;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectActiveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'services.arkfleet.base_url' => 'http://192.168.32.15/ark-fleet/api',
            'services.arkfleet.active_projects' => ['021C', '025C', 'APS'],
        ]);
    }

    public function test_it_manager_can_toggle_project_active_status(): void
    {
        ProjectCache::create([
            'project_code' => '022C',
            'project_name' => 'Graha Panca Karsa',
            'is_active' => false,
        ]);

        $itManager = $this->makeItManager();

        $this->actingAs($itManager)
            ->patch('/admin/projects/022C', ['is_active' => true])
            ->assertRedirect();

        $this->assertTrue(ProjectCache::where('project_code', '022C')->value('is_active'));

        $this->actingAs($itManager)
            ->patch('/admin/projects/022C', ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse(ProjectCache::where('project_code', '022C')->value('is_active'));
    }

    public function test_planner_without_user_manage_cannot_toggle_project(): void
    {
        ProjectCache::create([
            'project_code' => '022C',
            'project_name' => 'Graha Panca Karsa',
            'is_active' => true,
        ]);

        $planner = User::factory()->create(['is_active' => true, 'project_code_scope' => 'MBL']);
        setPermissionsTeamId('MBL');
        $planner->assignRole('planner');

        $this->actingAs($planner)
            ->patch('/admin/projects/022C', ['is_active' => false])
            ->assertForbidden();
    }

    public function test_sync_preserves_manual_inactive_flag_and_seeds_new_from_config(): void
    {
        ProjectCache::create([
            'project_code' => '021C',
            'project_name' => 'Old name',
            'is_active' => false,
        ]);

        Http::fake([
            '*/projects' => Http::response([
                'data' => [
                    ['project_code' => '021C', 'bowheer' => 'Coal hauling 021C', 'location' => 'Site A'],
                    ['project_code' => '022C', 'bowheer' => 'Graha Panca Karsa', 'location' => 'Site B'],
                ],
            ]),
        ]);

        $itManager = $this->makeItManager();

        $this->actingAs($itManager)->post('/admin/projects/sync')->assertRedirect();

        $this->assertDatabaseHas('projects_cache', [
            'project_code' => '021C',
            'project_name' => 'Coal hauling 021C',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('projects_cache', [
            'project_code' => '022C',
            'project_name' => 'Graha Panca Karsa',
            'is_active' => false,
        ]);
    }

    public function test_active_project_codes_reads_from_database_when_populated(): void
    {
        ProjectCache::create([
            'project_code' => '021C',
            'project_name' => 'Coal hauling 021C',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => '022C',
            'project_name' => 'Graha Panca Karsa',
            'is_active' => true,
        ]);
        ProjectCache::create([
            'project_code' => '999Z',
            'project_name' => 'Inactive',
            'is_active' => false,
        ]);

        $client = app(ArkfleetClient::class);

        $this->assertEqualsCanonicalizing(['021C', '022C'], $client->activeProjectCodes());
    }

    public function test_active_project_codes_falls_back_to_config_when_table_empty(): void
    {
        $client = app(ArkfleetClient::class);

        $this->assertSame(['021C', '025C', 'APS'], $client->activeProjectCodes());
    }

    public function test_projects_index_returns_database_rows_with_active_flags(): void
    {
        ProjectCache::create([
            'project_code' => '021C',
            'project_name' => 'Coal hauling 021C',
            'location' => 'Site A',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        Http::fake([
            '*/projects' => Http::response(['data' => []]),
        ]);

        $itManager = $this->makeItManager();

        $this->actingAs($itManager)
            ->get('/admin/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Projects', false)
                ->has('projects', 1)
                ->where('projects.0.project_code', '021C')
                ->where('projects.0.is_active', true)
                ->where('arkfleetReachable', true)
            );
    }

    private function makeItManager(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        setPermissionsTeamId('');
        $user->assignRole('it_manager');

        return $user;
    }
}
