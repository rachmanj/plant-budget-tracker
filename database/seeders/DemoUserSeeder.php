<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $demos = [
            ['email' => 'planner@pmb.test', 'name' => 'Demo Planner', 'role' => 'planner', 'project' => 'MBL'],
            ['email' => 'finance.director@pmb.test', 'name' => 'Demo Finance Director', 'role' => 'finance_director', 'project' => null],
            ['email' => 'it.manager@pmb.test', 'name' => 'Demo IT Manager', 'role' => 'it_manager', 'project' => null],
            ['email' => 'plant.manager@pmb.test', 'name' => 'Demo Plant Manager MBL', 'role' => 'plant_manager', 'project' => 'MBL'],
            ['email' => 'plant.manager.sml@pmb.test', 'name' => 'Demo Plant Manager SML', 'role' => 'plant_manager', 'project' => 'SML'],
        ];

        foreach ($demos as $demo) {
            $user = User::firstOrCreate(
                ['email' => $demo['email']],
                [
                    'name' => $demo['name'],
                    'password' => Hash::make('password'),
                    'division' => 'plant',
                    'project_code_scope' => $demo['project'],
                    'is_active' => true,
                ]
            );

            $projectCode = $demo['project'] ?? '';
            setPermissionsTeamId($projectCode);
            $user->roles()->detach();
            $user->assignRole($demo['role']);
        }
    }
}
