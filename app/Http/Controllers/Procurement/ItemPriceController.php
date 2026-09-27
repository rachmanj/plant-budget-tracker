<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ItemPrice;
use App\Models\ItemPriceImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ItemPriceController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    private const IMPORT_HISTORY_PER_PAGE = 15;

    private const CSV_TEMPLATE_HEADER = 'item_code,vendor_code,uom,price,effective_date,note';

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('item_price.view'), 403);

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $search = trim((string) $request->input('search', ''));
        $query = ItemPrice::query()
            ->with('updatedByUser:id,name')
            ->orderByDesc('updated_at')
            ->orderBy('item_code');

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('item_code', 'like', '%'.$search.'%')
                    ->orWhere('vendor_code', 'like', '%'.$search.'%');
            });
        }

        $itemPrices = $query->paginate($perPage)->withQueryString();

        $itemPrices->getCollection()->transform(fn (ItemPrice $row) => [
            'id' => $row->id,
            'item_code' => $row->item_code,
            'vendor_code' => $row->vendor_code,
            'uom' => $row->uom,
            'price' => (string) $row->price,
            'currency' => $row->currency,
            'effective_date' => $row->effective_date?->format('Y-m-d'),
            'source' => $row->source,
            'updated_at' => $row->updated_at?->toIso8601String(),
            'updated_by_name' => $row->updatedByUser?->name,
        ]);

        $importHistory = ItemPriceImport::query()
            ->with('importedByUser:id,name')
            ->orderByDesc('imported_at')
            ->paginate(self::IMPORT_HISTORY_PER_PAGE, ['*'], 'import_page')
            ->withQueryString();

        $importHistory->getCollection()->transform(fn (ItemPriceImport $row) => [
            'id' => $row->id,
            'original_name' => $row->original_name,
            'imported_by_name' => $row->importedByUser?->name,
            'imported_at' => $row->imported_at?->toIso8601String(),
            'rows_total' => $row->rows_total,
            'rows_created' => $row->rows_created,
            'rows_updated' => $row->rows_updated,
            'rows_unchanged' => $row->rows_unchanged,
            'rows_failed' => $row->rows_failed,
            'errors' => $row->errors ?? [],
        ]);

        return Inertia::render('Procurement/ItemPrices/Index', [
            'itemPrices' => $itemPrices,
            'importHistory' => $importHistory,
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
            'perPage' => $perPage,
            'can' => [
                'import' => $request->user()->can('item_price.import'),
            ],
            'csvTemplateUrl' => route('procurement.item-prices.template'),
        ]);
    }

    public function downloadTemplate(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('item_price.view'), 403);

        return response()->streamDownload(
            function () {
                echo self::CSV_TEMPLATE_HEADER."\n";
            },
            'item-price-import-template.csv',
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }
}
