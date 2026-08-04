<?php

namespace Tests\Feature\FeatureFlag;

use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesScopedUsers;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use CreatesScopedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_cannibal_beta_routes_return_404_when_disabled(): void
    {
        config(['features.cannibal_beta' => false]);

        $planner = $this->makeUserWithRole('planner');

        $this->actingAsProject($planner)
            ->get('/components')
            ->assertNotFound();

        $this->actingAsProject($planner)
            ->get('/cannibal-requests')
            ->assertNotFound();
    }

    public function test_cannibal_beta_routes_available_when_enabled(): void
    {
        config(['features.cannibal_beta' => true]);

        $planner = $this->makeUserWithRole('planner');
        $amlMgr = $this->makeUserWithRole('aml_manager');

        $this->actingAsProject($planner)
            ->get('/cannibal-requests')
            ->assertOk();

        $this->actingAsProject($amlMgr)
            ->get('/components')
            ->assertOk();
    }

    public function test_ensure_feature_enabled_middleware_blocks_disabled_feature(): void
    {
        config(['features.cannibal_beta' => false]);

        $middleware = new \App\Http\Middleware\EnsureFeatureEnabled();
        $request = \Illuminate\Http\Request::create('/components');
        $request->setUserResolver(fn () => $this->makeUserWithRole('aml_manager'));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $middleware->handle($request, fn () => response('ok'), 'cannibal_beta');
    }

    public function test_ensure_feature_enabled_middleware_passes_when_enabled(): void
    {
        config(['features.cannibal_beta' => true]);

        $middleware = new \App\Http\Middleware\EnsureFeatureEnabled();
        $request = \Illuminate\Http\Request::create('/components');

        $response = $middleware->handle($request, fn () => response('ok'), 'cannibal_beta');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
