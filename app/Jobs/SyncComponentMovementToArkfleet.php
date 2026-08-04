<?php

namespace App\Jobs;

use App\Models\CannibalRequest;
use App\Models\Component;
use App\Services\Arkfleet\ArkfleetClient;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncComponentMovementToArkfleet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $cannibalRequestId,
    ) {
        $this->onQueue('default');
    }

    public function handle(ArkfleetClient $client): void
    {
        $request = CannibalRequest::with('components')->findOrFail($this->cannibalRequestId);

        foreach ($request->components as $component) {
            try {
                $client->patchComponentStatus($request->target_equipment_id, $component->id, 'cannibalized');
                $component->update(['status' => 'cannibalized', 'synced_to_arkfleet' => true]);
            } catch (ClientException $e) {
                if ($e->getResponse()?->getStatusCode() === 404) {
                    Log::info('ARKFLEET component PATCH endpoint not available yet.');

                    return;
                }

                throw $e;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), '404')) {
                    Log::info('ARKFLEET component PATCH endpoint not available yet.');

                    return;
                }

                throw $e;
            }
        }
    }
}
