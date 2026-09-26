<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrialUpdates
{
    /**
     * @return array{note: string, updates: array<int, array<string, mixed>>}
     */
    public static function all(): array
    {
        if (! config('trial.enabled')) {
            return self::empty();
        }

        $endsAt = config('trial.ends_at');
        if (filled($endsAt) && Carbon::parse($endsAt)->endOfDay()->isPast()) {
            return self::empty();
        }

        $path = config('trial.updates_file');

        if (! is_string($path) || ! is_readable($path)) {
            Log::warning('Trial updates file is missing or not readable.', ['path' => $path]);

            return self::empty();
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            Log::warning('Trial updates file could not be read.', ['path' => $path]);

            return self::empty();
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Log::warning('Trial updates file contains invalid JSON.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return self::empty();
        }

        if (! is_array($decoded)) {
            Log::warning('Trial updates file must decode to an object.', ['path' => $path]);

            return self::empty();
        }

        $note = is_string($decoded['trial_note'] ?? null) ? $decoded['trial_note'] : '';
        $updates = is_array($decoded['updates'] ?? null) ? $decoded['updates'] : [];

        usort($updates, function (array $a, array $b): int {
            $dateA = is_string($a['date'] ?? null) ? $a['date'] : '';
            $dateB = is_string($b['date'] ?? null) ? $b['date'] : '';

            return strcmp($dateB, $dateA);
        });

        $maxItems = (int) config('trial.max_items', 12);
        if ($maxItems > 0) {
            $updates = array_slice($updates, 0, $maxItems);
        }

        return [
            'note' => $note,
            'updates' => array_values($updates),
        ];
    }

    /**
     * @return array{note: string, updates: array<int, never>}
     */
    private static function empty(): array
    {
        return [
            'note' => '',
            'updates' => [],
        ];
    }
}
