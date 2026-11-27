<?php

namespace App\Http\Controllers;

use App\Support\TrialUpdates;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UpdateHistoryController extends Controller
{
    public function index(): Response
    {
        $data = TrialUpdates::history();
        $updates = $data['updates'];

        $latestCutoff = Carbon::now()->subDays(7)->startOfDay();

        $featureCount = 0;
        $fixCount = 0;
        $improvementCount = 0;
        $latestCount = 0;

        foreach ($updates as $entry) {
            $type = is_string($entry['type'] ?? null) ? $entry['type'] : '';
            if ($type === 'feature') {
                $featureCount++;
            } elseif ($type === 'fix') {
                $fixCount++;
            } elseif ($type === 'improvement') {
                $improvementCount++;
            }

            $date = is_string($entry['date'] ?? null) ? $entry['date'] : '';
            if ($date !== '') {
                try {
                    if (Carbon::parse($date)->startOfDay()->greaterThanOrEqualTo($latestCutoff)) {
                        $latestCount++;
                    }
                } catch (\Throwable) {
                    // ignore unparsable dates for latest count
                }
            }
        }

        return Inertia::render('Updates/Index', [
            'note' => $data['note'],
            'updates' => $updates,
            'counts' => [
                'all' => count($updates),
                'feature' => $featureCount,
                'fix' => $fixCount,
                'improvement' => $improvementCount,
                'latest' => $latestCount,
            ],
        ]);
    }
}