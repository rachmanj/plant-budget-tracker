<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $periodIds = DB::table('budget_allocations')
            ->select('budget_period_id')
            ->groupBy('budget_period_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('budget_period_id');

        foreach ($periodIds as $periodId) {
            $rows = DB::table('budget_allocations')
                ->where('budget_period_id', $periodId)
                ->orderBy('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $keeper = $rows->first();
            $duplicates = $rows->slice(1);

            $allocatedAmount = $keeper->allocated_amount;
            $carryForwardIn = $keeper->carry_forward_in;
            $committedAmount = $keeper->committed_amount;
            $actualAmount = $keeper->actual_amount;
            $tolerancePct = $keeper->tolerance_pct;

            foreach ($duplicates as $row) {
                $allocatedAmount = bcadd((string) $allocatedAmount, (string) $row->allocated_amount, 2);
                $carryForwardIn = bcadd((string) $carryForwardIn, (string) $row->carry_forward_in, 2);
                $committedAmount = bcadd((string) $committedAmount, (string) $row->committed_amount, 2);
                $actualAmount = bcadd((string) $actualAmount, (string) $row->actual_amount, 2);

                if (bccomp((string) $row->tolerance_pct, (string) $tolerancePct, 2) > 0) {
                    $tolerancePct = $row->tolerance_pct;
                }

                DB::table('budget_ledgers')
                    ->where('budget_allocation_id', $row->id)
                    ->update(['budget_allocation_id' => $keeper->id]);

                DB::table('budget_allocations')->where('id', $row->id)->delete();
            }

            DB::table('budget_allocations')
                ->where('id', $keeper->id)
                ->update([
                    'equipment_id' => null,
                    'unit_code_cache' => null,
                    'plant_type_cache' => null,
                    'allocated_amount' => $allocatedAmount,
                    'carry_forward_in' => $carryForwardIn,
                    'committed_amount' => $committedAmount,
                    'actual_amount' => $actualAmount,
                    'tolerance_pct' => $tolerancePct,
                    'is_editable' => true,
                    'updated_at' => now(),
                ]);
        }

        if (! Schema::hasIndex('budget_allocations', 'budget_allocations_budget_period_id_unique')) {
            Schema::table('budget_allocations', function (Blueprint $table) {
                $table->unique('budget_period_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropUnique(['budget_period_id']);
        });
    }
};
