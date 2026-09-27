<?php

namespace App\Services\Procurement;

use App\Models\SapSupplier;
use Illuminate\Support\Collection;

class SapSupplierSync
{
    /**
     * @param  Collection<int, object>  $rows
     * @return array{created: int, updated: int, deactivated: int}
     */
    public function sync(Collection $rows): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'deactivated' => 0,
        ];

        $syncedAt = now();
        $activeCodes = [];

        foreach ($rows as $row) {
            $cardCode = trim((string) ($row->card_code ?? ''));
            if ($cardCode === '') {
                continue;
            }

            $activeCodes[] = $cardCode;

            $attributes = [
                'card_name' => (string) ($row->card_name ?? ''),
                'payment_terms' => $row->payment_terms !== null ? (string) $row->payment_terms : null,
                'currency' => $row->currency !== null ? (string) $row->currency : null,
                'is_active' => true,
                'synced_at' => $syncedAt,
            ];

            $supplier = SapSupplier::query()->where('card_code', $cardCode)->first();

            if ($supplier === null) {
                SapSupplier::query()->create([
                    'card_code' => $cardCode,
                    ...$attributes,
                ]);
                $summary['created']++;

                continue;
            }

            $supplier->fill($attributes);
            if ($supplier->isDirty()) {
                $supplier->save();
                $summary['updated']++;
            } else {
                $supplier->touch();
            }
        }

        if ($activeCodes !== []) {
            $summary['deactivated'] = SapSupplier::query()
                ->where('is_active', true)
                ->whereNotIn('card_code', $activeCodes)
                ->update(['is_active' => false, 'synced_at' => $syncedAt]);
        }

        return $summary;
    }
}
