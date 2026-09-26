<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\DocumentAttachment;
use App\Models\DocumentComment;
use App\Models\DocumentCommentMention;
use App\Models\DocumentFollow;
use App\Models\ProjectCache;
use App\Models\SapPurchaseOrder;
use App\Models\User;
use App\Support\ProjectContext;
use App\Models\SapPurchaseRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseOrderController extends Controller
{
    private const PER_PAGE_OPTIONS = [25, 50, 100];

    /** @var array<string, string> */
    private const DEPARTMENT_OPTIONS = [
        '40' => 'Plant',
        '200' => 'Logistic & Warehouse',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $user = $request->user();
        $forcedProjectCode = null;
        $projectScope = null;
        if (! ProjectContext::allowSwitch($user)) {
            $projectScope = is_string($user->project_code_scope) && $user->project_code_scope !== ''
                ? $user->project_code_scope
                : null;
            $forcedProjectCode = $projectScope;
        }

        $filtered = $this->filteredQuery($request, $forcedProjectCode);

        $summaryRow = (clone $filtered)
            ->selectRaw('COUNT(*) as document_count, COALESCE(SUM(total_amount), 0) as total_amount')
            ->first();

        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 25;
        }

        $followedIds = DocumentFollow::query()
            ->where('followable_type', 'purchase_order')
            ->where('user_id', $user?->id)
            ->pluck('followable_id')
            ->flip();

        $purchaseOrders = (clone $filtered)
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(function (SapPurchaseOrder $order) use ($followedIds) {
                $data = $order->toArray();
                $data['isFollowed'] = $followedIds->has($order->id);

                return $data;
            });

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'q' => $request->input('q'),
                'project_code' => $forcedProjectCode ?? $request->input('project_code'),
                'dept_code' => $request->input('dept_code'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'delivery_status' => $request->input('delivery_status'),
                'origin' => $request->input('origin'),
                'following' => $request->boolean('following'),
                'per_page' => $perPage,
            ],
            'summary' => [
                'document_count' => (int) ($summaryRow->document_count ?? 0),
                'total_amount' => (string) ($summaryRow->total_amount ?? '0.00'),
            ],
            'projects' => $this->projectOptions($user),
            'projectScope' => $projectScope,
            'departments' => collect(self::DEPARTMENT_OPTIONS)
                ->map(fn (string $name, string $code) => ['dept_code' => $code, 'dept_name' => $name])
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, SapPurchaseOrder $sapPurchaseOrder): Response
    {
        abort_unless($request->user()?->can('procurement.view'), 403);

        $user = $request->user();
        $this->assertUserCanAccessPurchaseOrder($user, $sapPurchaseOrder);

        $sapPurchaseOrder->load([
            'lines' => fn ($query) => $query->orderBy('line_num')->orderBy('vis_order'),
            'plantRequest',
        ]);

        $relatedPurchaseRequest = null;
        if ($sapPurchaseOrder->pr_no !== null && $sapPurchaseOrder->pr_no !== '') {
            $relatedPurchaseRequest = SapPurchaseRequest::query()
                ->where('doc_num', $sapPurchaseOrder->pr_no)
                ->first();
        }

        $isFollowed = DocumentFollow::query()
            ->where('followable_type', 'purchase_order')
            ->where('followable_id', $sapPurchaseOrder->id)
            ->where('user_id', $user?->id)
            ->exists();

        $followerCount = DocumentFollow::query()
            ->where('followable_type', 'purchase_order')
            ->where('followable_id', $sapPurchaseOrder->id)
            ->count();

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'purchaseOrder' => $sapPurchaseOrder,
            'relatedPurchaseRequest' => $relatedPurchaseRequest,
            'attachments' => $this->attachmentPayloads($sapPurchaseOrder),
            'comments' => $this->commentPayloads($sapPurchaseOrder, $user),
            'mentionableUsers' => $this->mentionableUsers(),
            'isFollowed' => $isFollowed,
            'followerCount' => $followerCount,
            'can' => [
                'attach' => (bool) $request->user()?->can('po.attach'),
                'comment' => (bool) $request->user()?->can('po.comment'),
            ],
        ]);
    }

    public function storeAttachment(Request $request, SapPurchaseOrder $sapPurchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('po.attach'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,xlsx,xls,doc,docx,jpg,jpeg,png'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $baseName) ?: 'file';
        $safeName = $extension !== '' ? $safeBase.'.'.$extension : $safeBase;
        $storedFileName = Str::uuid()->toString().'-'.$safeName;
        $storedPath = 'document-attachments/purchase_order/'.$sapPurchaseOrder->id.'/'.$storedFileName;

        $content = file_get_contents($file->getRealPath());
        Storage::disk('local')->put($storedPath, $content);

        DocumentAttachment::create([
            'attachable_type' => 'purchase_order',
            'attachable_id' => $sapPurchaseOrder->id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash('sha256', $content),
            'uploaded_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Attachment uploaded successfully.');
    }

    public function downloadAttachment(
        Request $request,
        SapPurchaseOrder $sapPurchaseOrder,
        DocumentAttachment $attachment,
    ): StreamedResponse {
        abort_unless($request->user()?->can('procurement.view'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $attachment = $this->resolvePoAttachment($sapPurchaseOrder, $attachment);

        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name);
    }

    public function destroyAttachment(
        Request $request,
        SapPurchaseOrder $sapPurchaseOrder,
        DocumentAttachment $attachment,
    ): RedirectResponse {
        abort_unless($request->user()?->can('po.attach'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $attachment = $this->resolvePoAttachment($sapPurchaseOrder, $attachment);

        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    public function storeComment(Request $request, SapPurchaseOrder $sapPurchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('po.comment'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $comment = DocumentComment::create([
            'commentable_type' => 'purchase_order',
            'commentable_id' => $sapPurchaseOrder->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $this->syncCommentMentions($comment, $validated['body']);

        return back()->with('success', 'Comment posted.');
    }

    public function destroyComment(
        Request $request,
        SapPurchaseOrder $sapPurchaseOrder,
        DocumentComment $comment,
    ): RedirectResponse {
        abort_unless($request->user()?->can('procurement.view'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $comment = $this->resolvePoComment($sapPurchaseOrder, $comment);

        $user = $request->user();
        abort_unless(
            $comment->user_id === $user->id || $user->can('user.manage'),
            403
        );

        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }

    public function toggleFollow(Request $request, SapPurchaseOrder $sapPurchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('procurement.view'), 403);
        $this->assertUserCanAccessPurchaseOrder($request->user(), $sapPurchaseOrder);

        $userId = $request->user()->id;
        $existing = DocumentFollow::query()
            ->where('followable_type', 'purchase_order')
            ->where('followable_id', $sapPurchaseOrder->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return back()->with('success', 'You are no longer following this purchase order.');
        }

        DocumentFollow::create([
            'followable_type' => 'purchase_order',
            'followable_id' => $sapPurchaseOrder->id,
            'user_id' => $userId,
        ]);

        return back()->with('success', 'You are now following this purchase order.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attachmentPayloads(SapPurchaseOrder $sapPurchaseOrder): array
    {
        return DocumentAttachment::query()
            ->where('attachable_type', 'purchase_order')
            ->where('attachable_id', $sapPurchaseOrder->id)
            ->with('uploader:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DocumentAttachment $attachment) => [
                'id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'size' => $attachment->size,
                'mime' => $attachment->mime,
                'uploaded_by_name' => $attachment->uploader?->name,
                'created_at' => $attachment->created_at?->toIso8601String(),
                'download_url' => route('procurement.purchase-orders.attachments.download', [
                    'sapPurchaseOrder' => $sapPurchaseOrder->id,
                    'attachment' => $attachment->id,
                ]),
            ])
            ->values()
            ->all();
    }

    private function resolvePoAttachment(
        SapPurchaseOrder $sapPurchaseOrder,
        DocumentAttachment $attachment,
    ): DocumentAttachment {
        if ($attachment->attachable_type !== 'purchase_order'
            || (int) $attachment->attachable_id !== (int) $sapPurchaseOrder->id) {
            abort(404);
        }

        return $attachment;
    }

    private function resolvePoComment(
        SapPurchaseOrder $sapPurchaseOrder,
        DocumentComment $comment,
    ): DocumentComment {
        if ($comment->commentable_type !== 'purchase_order'
            || (int) $comment->commentable_id !== (int) $sapPurchaseOrder->id) {
            abort(404);
        }

        return $comment;
    }

    private function assertUserCanAccessPurchaseOrder(?User $user, SapPurchaseOrder $sapPurchaseOrder): void
    {
        if ($user !== null && ProjectContext::allowSwitch($user)) {
            return;
        }

        abort_unless(
            $user !== null
                && is_string($user->project_code_scope)
                && $user->project_code_scope !== ''
                && $sapPurchaseOrder->project_code === $user->project_code_scope,
            403
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function commentPayloads(SapPurchaseOrder $sapPurchaseOrder, ?User $viewer): array
    {
        return DocumentComment::query()
            ->where('commentable_type', 'purchase_order')
            ->where('commentable_id', $sapPurchaseOrder->id)
            ->with(['user:id,name', 'mentions.user:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (DocumentComment $comment) use ($viewer) {
                $mentionedNames = $comment->mentions
                    ->map(fn (DocumentCommentMention $mention) => $mention->user?->name)
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $comment->id,
                    'author_name' => $comment->user?->name,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'body' => $comment->body,
                    'mentioned_names' => $mentionedNames,
                    'can_delete' => $viewer !== null
                        && ($comment->user_id === $viewer->id || $viewer->can('user.manage')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function mentionableUsers(): array
    {
        return User::query()
            ->where('is_active', true)
            ->permission('procurement.view')
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function syncCommentMentions(DocumentComment $comment, string $body): void
    {
        if (! preg_match_all('/@([^\s@]+)/u', $body, $matches)) {
            return;
        }

        $tokens = $matches[1];
        $mentionedUserIds = [];

        foreach ($tokens as $token) {
            if (count($mentionedUserIds) >= 10) {
                break;
            }

            $user = $this->resolveMentionUser($token);
            if ($user === null || in_array($user->id, $mentionedUserIds, true)) {
                continue;
            }

            $mentionedUserIds[] = $user->id;
        }

        foreach ($mentionedUserIds as $userId) {
            DocumentCommentMention::create([
                'document_comment_id' => $comment->id,
                'user_id' => $userId,
            ]);
        }
    }

    private function resolveMentionUser(string $token): ?User
    {
        $needle = strtolower($token);

        return User::query()
            ->where('is_active', true)
            ->permission('procurement.view')
            ->where(function (Builder $query) use ($needle) {
                $query->whereRaw('LOWER(email) = ?', [$needle])
                    ->orWhereRaw('LOWER(SUBSTRING_INDEX(email, \'@\', 1)) = ?', [$needle]);
            })
            ->first();
    }

    private function filteredQuery(Request $request, ?string $forcedProjectCode = null): Builder
    {
        $query = SapPurchaseOrder::query();

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $outer) use ($like, $search) {
                $outer->where('pr_no', 'like', $like)
                    ->orWhere('vendor_name', 'like', $like)
                    ->orWhereHas('lines', fn (Builder $lines) => $lines->where('item_code', 'like', $like));

                if (ctype_digit($search)) {
                    $outer->orWhere('doc_num', (int) $search);
                } else {
                    $outer->orWhereRaw('CAST(doc_num AS CHAR) LIKE ?', [$like]);
                }
            });
        }

        if (is_string($forcedProjectCode) && $forcedProjectCode !== '') {
            $query->where('project_code', $forcedProjectCode);
        } else {
            $projectCode = $request->input('project_code');
            if (is_string($projectCode) && $projectCode !== '') {
                $query->where('project_code', $projectCode);
            }
        }

        $deptCode = $request->input('dept_code');
        if (is_string($deptCode) && $deptCode !== '') {
            $query->where('dept_code', $deptCode);
        }

        $from = $request->input('from');
        $to = $request->input('to');
        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            $query->docDateBetween($from, $to);
        } elseif (is_string($from) && $from !== '') {
            $query->whereDate('doc_date', '>=', $from);
        } elseif (is_string($to) && $to !== '') {
            $query->whereDate('doc_date', '<=', $to);
        }

        $deliveryStatus = $request->input('delivery_status');
        if (is_string($deliveryStatus) && $deliveryStatus !== '') {
            $query->where('delivery_status', $deliveryStatus);
        }

        $origin = $request->input('origin');
        if (in_array($origin, ['sap', 'pmb'], true)) {
            $query->where('origin', $origin);
        }

        if ($request->boolean('following')) {
            $userId = $request->user()?->id;
            if ($userId === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('id', DocumentFollow::query()
                    ->where('followable_type', 'purchase_order')
                    ->where('user_id', $userId)
                    ->select('followable_id'));
            }
        }

        return $query;
    }

    /**
     * @return array<int, array{project_code: string, project_name: string}>
     */
    private function projectOptions(?User $user): array
    {
        if ($user !== null && ! ProjectContext::allowSwitch($user)) {
            $scope = $user->project_code_scope;
            if (! is_string($scope) || $scope === '') {
                return [];
            }

            $name = ProjectCache::query()
                ->where('project_code', $scope)
                ->value('project_name');

            return [[
                'project_code' => $scope,
                'project_name' => (string) ($name ?? $scope),
            ]];
        }

        $codes = SapPurchaseOrder::query()
            ->whereNotNull('project_code')
            ->distinct()
            ->orderBy('project_code')
            ->pluck('project_code');

        if ($codes->isEmpty()) {
            return [];
        }

        $names = ProjectCache::query()
            ->whereIn('project_code', $codes)
            ->pluck('project_name', 'project_code');

        return $codes
            ->map(fn (string $code) => [
                'project_code' => $code,
                'project_name' => (string) ($names[$code] ?? $code),
            ])
            ->values()
            ->all();
    }
}
