<?php

namespace App\Jobs;

use App\Contracts\Sap\SapProcurementReadRepositoryContract;
use App\Models\PlantRequest;
use App\Models\SapSyncLog;
use App\Models\TabulationBid;
use App\Services\Procurement\PurchaseOrderRegisterSync;
use App\Services\Procurement\PurchaseRequestRegisterSync;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SyncSapProcurementRegister implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?CarbonInterface $from = null,
        public ?CarbonInterface $to = null,
    ) {
        $this->onQueue('sap-writes');
    }

    public function handle(
        SapProcurementReadRepositoryContract $repository,
        PurchaseOrderRegisterSync $purchaseOrderRegisterSync,
        PurchaseRequestRegisterSync $purchaseRequestRegisterSync,
    ): void {
        $from = Carbon::parse($this->from ?? now()->subDays(30)->startOfDay());
        $to = Carbon::parse($this->to ?? now()->endOfDay());

        $fromKey = $from->format('Y-m-d');
        $toKey = $to->format('Y-m-d');
        $correlationKey = "sync_pr_register:{$fromKey}:{$toKey}";

        $log = SapSyncLog::firstOrCreate(
            ['correlation_key' => $correlationKey],
            [
                'operation' => 'sync_pr_register',
                'ref_type' => 'procurement_register',
                'ref_id' => 0,
                'status' => 'pending',
            ]
        );

        if ($log->status === 'success') {
            return;
        }

        $log->increment('attempts');

        try {
            $includePrDocNums = PlantRequest::query()
                ->whereNotNull('sap_pr_no')
                ->where('sap_pr_no', '!=', '')
                ->pluck('sap_pr_no')
                ->unique()
                ->values()
                ->all();

            $includePoDocEntries = PlantRequest::query()
                ->whereNotNull('sap_po_id')
                ->where('sap_po_id', '!=', '')
                ->pluck('sap_po_id')
                ->merge(
                    TabulationBid::query()
                        ->whereNotNull('sap_po_id')
                        ->where('sap_po_id', '!=', '')
                        ->pluck('sap_po_id')
                )
                ->unique()
                ->values()
                ->all();

            $poSummary = $purchaseOrderRegisterSync->sync(
                $repository->fetchPurchaseOrderRows($from, $to, $includePoDocEntries),
                $includePoDocEntries,
            );
            $prSummary = $purchaseRequestRegisterSync->sync(
                $repository->fetchPurchaseRequestRows($from, $to, $includePrDocNums),
                $includePrDocNums,
            );

            $log->update([
                'status' => 'success',
                'request_payload' => [
                    'from' => $fromKey,
                    'to' => $toKey,
                ],
                'response_payload' => [
                    'purchase_orders' => $poSummary,
                    'purchase_requests' => $prSummary,
                ],
                'error_message' => null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
