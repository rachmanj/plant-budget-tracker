<?php

namespace App\Http\Requests;

use App\Models\BudgetPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\BudgetAllocation::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'project_code' => ['required', 'string', 'max:20'],
            'period_month' => ['required', 'date'],
            'status' => ['sometimes', 'in:draft,open,locked,closed'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.equipment_id' => ['nullable', 'integer'],
            'allocations.*.unit_code_cache' => ['nullable', 'string', 'max:50'],
            'allocations.*.plant_type_cache' => ['nullable', 'in:DIGGER,HAULER,SUPPORT'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'min:0'],
            'allocations.*.tolerance_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocations.*.memo' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $allocations = (array) $this->input('allocations', []);
                [$existingEquipmentIds, $existingDivisionPlantTypes] = $this->existingAllocationsForPeriod();

                $seenEquipmentIds = [];
                $seenDivisionPlantTypes = [];

                foreach ($allocations as $index => $row) {
                    $equipmentId = $row['equipment_id'] ?? null;
                    $unitCode = $row['unit_code_cache'] ?? null;
                    $plantType = $row['plant_type_cache'] ?? null;
                    $hasEquipment = $equipmentId !== null && $equipmentId !== '';

                    if ($hasEquipment) {
                        if ($unitCode === null || $unitCode === '') {
                            $validator->errors()->add(
                                "allocations.{$index}.unit_code_cache",
                                'Unit code wajib diisi untuk alokasi per unit.'
                            );
                        }

                        if ($plantType === null || $plantType === '') {
                            $validator->errors()->add(
                                "allocations.{$index}.plant_type_cache",
                                'Tipe plant wajib dipilih untuk alokasi per unit.'
                            );
                        }

                        $equipmentIdInt = (int) $equipmentId;

                        if (in_array($equipmentIdInt, $seenEquipmentIds, true)) {
                            $validator->errors()->add(
                                "allocations.{$index}.equipment_id",
                                'Unit ini dialokasikan lebih dari sekali dalam satu periode.'
                            );
                        } else {
                            $seenEquipmentIds[] = $equipmentIdInt;
                        }

                        if (in_array($equipmentIdInt, $existingEquipmentIds, true)) {
                            $validator->errors()->add(
                                "allocations.{$index}.equipment_id",
                                'Unit ini sudah punya alokasi pada periode tersebut.'
                            );
                        }
                    } elseif ($plantType !== null && $plantType !== '') {
                        if (
                            in_array($plantType, $seenDivisionPlantTypes, true)
                            || in_array($plantType, $existingDivisionPlantTypes, true)
                        ) {
                            $validator->errors()->add(
                                "allocations.{$index}.plant_type_cache",
                                'Alokasi divisi untuk tipe plant ini sudah ada pada periode tersebut.'
                            );
                        } else {
                            $seenDivisionPlantTypes[] = $plantType;
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array{0: list<int>, 1: list<string>}
     */
    private function existingAllocationsForPeriod(): array
    {
        $projectCode = $this->input('project_code');
        $periodMonth = $this->input('period_month');

        if (! is_string($projectCode) || $projectCode === '' || ! $periodMonth) {
            return [[], []];
        }

        try {
            $normalizedMonth = Carbon::parse($periodMonth)->startOfMonth()->toDateString();
        } catch (\Throwable) {
            return [[], []];
        }

        $period = BudgetPeriod::query()
            ->where('project_code', $projectCode)
            ->whereDate('period_month', $normalizedMonth)
            ->first();

        if (! $period) {
            return [[], []];
        }

        $existingEquipmentIds = $period->allocations()
            ->whereNotNull('equipment_id')
            ->pluck('equipment_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingDivisionPlantTypes = $period->allocations()
            ->whereNull('equipment_id')
            ->whereNotNull('plant_type_cache')
            ->pluck('plant_type_cache')
            ->all();

        return [$existingEquipmentIds, $existingDivisionPlantTypes];
    }
}
