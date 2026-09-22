<?php

namespace App\Services\Sap;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SapReadRepository
{
    public function __construct(
        private readonly SapService $sapService,
    ) {}

    public function getMaterialRequest(int $docEntry): array
    {
        try {
            $row = DB::connection('sap_sql')
                ->table('OPRQ')
                ->where('DocEntry', $docEntry)
                ->first();

            if ($row) {
                return (array) $row;
            }
        } catch (\Throwable $e) {
            Log::warning('SAP direct SQL MR read failed', ['error' => $e->getMessage()]);
        }

        return $this->sapService->request('GET', "PurchaseRequests({$docEntry})");
    }

    public function getMaterialRequestLines(int $docEntry): Collection
    {
        try {
            return collect(DB::connection('sap_sql')
                ->select('SELECT TOP 100 * FROM [PRQ1] WHERE [DocEntry] = ?', [$docEntry]));
        } catch (\Throwable $e) {
            Log::warning('SAP direct SQL MR lines failed, using OData fallback', ['error' => $e->getMessage()]);

            $result = $this->sapService->getEntity('PurchaseRequests', [
                '$filter' => "DocEntry eq {$docEntry}",
                '$expand' => 'DocumentLines',
            ]);

            return collect($result['value'][0]['DocumentLines'] ?? []);
        }
    }

    public function getPurchaseRequest(int $docEntry): array
    {
        return $this->getMaterialRequest($docEntry);
    }

    public function getPurchaseOrder(int $docEntry): array
    {
        try {
            $row = DB::connection('sap_sql')
                ->table('OPOR')
                ->where('DocEntry', $docEntry)
                ->first();

            if ($row) {
                return (array) $row;
            }
        } catch (\Throwable $e) {
            Log::warning('SAP direct SQL PO read failed', ['error' => $e->getMessage()]);
        }

        return $this->sapService->request('GET', "Orders({$docEntry})");
    }

    public function getPriceList(array $itemCodes): Collection
    {
        try {
            $placeholders = implode(',', array_fill(0, count($itemCodes), '?'));
            $sql = "SELECT TOP 100 * FROM [ITM1] WHERE [ItemCode] IN ({$placeholders})";

            return collect(DB::connection('sap_sql')->select($sql, $itemCodes));
        } catch (\Throwable $e) {
            Log::warning('SAP direct SQL price list failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    public function getItemPurchasePrice(string $itemCode): ?array
    {
        try {
            $connection = DB::connection('sap_sql');

            $poRow = $connection->selectOne(
                'SELECT TOP 1 [POR1].[Price], [POR1].[Currency], [OPOR].[DocNum], [OPOR].[DocDate]
                 FROM [POR1]
                 INNER JOIN [OPOR] ON [OPOR].[DocEntry] = [POR1].[DocEntry]
                 WHERE [POR1].[ItemCode] = ? AND [POR1].[Price] > 0 AND [POR1].[Currency] = ?
                 ORDER BY [OPOR].[DocDate] DESC, [OPOR].[DocNum] DESC',
                [$itemCode, 'IDR']
            );

            if ($poRow && (float) $poRow->Price > 0) {
                $reference = "PO {$poRow->DocNum}";

                if ($poRow->DocDate !== null) {
                    $docDate = Carbon::parse($poRow->DocDate)->format('Y-m-d');
                    $reference .= " · {$docDate}";
                }

                return [
                    'price' => number_format((float) $poRow->Price, 2, '.', ''),
                    'currency' => (string) $poRow->Currency,
                    'source' => 'po',
                    'reference' => $reference,
                ];
            }

            $itemRow = $connection->selectOne(
                "SELECT TOP 1 [LastPurPrc] FROM [OITM] WHERE [ItemCode] = ? AND [validFor] = 'Y' AND [LastPurPrc] > 0",
                [$itemCode]
            );

            if ($itemRow && (float) $itemRow->LastPurPrc > 0) {
                return [
                    'price' => number_format((float) $itemRow->LastPurPrc, 2, '.', ''),
                    'currency' => 'IDR',
                    'source' => 'item_master',
                    'reference' => 'Harga beli terakhir (item master SAP)',
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('SAP item purchase price lookup failed', [
                'item_code' => $itemCode,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function getVendorMaster(string $cardCode): array
    {
        try {
            $row = DB::connection('sap_sql')
                ->table('OCRD')
                ->where('CardCode', $cardCode)
                ->first();

            if ($row) {
                return (array) $row;
            }
        } catch (\Throwable $e) {
            Log::warning('SAP vendor read failed', ['error' => $e->getMessage()]);
        }

        return $this->sapService->getVendorMaster($cardCode);
    }

    public function getInventoryTransferOut(Carbon $from, Carbon $to): Collection
    {
        try {
            $sqlPath = database_path('sql/list_ITO.sql');
            if (file_exists($sqlPath)) {
                return collect(DB::connection('sap_sql')->select(
                    file_get_contents($sqlPath),
                    [$from->toDateString(), $to->toDateString()]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('SAP ITO direct SQL failed', ['error' => $e->getMessage()]);
        }

        return collect();
    }
}
