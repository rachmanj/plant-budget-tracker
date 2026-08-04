<?php

namespace Tests\Concerns;

use App\Models\BudgetAllocation;
use App\Models\BudgetPeriod;
use App\Models\User;
use App\Services\Budget\BudgetEngine;

trait CreatesScopedUsers
{
    protected function makeFinanceDirector(): User
    {
        $user = User::factory()->create(['is_active' => true, 'project_code_scope' => null]);
        setPermissionsTeamId('');
        $user->assignRole('finance_director');

        return $user;
    }

    protected function makeUserWithRole(string $role, string $project = 'MBL'): User
    {
        $user = User::factory()->create([
            'is_active' => true,
            'project_code_scope' => $project,
        ]);

        setPermissionsTeamId($project);
        $user->assignRole($role);

        return $user;
    }

    protected function makeAllocation(
        User $finance,
        string $project = 'MBL',
        string $amount = '10000000.00',
        int $equipmentId = 42,
        string $unitCode = 'E-042',
    ): BudgetAllocation {
        $period = BudgetPeriod::factory()->create([
            'project_code' => $project,
            'created_by' => $finance->id,
            'status' => 'open',
            'period_month' => now()->startOfMonth(),
        ]);

        return app(BudgetEngine::class)->createAllocation($period, [
            'allocated_amount' => $amount,
            'equipment_id' => $equipmentId,
            'unit_code_cache' => $unitCode,
            'tolerance_pct' => '10.00',
        ], $finance);
    }

    protected function actingAsProject(User $user, string $project = 'MBL'): static
    {
        setPermissionsTeamId($project);

        return $this->actingAs($user)
            ->withoutVite()
            ->withSession(['current_project' => $project]);
    }
}
