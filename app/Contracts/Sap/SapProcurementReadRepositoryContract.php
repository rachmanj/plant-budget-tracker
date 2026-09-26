<?php

namespace App\Contracts\Sap;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SapProcurementReadRepositoryContract
{
    /**
     * @return Collection<int, object>
     */
    public function fetchPurchaseOrderRows(CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * @return Collection<int, object>
     */
    public function fetchPurchaseRequestRows(CarbonInterface $from, CarbonInterface $to): Collection;
}
