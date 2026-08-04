<?php

namespace App\Jobs;

use App\Models\TabulationBid;
use App\Services\Sap\SapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollSapPoStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('sap-writes');
    }

    public function handle(SapService $sapService): void
    {
        $bids = TabulationBid::query()
            ->whereNotNull('sap_po_id')
            ->where('status', '!=', 'closed')
            ->get();

        foreach ($bids as $bid) {
            if ($bid->sap_po_id === 'PENDING_SAP') {
                continue;
            }

            try {
                $status = $sapService->getPurchaseOrderStatus($bid->sap_po_id);
                $grpos = $sapService->getGrpoByPoRef($bid->sap_po_id);

                if (! empty($grpos['value'])) {
                    foreach ($grpos['value'] as $grpo) {
                        ReconcileGrpoToLedger::dispatch((int) $grpo['DocEntry']);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
