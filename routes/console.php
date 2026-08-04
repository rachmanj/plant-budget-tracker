<?php

use App\Jobs\CarryForwardJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('arkfleet:warm-cache')
    ->dailyAt('06:00')
    ->timezone('Asia/Makassar');

Schedule::job(new CarryForwardJob)
    ->monthlyOn(1, '00:01')
    ->timezone('Asia/Makassar');
