<?php

namespace Tests\Feature\Arkfleet;

use App\Models\ProjectCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncArkfleetProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.arkfleet.base_url' => 'http://192.168.32.15/ark-fleet/api',
            'services.arkfleet.active_projects' => ['021C', '025C', 'APS'],
        ]);
    }

    public function test_sync_projects_command_upserts_all_projects_and_sets_active_flags(): void
    {
        ProjectCache::create([
            'project_code' => 'MBL',
            'project_name' => 'Mine Site MBL',
            'is_active' => true,
        ]);

        Http::fake([
            '*/projects' => Http::response([
                'data' => [
                    ['project_code' => '021C', 'bowheer' => 'Coal hauling 021C', 'location' => 'Site A'],
                    ['project_code' => '022C', 'bowheer' => 'Graha Panca Karsa', 'location' => 'Site B'],
                ],
            ]),
        ]);

        $this->artisan('arkfleet:sync-projects')->assertSuccessful();

        $this->assertDatabaseMissing('projects_cache', ['project_code' => 'MBL']);
        $this->assertDatabaseHas('projects_cache', [
            'project_code' => '021C',
            'project_name' => 'Coal hauling 021C',
            'location' => 'Site A',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('projects_cache', [
            'project_code' => '022C',
            'project_name' => 'Graha Panca Karsa',
            'is_active' => false,
        ]);
    }
}
