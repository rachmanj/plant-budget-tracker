<?php

namespace App\Support;

use App\Models\ProcurementSetting;
use App\Models\User;
use InvalidArgumentException;

class ProcurementSettings
{
    private const PO_DIRECTOR_THRESHOLD_KEY = 'po_director_threshold_idr';

    public static function poDirectorThreshold(): float
    {
        $value = ProcurementSetting::query()
            ->where('key', self::PO_DIRECTOR_THRESHOLD_KEY)
            ->value('value');

        if ($value === null || $value === '') {
            return 100000000.00;
        }

        return (float) $value;
    }

    public static function setPoDirectorThreshold(string $value, ?User $actor): void
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Threshold must be a non-negative number.');
        }

        ProcurementSetting::query()->updateOrCreate(
            ['key' => self::PO_DIRECTOR_THRESHOLD_KEY],
            [
                'value' => $value,
                'updated_by' => $actor?->id,
            ]
        );
    }
}
