<?php

namespace App\Contracts\Sap;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SapProcurementReadRepositoryContract
{
    /**
     * @return Collection<int, object>
     */
    /**
     * @param  array<int|string>  $includeDocEntries  SAP DocEntry values for PMB-created POs (bypass department filter)
     * @return Collection<int, object>
     */
    public function fetchPurchaseOrderRows(CarbonInterface $from, CarbonInterface $to, array $includeDocEntries = []): Collection;

    /**
     * @param  array<int|string>  $includeDocNums  SAP DocNum values for PMB-created PRs (bypass department filter)
     * @return Collection<int, object>
     */
    public function fetchPurchaseRequestRows(CarbonInterface $from, CarbonInterface $to, array $includeDocNums = []): Collection;
}
