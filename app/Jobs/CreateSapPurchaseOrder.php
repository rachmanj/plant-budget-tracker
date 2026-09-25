<?php

namespace App\Jobs;

use App\Models\InterchangeMap;
use App\Models\PlantRequest;
use App\Models\SapSyncLog;
use App\Models\TabulationBid;
use App\Services\Sap\SapCircuitBreaker;
use App\Services\Sap\SapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CreateSapPurchaseOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $tabulationBidId,
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

        $lock = Cache::lock("sap-po-create-{$this->tabulationBidId}", 120);

        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $bid = TabulationBid::with('award.vendor')->findOrFail($this->tabulationBidId);

            if ($bid->sap_po_id) {
                return;
            }

            $correlationKey = "create_po:tabulation_bid:{$this->tabulationBidId}";

            $log = SapSyncLog::firstOrCreate(
                ['correlation_key' => $correlationKey],
                [
                    'operation' => 'create_po',
                    'ref_type' => 'tabulation_bid',
                    'ref_id' => $this->tabulationBidId,
                    'status' => 'pending',
                ]
            );

            if ($log->status === 'success') {
                return;
            }

            $vendor = $bid->award?->vendor;
            if (! $vendor) {
                throw new \RuntimeException('No awarded vendor for PO creation.');
            }

            $log->increment('attempts');

            try {
                $plantRequest = PlantRequest::with('lines')
                    ->where('sap_pr_no', $bid->sap_pr_id)
                    ->first();

                if (! $plantRequest) {
                    throw new \RuntimeException("Awarded plant request not found for PR {$bid->sap_pr_id}");
                }

                $documentLines = $plantRequest->lines->map(function ($line) {
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

                $today = now()->startOfDay();
                $payload = [
                    'CardCode' => $vendor->vendor_code,
                    'DocDate' => $today->toDateString(),
                    'DocDueDate' => $today->copy()->addDays(30)->toDateString(),
                    'Comments' => "PMB tabulation bid #{$bid->id} - PR {$bid->sap_pr_id}",
                    'DocumentLines' => $documentLines,
                ];

                $result = $sapService->createPurchaseOrder($payload);
                $poId = (string) ($result['DocEntry'] ?? 'PENDING_SAP');

                $bid->update(['sap_po_id' => $poId, 'status' => 'po_created', 'sap_sync_failed' => false]);
                $this->advancePlantRequestStatus($bid->sap_pr_id, $poId);

                $log->update([
                    'status' => 'success',
                    'request_payload' => $payload,
                    'response_payload' => $result,
                    'completed_at' => now(),
                ]);

                $breaker->recordSuccess('service_layer');
            } catch (\Throwable $e) {
                $breaker->recordFailure('service_layer');
                $log->update([
                    'status' => $this->attempts() >= $this->tries ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                ]);

                if ($this->attempts() >= $this->tries) {
                    $bid->update(['sap_sync_failed' => true, 'sap_po_id' => 'PENDING_SAP']);
                }

                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    private function advancePlantRequestStatus(string $sapPrNo, string $sapPoId): void
    {
        $plantRequest = PlantRequest::where('sap_pr_no', $sapPrNo)->first();

        if (! $plantRequest) {
            return;
        }

        if (! in_array($plantRequest->status, ['approved', 'pr_created'], true)) {
            return;
        }

        $plantRequest->update(['status' => 'po_created', 'sap_po_id' => $sapPoId]);
    }
}
