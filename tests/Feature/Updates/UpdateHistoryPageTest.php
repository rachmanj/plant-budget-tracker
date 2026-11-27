<?php

namespace Tests\Feature\Updates;

use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class UpdateHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/updates')->assertRedirect(route('login'));
    }

    public function test_authenticated_user_receives_update_history_page(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/updates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Updates/Index', false));
    }

    public function test_page_props_include_note_updates_and_counts_with_correct_shape(): void
    {
        $today = Carbon::now()->toDateString();
        $path = $this->writeTrialUpdatesFile([
            'trial_note' => 'Note for history page.',
            'updates' => [
                [
                    'date' => '2026-09-24',
                    'type' => 'feature',
                    'title' => 'Third',
                    'summary' => 'Summary three.',
                ],
                [
                    'date' => '2026-09-26',
                    'type' => 'fix',
                    'title' => 'First',
                    'summary' => 'Summary one.',
                ],
                [
                    'date' => $today,
                    'type' => 'improvement',
                    'title' => 'Latest',
                    'summary' => 'Summary latest.',
                ],
            ],
        ]);

        Config::set('trial.updates_file', $path);

        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/updates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Updates/Index', false)
                ->where('note', 'Note for history page.')
                ->has('updates', 3)
                ->where('updates.0.title', 'Latest')
                ->where('updates.1.title', 'First')
                ->where('updates.2.title', 'Third')
                ->has('counts', fn ($counts) => $counts
                    ->where('all', 3)
                    ->where('feature', 1)
                    ->where('fix', 1)
                    ->where('improvement', 1)
                    ->where('latest', 1)
                )
            );
    }

    public function test_page_shows_entries_when_trial_mode_disabled_and_trial_period_ended(): void
    {
        $path = $this->writeTrialUpdatesFile([
            'trial_note' => 'Still visible on history.',
            'updates' => [
                [
                    'date' => '2026-09-26',
                    'type' => 'feature',
                    'title' => 'Persisted entry',
                    'summary' => 'Should appear despite trial flags.',
                ],
            ],
        ]);

        Config::set('trial.enabled', false);
        Config::set('trial.ends_at', '2020-01-01');
        Config::set('trial.updates_file', $path);
        Config::set('trial.max_items', 0);

        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/updates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Updates/Index', false)
                ->where('note', 'Still visible on history.')
                ->has('updates', 1)
                ->where('updates.0.title', 'Persisted entry')
            );
    }

    public function test_missing_or_invalid_updates_file_does_not_cause_server_error(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        Config::set('trial.updates_file', storage_path('framework/testing/missing-updates-history.json'));

        $this->actingAs($user)
            ->get('/updates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Updates/Index', false)
                ->where('note', '')
                ->has('updates', 0)
                ->has('counts', fn ($counts) => $counts
                    ->where('all', 0)
                    ->where('feature', 0)
                    ->where('fix', 0)
                    ->where('improvement', 0)
                    ->where('latest', 0)
                )
            );

        $invalidPath = $this->writeTrialUpdatesFileRaw('{ not valid json');
        Config::set('trial.updates_file', $invalidPath);

        $this->actingAs($user)
            ->get('/updates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Updates/Index', false)
                ->where('note', '')
                ->has('updates', 0)
            );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeTrialUpdatesFile(array $payload): string
    {
        return $this->writeTrialUpdatesFileRaw((string) json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function writeTrialUpdatesFileRaw(string $contents): string
    {
        $path = storage_path('framework/testing/trial-updates-'.uniqid('', true).'.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, $contents);

        return $path;
    }
}