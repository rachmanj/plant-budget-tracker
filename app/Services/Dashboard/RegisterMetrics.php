<?php

namespace App\Services\Dashboard;

use App\Models\SapPurchaseOrder;
use App\Models\SapPurchaseOrderLine;
use App\Models\SapPurchaseRequest;
use App\Models\SapSupplier;
use App\Models\SapSyncLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterMetrics
{
    public function for(?User $user, string $projectCode): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! $user->can('reports.view') && ! $user->can('procurement.view')) {
            return null;
        }

        return [
            'totals' => $this->totals(),
            'monthly' => $this->monthlySeries(),
            'projects' => $this->topProjects(),
            'recent' => $this->recentPurchaseOrders(),
            'health' => $this->health(),
        ];
    }

    /**
     * @return array{
     *     po_docs: int,
     *     po_value: string,
     *     pr_docs: int,
     *     pr_value: string,
     *     po_lines: int,
     *     suppliers: int
     * }
     */
    private function totals(): array
    {
        $po = SapPurchaseOrder::query()
            ->selectRaw('COUNT(*) as doc_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as amount_sum')
            ->first();

        $pr = SapPurchaseRequest::query()
            ->selectRaw('COUNT(*) as doc_count')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as amount_sum')
            ->first();

        return [
            'po_docs' => (int) ($po?->doc_count ?? 0),
            'po_value' => $this->money($po?->amount_sum ?? 0),
            'pr_docs' => (int) ($pr?->doc_count ?? 0),
            'pr_value' => $this->money($pr?->amount_sum ?? 0),
            'po_lines' => (int) SapPurchaseOrderLine::query()->count(),
            'suppliers' => (int) SapSupplier::query()->count(),
        ];
    }

    /**
     * @return list<array{month: string, label: string, po_docs: int, pr_docs: int, po_value: string}>
     */
    private function monthlySeries(): array
    {
        $monthKeys = [];
        $cursor = now()->copy()->startOfMonth()->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $monthKeys[] = $cursor->format('Y-m');
            $cursor = $cursor->copy()->addMonth();
        }

        $rangeStart = now()->copy()->startOfMonth()->subMonths(5)->toDateString();
        $rangeEnd = now()->copy()->endOfMonth()->toDateString();

        $poByMonth = SapPurchaseOrder::query()
            ->whereBetween('doc_date', [$rangeStart, $rangeEnd])
            ->selectRaw("DATE_FORMAT(doc_date, '%Y-%m') as month_key")
            ->selectRaw('COUNT(*) as po_docs')
            ->selectRaw('COALESCE(SUM(total_amount), 0) as po_value')
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');

        $prByMonth = SapPurchaseRequest::query()
            ->whereBetween('doc_date', [$rangeStart, $rangeEnd])
            ->selectRaw("DATE_FORMAT(doc_date, '%Y-%m') as month_key")
            ->selectRaw('COUNT(*) as pr_docs')
            ->groupBy('month_key')
            ->get()
            ->keyBy('month_key');

        $rows = [];

        foreach ($monthKeys as $monthKey) {
            $month = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
            $poRow = $poByMonth->get($monthKey);
            $prRow = $prByMonth->get($monthKey);

            $rows[] = [
                'month' => $monthKey,
                'label' => $month->locale('en')->translatedFormat('M Y'),
                'po_docs' => (int) ($poRow?->po_docs ?? 0),
                'pr_docs' => (int) ($prRow?->pr_docs ?? 0),
                'po_value' => $this->money($poRow?->po_value ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{code: string, docs: int}>
     */
    private function topProjects(): array
    {
        return SapPurchaseOrder::query()
            ->whereNotNull('project_code')
            ->where('project_code', '!=', '')
            ->selectRaw('project_code as code')
            ->selectRaw('COUNT(*) as docs')
            ->groupBy('project_code')
            ->orderByDesc('docs')
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'code' => (string) $row->code,
                'docs' => (int) $row->docs,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentPurchaseOrders(): array
    {
        return SapPurchaseOrder::query()
            ->whereNotNull('project_code')
            ->where('project_code', '!=', '')
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'doc_num',
                'vendor_code',
                'vendor_name',
                'dept_name',
                'project_code',
                'doc_date',
                'total_amount',
                'delivery_status',
            ])
            ->map(fn (SapPurchaseOrder $order) => [
                'doc_num' => (string) $order->doc_num,
                'vendor_code' => (string) $order->vendor_code,
                'vendor_name' => (string) ($order->vendor_name ?? ''),
                'dept_name' => (string) ($order->dept_name ?? ''),
                'project_code' => (string) $order->project_code,
                'doc_date' => $order->doc_date?->format('Y-m-d'),
                'total_amount' => $this->money($order->total_amount),
                'delivery_status' => (string) ($order->delivery_status ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     last_sync_status: string|null,
     *     last_sync_at: string|null,
     *     failed_jobs: int,
     *     latest_doc_date: string|null
     * }
     */
    private function health(): array
    {
        $lastSync = SapSyncLog::query()
            ->where('ref_type', 'procurement_register')
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        $lastSyncAt = null;
        if ($lastSync?->completed_at !== null) {
            $lastSyncAt = $lastSync->completed_at
                ->locale('en')
                ->translatedFormat('d M Y H:i');
        }

        $latestDoc = SapPurchaseOrder::query()->max('doc_date');
        $latestDocDate = null;
        if ($latestDoc !== null) {
            $latestDocDate = Carbon::parse($latestDoc)->locale('en')->translatedFormat('d M Y');
        }

        return [
            'last_sync_status' => $lastSync?->status,
            'last_sync_at' => $lastSyncAt,
            'failed_jobs' => (int) DB::table('failed_jobs')->count(),
            'latest_doc_date' => $latestDocDate,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }
}