<?php

namespace App\Jobs;

use App\Models\DmbdEntry;
use App\Services\Arkfleet\ArkfleetClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDmbdStatusToArkfleet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $dmbdEntryId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ArkfleetClient $client): void
    {
        $entry = DmbdEntry::findOrFail($this->dmbdEntryId);

        try {
            $client->patchEquipmentStatus($entry->equipment_id, $entry->operational_status);
            $entry->update(['synced_to_arkfleet' => true]);
        } catch (RequestException $e) {
            if ($e->response?->status() === 404) {
                Log::info('ARKFLEET status PATCH endpoint not available yet — will retry on schedule.');

                return;
            }

            throw $e;
        }
    }
}
