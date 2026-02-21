<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhRapatActionItem;
use App\Models\User;
use App\Services\Quality\AuditTrailService;
use App\Services\Quality\QmhActionItemStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QmhActionItemController extends Controller
{
    public function __construct(
        private readonly QmhActionItemStateMachine $stateMachine,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = validator($request->all(), [
            'status' => ['nullable', 'in:open,in_progress,resolved,verified,closed,overdue'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'rapat_id' => ['nullable', 'integer', 'exists:qmh_rapats,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $query = QmhRapatActionItem::query()->with(['rapat:id,title', 'assignee:id,name']);

        if (! $user->hasAnyPermission(['qmh.rapat.view.all', 'qmh.view'])) {
            $query->where(function ($q) use ($user): void {
                $q->where('created_by', $user->id)->orWhere('assignee_id', $user->id);
            });
        }

        $query
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['assignee_id']), fn ($q) => $q->where('assignee_id', $validated['assignee_id']))
            ->when(isset($validated['rapat_id']), fn ($q) => $q->where('rapat_id', $validated['rapat_id']))
            ->orderByDesc('due_date')
            ->orderByDesc('id');

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 15)));
    }

    public function show(Request $request, QmhRapatActionItem $actionItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canAccessItem($user, $actionItem), 403);

        $actionItem->load(['rapat:id,title', 'assignee:id,name', 'creator:id,name', 'updater:id,name']);

        $dependencies = DB::table('qmh_action_item_dependencies')
            ->where('action_item_id', $actionItem->id)
            ->pluck('depends_on_action_item_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return response()->json([
            'data' => $actionItem,
            'dependencies' => $dependencies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $validated = validator($request->all(), [
            'rapat_id' => ['required', 'integer', 'exists:qmh_rapats,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ])->validate();

        $item = QmhRapatActionItem::query()->create([
            'rapat_id' => $validated['rapat_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'assignee_id' => $validated['assignee_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => QmhRapatActionItem::STATUS_OPEN,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->auditTrailService->log(
            tableName: 'qmh_rapat_action_items',
            recordId: $item->id,
            action: 'CREATE',
            newValues: $item->toArray(),
            changedBy: (int) $user->id,
            reason: 'Create action item API'
        );

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, QmhRapatActionItem $actionItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canMutateItem($user, $actionItem), 403);

        $validated = validator($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ])->validate();

        $before = $actionItem->toArray();
        $actionItem->fill($validated);
        $actionItem->updated_by = $user->id;
        $actionItem->save();

        $this->auditTrailService->log(
            tableName: 'qmh_rapat_action_items',
            recordId: $actionItem->id,
            action: 'UPDATE',
            oldValues: $before,
            newValues: $actionItem->fresh()?->toArray(),
            changedBy: (int) $user->id,
            reason: 'Update action item API'
        );

        return response()->json(['data' => $actionItem->fresh()]);
    }

    public function updateState(Request $request, QmhRapatActionItem $actionItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canMutateItem($user, $actionItem), 403);

        $validated = validator($request->all(), [
            'status' => ['required', 'in:in_progress,resolved,verified,closed,overdue'],
        ])->validate();

        abort_unless($this->canTransitionState($user, $actionItem, $validated['status']), 403);

        $before = $actionItem->toArray();

        try {
            $this->stateMachine->transition($actionItem, $validated['status'], (int) $user->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $this->auditTrailService->log(
            tableName: 'qmh_rapat_action_items',
            recordId: $actionItem->id,
            action: 'STATE_CHANGE',
            oldValues: $before,
            newValues: $actionItem->fresh()?->toArray(),
            changedBy: (int) $user->id,
            reason: 'State change action item API'
        );

        return response()->json(['data' => $actionItem->fresh()]);
    }

    public function addDependency(Request $request, QmhRapatActionItem $actionItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canMutateItem($user, $actionItem), 403);

        $validated = validator($request->all(), [
            'depends_on_action_item_id' => ['required', 'integer', 'exists:qmh_rapat_action_items,id'],
        ])->validate();

        $dependsOnId = (int) $validated['depends_on_action_item_id'];
        if ($dependsOnId === (int) $actionItem->id) {
            return response()->json(['message' => 'Action item tidak boleh bergantung pada dirinya sendiri.'], 422);
        }

        DB::table('qmh_action_item_dependencies')->updateOrInsert(
            [
                'action_item_id' => $actionItem->id,
                'depends_on_action_item_id' => $dependsOnId,
            ],
            [
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if ($this->hasCircularDependency((int) $actionItem->id)) {
            DB::table('qmh_action_item_dependencies')
                ->where('action_item_id', $actionItem->id)
                ->where('depends_on_action_item_id', $dependsOnId)
                ->delete();

            return response()->json(['message' => 'Circular dependency terdeteksi.'], 422);
        }

        return response()->json(['message' => 'Dependency berhasil ditambahkan.'], 201);
    }

    public function removeDependency(Request $request, QmhRapatActionItem $actionItem, int $dependency): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canMutateItem($user, $actionItem), 403);

        DB::table('qmh_action_item_dependencies')
            ->where('action_item_id', $actionItem->id)
            ->where('depends_on_action_item_id', $dependency)
            ->delete();

        return response()->json(['message' => 'Dependency berhasil dihapus.']);
    }

    public function dependencyGraph(Request $request, QmhRapatActionItem $actionItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canAccessItem($user, $actionItem), 403);

        $dependencies = DB::table('qmh_action_item_dependencies')
            ->where('action_item_id', $actionItem->id)
            ->pluck('depends_on_action_item_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $dependents = DB::table('qmh_action_item_dependencies')
            ->where('depends_on_action_item_id', $actionItem->id)
            ->pluck('action_item_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'action_item_id' => $actionItem->id,
            'dependencies' => $dependencies,
            'dependents' => $dependents,
        ]);
    }

    private function canAccessItem(User $user, QmhRapatActionItem $actionItem): bool
    {
        if ($user->hasAnyPermission(['qmh.rapat.view.all', 'qmh.view'])) {
            return true;
        }

        return (int) $actionItem->created_by === (int) $user->id || (int) $actionItem->assignee_id === (int) $user->id;
    }

    private function canMutateItem(User $user, QmhRapatActionItem $actionItem): bool
    {
        if ($user->hasAnyPermission(['qmh.rapat.edit', 'qmh.rapat.create.all', 'qmh.create'])) {
            return true;
        }

        if ((int) $actionItem->assignee_id === (int) $user->id) {
            return true;
        }

        return (int) $actionItem->created_by === (int) $user->id;
    }

    private function canTransitionState(User $user, QmhRapatActionItem $actionItem, string $targetStatus): bool
    {
        if ($targetStatus === QmhRapatActionItem::STATUS_VERIFIED) {
            return $user->hasAnyPermission(['action-item:verify', 'qmh.rapat.edit', 'qmh.rapat.create.all', 'qmh.create']);
        }

        if ($targetStatus === QmhRapatActionItem::STATUS_CLOSED) {
            if ($user->hasAnyPermission(['action-item:close', 'qmh.rapat.edit', 'qmh.rapat.create.all', 'qmh.create'])) {
                return true;
            }

            return (int) $actionItem->created_by === (int) $user->id;
        }

        if ((string) $actionItem->status === QmhRapatActionItem::STATUS_OVERDUE
            && in_array($targetStatus, [QmhRapatActionItem::STATUS_IN_PROGRESS, QmhRapatActionItem::STATUS_RESOLVED], true)) {
            if ($user->hasAnyPermission(['action-item:reopen', 'qmh.rapat.edit', 'qmh.rapat.create.all', 'qmh.create'])) {
                return true;
            }

            return (int) $actionItem->assignee_id === (int) $user->id;
        }

        return true;
    }

    private function hasCircularDependency(int $rootActionItemId): bool
    {
        $graph = [];

        DB::table('qmh_action_item_dependencies')
            ->select('action_item_id', 'depends_on_action_item_id')
            ->orderBy('action_item_id')
            ->chunk(500, function ($rows) use (&$graph): void {
                foreach ($rows as $row) {
                    $graph[(int) $row->action_item_id][] = (int) $row->depends_on_action_item_id;
                }
            });

        return $this->dfsDetectCycle($rootActionItemId, $graph, [], []);
    }

    /**
     * @param  array<int, array<int, int>>  $graph
     * @param  array<int, bool>  $visited
     * @param  array<int, bool>  $stack
     */
    private function dfsDetectCycle(int $node, array $graph, array $visited, array $stack): bool
    {
        if (($stack[$node] ?? false) === true) {
            return true;
        }

        if (($visited[$node] ?? false) === true) {
            return false;
        }

        $visited[$node] = true;
        $stack[$node] = true;

        foreach (($graph[$node] ?? []) as $neighbor) {
            if ($this->dfsDetectCycle($neighbor, $graph, $visited, $stack)) {
                return true;
            }
        }

        $stack[$node] = false;

        return false;
    }
}
