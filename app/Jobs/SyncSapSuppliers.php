<?php

namespace App\Jobs;

use App\Services\Procurement\SapSupplierSync;
use App\Services\Sap\SapReadRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSapSuppliers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('sap-writes');
    }

    /**
     * @return array{created: int, updated: int, deactivated: int}
     */
    public function handle(SapReadRepository $repository, SapSupplierSync $sync): array
    {
        return $sync->sync($repository->fetchVendors());
    }
}
