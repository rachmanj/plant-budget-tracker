<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Services\Pricing\ItemPriceCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ItemPriceImportController extends Controller
{
    public function store(Request $request, ItemPriceCsvImporter $importer): JsonResponse
    {
        abort_unless($request->user()?->can('item_price.import'), 403);

        $request->validate([
            'file' => ['required', 'file'],
        ]);

        try {
            $import = $importer->import($request->file('file'), $request->user());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'id' => $import->id,
            'original_name' => $import->original_name,
            'rows_total' => $import->rows_total,
            'rows_created' => $import->rows_created,
            'rows_updated' => $import->rows_updated,
            'rows_unchanged' => $import->rows_unchanged,
            'rows_failed' => $import->rows_failed,
            'errors' => $import->errors,
            'imported_at' => $import->imported_at?->toIso8601String(),
        ]);
    }
}
