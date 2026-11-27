<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LoginTrialUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_does_not_include_trial_updates_prop_when_trial_mode_enabled(): void
    {
        $path = $this->writeTrialUpdatesFile([
            'trial_note' => 'Trial note for testers.',
            'updates' => [
                [
                    'date' => '2026-09-26',
                    'type' => 'fix',
                    'title' => 'First',
                    'summary' => 'Summary one.',
                ],
            ],
        ]);

        Config::set('trial.enabled', true);
        Config::set('trial.ends_at', null);
        Config::set('trial.updates_file', $path);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login', false)
                ->missing('trialUpdates')
            );
    }

    public function test_login_does_not_include_trial_updates_when_trial_mode_disabled(): void
    {
        Config::set('trial.enabled', false);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login', false)
                ->missing('trialUpdates')
            );
    }

    public function test_login_does_not_include_trial_updates_when_trial_period_ended(): void
    {
        $path = $this->writeTrialUpdatesFile([
            'trial_note' => 'Should not appear on login.',
            'updates' => [
                [
                    'date' => '2026-09-26',
                    'type' => 'feature',
                    'title' => 'Stale',
                    'summary' => 'Stale summary.',
                ],
            ],
        ]);

        Config::set('trial.enabled', true);
        Config::set('trial.ends_at', '2020-01-01');
        Config::set('trial.updates_file', $path);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login', false)
                ->missing('trialUpdates')
            );
    }

    public function test_login_succeeds_when_trial_updates_file_is_missing_or_invalid(): void
    {
        Config::set('trial.enabled', true);
        Config::set('trial.ends_at', null);

        Config::set('trial.updates_file', storage_path('framework/testing/missing-trial-updates.json'));

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login', false)
                ->missing('trialUpdates')
            );

        $invalidPath = $this->writeTrialUpdatesFileRaw('{ not valid json');

        Config::set('trial.updates_file', $invalidPath);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login', false)
                ->missing('trialUpdates')
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