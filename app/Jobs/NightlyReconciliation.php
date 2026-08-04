<?php

namespace App\Jobs;

use App\Models\PlantRequest;
use App\Models\TabulationBid;
use App\Services\Sap\SapReadRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NightlyReconciliation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('sap-writes');
    }

    public function handle(SapReadRepository $repository): void
    {
        $discrepancies = [];

        $bids = TabulationBid::query()
            ->where('status', 'po_created')
            ->whereNotNull('sap_po_id')
            ->where('sap_po_id', '!=', 'PENDING_SAP')
            ->get();

        foreach ($bids as $bid) {
            try {
                $repository->getPurchaseOrder((int) $bid->sap_po_id);
            } catch (\Throwable) {
                $discrepancies[] = [
                    'type' => 'tabulation_bid',
                    'id' => $bid->id,
                    'message' => "PO {$bid->sap_po_id} not found in SAP",
                ];
            }
        }

        if (! empty($discrepancies)) {
            Log::warning('Nightly reconciliation found discrepancies', ['count' => count($discrepancies)]);
        }
    }
}
