<?php

namespace App\Services\Pricing;

use App\Models\PlantRequestLine;
use App\Services\Sap\SapReadRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PricingEstimator
{
    public function __construct(
        private readonly SapReadRepository $sapReadRepository,
    ) {}

    public function estimate(string $partNumber): array
    {
        $ttl = (int) config('services.sap.price_cache_ttl', 21600);

        return Cache::remember(
            "part_price:{$partNumber}",
            $ttl,
            fn () => $this->resolve($partNumber)
        );
    }

    private function resolve(string $partNumber): array
    {
        $sapPrice = $this->getSapPrice($partNumber);
        if ($sapPrice !== null) {
            return $sapPrice;
        }

        $historicalPrice = $this->getHistoricalPrice($partNumber);
        if ($historicalPrice !== null) {
            return $historicalPrice;
        }

        return [
            'unit_price' => '0.00',
            'source' => 'none',
            'reference' => null,
        ];
    }

    private function getSapPrice(string $partNumber): ?array
    {
        try {
            $result = $this->sapReadRepository->getItemPurchasePrice($partNumber);
        } catch (\Throwable) {
            return null;
        }

        if ($result === null) {
            return null;
        }

        return [
            'unit_price' => number_format((float) $result['price'], 2, '.', ''),
            'source' => 'sap_price',
            'reference' => $result['reference'],
        ];
    }

    private function getHistoricalPrice(string $partNumber): ?array
    {
        $line = PlantRequestLine::query()
            ->join('plant_requests', 'plant_requests.id', '=', 'plant_request_lines.plant_request_id')
            ->where('plant_request_lines.part_number', $partNumber)
            ->where('plant_request_lines.unit_price_est', '>', 0)
            ->whereNotIn('plant_requests.status', ['cancelled', 'rejected'])
            ->orderByDesc('plant_request_lines.created_at')
            ->orderByDesc('plant_request_lines.id')
            ->select(
                'plant_request_lines.unit_price_est',
                'plant_request_lines.price_source',
                'plant_requests.request_no',
                'plant_requests.created_at as request_created_at'
            )
            ->first();

        if (! $line) {
            return null;
        }

        $source = in_array($line->price_source, ['tabulation_bid', 'manual'], true)
            ? $line->price_source
            : 'manual';

        $date = $line->request_created_at
            ? Carbon::parse($line->request_created_at)->format('d M Y')
            : '';

        return [
            'unit_price' => number_format((float) $line->unit_price_est, 2, '.', ''),
            'source' => $source,
            'reference' => "Permintaan {$line->request_no} · {$date}",
        ];
    }
}
