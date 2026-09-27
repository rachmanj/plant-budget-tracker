<?php

namespace App\Services\Pricing;

use App\Models\ItemPrice;
use App\Models\ItemPriceHistory;
use App\Models\ItemPriceImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ItemPriceCsvImporter
{
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private const MAX_ROWS = 20000;

    public function __construct(
        private readonly PricingEstimator $pricingEstimator,
    ) {}

    public function import(UploadedFile $file, User $actor): ItemPriceImport
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('The uploaded file is invalid.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            throw new InvalidArgumentException('Only CSV files are accepted.');
        }

        if ($file->getSize() > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('CSV file must not exceed 5 MB.');
        }

        $storedPath = $file->store('item-price-imports', 'local');

        $import = new ItemPriceImport([
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'rows_total' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_unchanged' => 0,
            'rows_failed' => 0,
            'errors' => [],
            'imported_by' => $actor->id,
            'imported_at' => now(),
        ]);

        $fullPath = Storage::disk('local')->path($storedPath);
        $handle = fopen($fullPath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded CSV file.');
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headerLine = fgets($handle);
        if ($headerLine === false || trim($headerLine) === '') {
            fclose($handle);
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $delimiter = $this->detectDelimiter($headerLine);
        $headerRow = str_getcsv($this->stripBom($headerLine), $delimiter);
        if ($this->isEmptyRow($headerRow)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV file is empty.');
        }

        $columnMap = $this->mapHeaderColumns($headerRow);
        if (! isset($columnMap['item_code'], $columnMap['price'])) {
            fclose($handle);
            throw new InvalidArgumentException('CSV must include item_code and price columns.');
        }

        $errors = [];
        $rowsCreated = 0;
        $rowsUpdated = 0;
        $rowsUnchanged = 0;
        $rowsFailed = 0;
        $rowsTotal = 0;
        $affectedItemCodes = [];

        try {
            DB::transaction(function () use (
                $handle,
                $delimiter,
                $columnMap,
                $actor,
                &$errors,
                &$rowsCreated,
                &$rowsUpdated,
                &$rowsUnchanged,
                &$rowsFailed,
                &$rowsTotal,
                &$affectedItemCodes,
                $import,
            ) {
                $import->save();

                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $rowsTotal++;
                if ($rowsTotal > self::MAX_ROWS) {
                    throw new InvalidArgumentException('CSV exceeds the maximum of 20000 data rows.');
                }

                $lineNumber = $rowsTotal + 1;
                $itemCode = trim((string) ($row[$columnMap['item_code']] ?? ''));
                $priceRaw = trim((string) ($row[$columnMap['price']] ?? ''));
                $vendorCode = isset($columnMap['vendor_code'])
                    ? trim((string) ($row[$columnMap['vendor_code']] ?? ''))
                    : '';
                $uom = isset($columnMap['uom'])
                    ? trim((string) ($row[$columnMap['uom']] ?? ''))
                    : null;
                $effectiveDateRaw = isset($columnMap['effective_date'])
                    ? trim((string) ($row[$columnMap['effective_date']] ?? ''))
                    : '';
                $note = isset($columnMap['note'])
                    ? trim((string) ($row[$columnMap['note']] ?? ''))
                    : null;

                if ($itemCode === '') {
                    $rowsFailed++;
                    $errors[] = ['line' => $lineNumber, 'message' => 'item_code is required.'];

                    continue;
                }

                if ($priceRaw === '' || ! is_numeric($priceRaw) || (float) $priceRaw < 0) {
                    $rowsFailed++;
                    $errors[] = ['line' => $lineNumber, 'message' => 'price must be a non-negative number.'];

                    continue;
                }

                $price = number_format((float) $priceRaw, 2, '.', '');
                $currency = 'IDR';
                $effectiveDate = $effectiveDateRaw !== '' ? $effectiveDateRaw : null;

                if ($effectiveDate !== null) {
                    $parsed = date_create($effectiveDate);
                    if ($parsed === false) {
                        $rowsFailed++;
                        $errors[] = ['line' => $lineNumber, 'message' => 'effective_date is not a valid date.'];

                        continue;
                    }
                    $effectiveDate = $parsed->format('Y-m-d');
                }

                $existing = ItemPrice::query()
                    ->where('item_code', $itemCode)
                    ->where('vendor_code', $vendorCode)
                    ->first();

                if ($existing !== null
                    && $this->pricesMatch($existing, $price, $currency, $effectiveDate, $uom, $note)) {
                    $rowsUnchanged++;

                    continue;
                }

                $oldPrice = $existing?->price;

                if ($existing === null) {
                    ItemPrice::query()->create([
                        'item_code' => $itemCode,
                        'vendor_code' => $vendorCode,
                        'uom' => $uom !== '' ? $uom : null,
                        'price' => $price,
                        'currency' => $currency,
                        'effective_date' => $effectiveDate,
                        'source' => 'csv_import',
                        'note' => $note !== '' ? $note : null,
                        'last_import_id' => $import->id,
                        'updated_by' => $actor->id,
                    ]);
                    $rowsCreated++;
                } else {
                    $existing->fill([
                        'uom' => $uom !== '' ? $uom : null,
                        'price' => $price,
                        'currency' => $currency,
                        'effective_date' => $effectiveDate,
                        'source' => 'csv_import',
                        'note' => $note !== '' ? $note : null,
                        'last_import_id' => $import->id,
                        'updated_by' => $actor->id,
                    ]);
                    $existing->save();
                    $rowsUpdated++;
                }

                ItemPriceHistory::query()->create([
                    'item_code' => $itemCode,
                    'vendor_code' => $vendorCode,
                    'old_price' => $oldPrice,
                    'new_price' => $price,
                    'currency' => $currency,
                    'source' => 'csv_import',
                    'effective_date' => $effectiveDate,
                    'import_id' => $import->id,
                    'changed_by' => $actor->id,
                    'note' => $note !== '' ? $note : null,
                ]);

                $affectedItemCodes[$itemCode] = true;
                }

                $import->fill([
                    'rows_total' => $rowsTotal,
                    'rows_created' => $rowsCreated,
                    'rows_updated' => $rowsUpdated,
                    'rows_unchanged' => $rowsUnchanged,
                    'rows_failed' => $rowsFailed,
                    'errors' => $errors !== [] ? $errors : null,
                ]);
                $import->save();
            });
        } finally {
            fclose($handle);
        }

        foreach (array_keys($affectedItemCodes) as $itemCode) {
            $this->pricingEstimator->forgetCache($itemCode);
        }

        return $import->fresh();
    }

    private function detectDelimiter(string $line): string
    {
        $semicolons = substr_count($line, ';');
        $commas = substr_count($line, ',');

        return $semicolons > $commas ? ';' : ',';
    }

    private function stripBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<string, int>
     */
    private function mapHeaderColumns(array $header): array
    {
        $map = [];
        foreach ($header as $index => $name) {
            $key = Str::lower(trim((string) $name));
            if ($key !== '') {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function pricesMatch(
        ItemPrice $existing,
        string $price,
        string $currency,
        ?string $effectiveDate,
        ?string $uom,
        ?string $note,
    ): bool {
        $existingEffective = $existing->effective_date?->format('Y-m-d');

        return number_format((float) $existing->price, 2, '.', '') === $price
            && (string) $existing->currency === $currency
            && $existingEffective === $effectiveDate
            && (string) ($existing->uom ?? '') === (string) ($uom ?? '')
            && (string) ($existing->note ?? '') === (string) ($note ?? '');
    }
}
