<?php

namespace App\Jobs;

use App\Models\InterchangeMap;
use App\Models\SapSyncLog;
use App\Services\Sap\SapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncInterchangeToSap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $interchangeMapId,
    ) {
        $this->onQueue('sap-writes');
    }

    public function handle(SapService $sapService): void
    {
        $map = InterchangeMap::findOrFail($this->interchangeMapId);

        if (! $map->isReadyForSapSync()) {
            return;
        }

        $correlationKey = "sync_interchange:{$this->interchangeMapId}";

        $log = SapSyncLog::firstOrCreate(
            ['correlation_key' => $correlationKey],
            [
                'operation' => 'sync_interchange',
                'ref_type' => 'interchange_map',
                'ref_id' => $this->interchangeMapId,
                'status' => 'pending',
            ]
        );

        if ($log->status === 'success') {
            return;
        }

        $payload = [
            'genuine_part_number' => $map->genuine_part_number,
            'oem_part_number' => $map->oem_part_number,
            'material_name' => $map->material_name,
        ];

        try {
            $result = $sapService->syncInterchangeMapping($payload);
            $map->update([
                'sap_synced' => true,
                'sap_sync_ref' => (string) ($result['ItemCode'] ?? $map->genuine_part_number),
            ]);
            $log->update(['status' => 'success', 'response_payload' => $result, 'completed_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            throw $e;
        }
    }
}
