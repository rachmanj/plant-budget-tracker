<?php

namespace App\Jobs;

use App\Models\InterchangeMap;
use App\Models\PlantRequest;
use App\Models\SapSyncLog;
use App\Services\Sap\SapCircuitBreaker;
use App\Services\Sap\SapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateSapPurchaseRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $plantRequestId,
    ) {
        $this->onQueue('sap-writes');
    }

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(SapService $sapService, SapCircuitBreaker $breaker): void
    {
        if ($breaker->isOpen('service_layer')) {
            $this->release(60);

            return;
        }

        $plantRequest = PlantRequest::with('lines')->findOrFail($this->plantRequestId);
        $correlationKey = "create_pr:plant_request:{$this->plantRequestId}";

        $log = SapSyncLog::firstOrCreate(
            ['correlation_key' => $correlationKey],
            [
                'operation' => 'create_pr',
                'ref_type' => 'plant_request',
                'ref_id' => $this->plantRequestId,
                'status' => 'pending',
            ]
        );

        if ($log->status === 'success') {
            return;
        }

        $lines = $plantRequest->lines->map(function ($line) {
            $partNumber = $line->part_number;
            if ($line->interchange_map_id) {
                $map = InterchangeMap::find($line->interchange_map_id);
                $partNumber = $map?->genuine_part_number ?? $partNumber;
            }

            return [
                'ItemCode' => $partNumber,
                'Quantity' => $line->qty,
                'UnitPrice' => (float) $line->unit_price_est,
            ];
        })->all();

        $payload = ['DocumentLines' => $lines];

        try {
            $result = $sapService->createPurchaseRequest($payload);
            $prNo = (string) ($result['DocNum'] ?? $result['DocEntry'] ?? '');

            $plantRequest->update(['sap_pr_no' => $prNo, 'status' => 'pr_created']);
            $log->update([
                'status' => 'success',
                'request_payload' => $payload,
                'response_payload' => $result,
                'completed_at' => now(),
            ]);

            $breaker->recordSuccess('service_layer');
        } catch (\Throwable $e) {
            $breaker->recordFailure('service_layer');
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
