<?php

namespace App\Services\Pricing;

use App\Models\TabulationBidAward;
use App\Services\Sap\SapReadRepository;
use Illuminate\Support\Facades\Cache;

class PricingEstimator
{
    public function __construct(
        private readonly SapReadRepository $sapReadRepository,
    ) {}

    public function estimate(string $partNumber): array
    {
        $tabulationPrice = $this->getLatestTabulationBidPrice($partNumber);
        if ($tabulationPrice !== null) {
            return [
                'unit_price' => $tabulationPrice,
                'source' => 'tabulation_bid',
            ];
        }

        $sapPrice = $this->getSapPrice($partNumber);
        if ($sapPrice !== null) {
            return [
                'unit_price' => $sapPrice,
                'source' => 'sap_price',
            ];
        }

        return [
            'unit_price' => '0.00',
            'source' => 'none',
        ];
    }

    private function getLatestTabulationBidPrice(string $partNumber): ?string
    {
        $award = TabulationBidAward::query()
            ->with('vendor')
            ->whereHas('bid', fn ($q) => $q->whereIn('status', ['forwarded_admin', 'po_created', 'closed']))
            ->latest('awarded_at')
            ->first();

        if (! $award?->vendor) {
            return null;
        }

        return number_format((float) $award->vendor->price, 2, '.', '');
    }

    private function getSapPrice(string $partNumber): ?string
    {
        return Cache::remember("sap_price:{$partNumber}", 86400, function () use ($partNumber) {
            try {
                $prices = $this->sapReadRepository->getPriceList([$partNumber]);
                $price = $prices->first();

                if ($price && isset($price->Price)) {
                    return number_format((float) $price->Price, 2, '.', '');
                }
            } catch (\Throwable) {
                return null;
            }

            return null;
        });
    }
}
