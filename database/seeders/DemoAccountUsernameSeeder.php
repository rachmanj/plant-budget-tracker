<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountUsernameSeeder extends Seeder
{
    /**
     * Idempotent backfill of proc-app-style usernames for known demo accounts.
     *
     * @var array<string, string>
     */
    private const EMAIL_TO_USERNAME = [
        'procurement.admin@pmb.demo' => 'adminproc',
        'buyer@pmb.demo' => 'buyer',
        'president.director@pmb.demo' => 'director',
        'it.manager@pmb.demo' => 'admin',
        'procurement.manager@pmb.demo' => 'procurement.manager',
        'plant.manager@pmb.demo' => 'plant.manager',
        'project.manager@pmb.demo' => 'project.manager',
        'planner@pmb.demo' => 'planner',
        'mechanic@pmb.demo' => 'mechanic',
        'logistic.foreman@pmb.demo' => 'logistic.foreman',
        'logistic.pic@pmb.demo' => 'logistic.pic',
        'finance.director@pmb.demo' => 'finance.director',
        'operation.director@pmb.demo' => 'operation.director',
        'aml.manager@pmb.demo' => 'aml.manager',
        'aml.dept.head@pmb.demo' => 'aml.dept.head',
        'rachmanj@gmail.com' => 'rachmanj',
    ];

    public function run(): void
    {
        foreach (self::EMAIL_TO_USERNAME as $email => $username) {
            User::query()
                ->where('email', $email)
                ->update(['username' => strtolower($username)]);
        }
    }
}
