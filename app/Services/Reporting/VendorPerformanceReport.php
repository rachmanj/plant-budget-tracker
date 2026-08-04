<?php

namespace App\Services\Reporting;

use App\Models\TabulationBidAward;
use App\Models\TabulationBidVendor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class VendorPerformanceReport
{
    public function priceCompetitiveness(string $vendorCode, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = TabulationBidVendor::query()->where('vendor_code', $vendorCode);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $vendors = $query->get();
        $avgRank = $vendors->avg('rank') ?? 0;
        $wins = TabulationBidAward::query()
            ->whereIn('tabulation_bid_vendor_id', $vendors->pluck('id'))
            ->count();

        return [
            'vendor_code' => $vendorCode,
            'avg_rank' => number_format((float) $avgRank, 2),
            'win_count' => $wins,
            'bid_count' => $vendors->count(),
            'win_rate' => $vendors->count() > 0
                ? number_format(($wins / $vendors->count()) * 100, 2)
                : '0.00',
        ];
    }

    public function stockReliability(string $vendorCode): array
    {
        $vendors = TabulationBidVendor::query()->where('vendor_code', $vendorCode)->get();
        $total = $vendors->count();

        if ($total === 0) {
            return ['vendor_code' => $vendorCode, 'ready_pct' => '0.00'];
        }

        $ready = $vendors->where('stock_availability', 'ready')->count();

        return [
            'vendor_code' => $vendorCode,
            'ready_pct' => number_format(($ready / $total) * 100, 2),
            'indent_pct' => number_format(($vendors->where('stock_availability', 'indent')->count() / $total) * 100, 2),
        ];
    }

    public function indentFrequency(): Collection
    {
        return TabulationBidVendor::query()
            ->selectRaw('vendor_code, vendor_name, COUNT(*) as total, SUM(CASE WHEN stock_availability = "indent" THEN 1 ELSE 0 END) as indent_count')
            ->groupBy('vendor_code', 'vendor_name')
            ->get()
            ->map(fn ($row) => [
                'vendor_code' => $row->vendor_code,
                'vendor_name' => $row->vendor_name,
                'indent_pct' => $row->total > 0
                    ? number_format(($row->indent_count / $row->total) * 100, 2)
                    : '0.00',
            ]);
    }
}
