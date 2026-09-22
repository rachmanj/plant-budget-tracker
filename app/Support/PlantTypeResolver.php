<?php

namespace App\Support;

class PlantTypeResolver
{
    /**
     * Maps a raw ARKFLEET plant_type value to a PMB plant_type_cache value.
     * Only DIGGER, HAULER, and SUPPORT have a direct mapping. Anything else
     * (HEAVY EQUIPMENT, n/a, null) returns null — the user must pick manually.
     */
    public static function fromArkfleet(?string $plantType): ?string
    {
        if ($plantType === null) {
            return null;
        }

        $normalized = strtoupper(trim($plantType));

        return match ($normalized) {
            'DIGGER' => 'DIGGER',
            'HAULER' => 'HAULER',
            'SUPPORT' => 'SUPPORT',
            default => null,
        };
    }
}
