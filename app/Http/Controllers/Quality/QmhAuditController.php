<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhAudit;
use App\Models\QmhAuditTemuan;
use App\Models\User;
use App\Services\Quality\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QmhAuditController extends Controller
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $filters = validator($request->only(['search', 'audit_type', 'status', 'from', 'to']), [
            'search' => ['nullable', 'string'],
            'audit_type' => ['nullable', 'in:internal,eksternal,surveillance'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,closed,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ])->validate();

        $audits = QmhAudit::query()
            ->withCount('temuans')
            ->search($filters['search'] ?? null)
            ->when(isset($filters['audit_type']), fn ($query) => $query->where('audit_type', $filters['audit_type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('scheduled_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('scheduled_at', '<=', $filters['to']))
            ->when(! $this->canViewAll($user), function ($query) use ($user) {
                $query->where(function ($subquery) use ($user): void {
                    $subquery
                        ->where('created_by', $user->id)
                        ->orWhereHas('auditAuditors', fn ($auditorQuery) => $auditorQuery->where('user_id', $user->id));
                });
            })
            ->orderByDesc('scheduled_at')
            ->paginate(15)
            ->appends($request->query());

        return view('quality.audit.index', [
            'audits' => $audits,
            'canCreate' => $this->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        return view('quality.audit.create', [
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'audit_type' => ['required', 'in:internal,eksternal,surveillance'],
            'scope' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'auditors' => ['nullable', 'array'],
            'auditors.*' => ['integer', 'exists:users,id'],
            'auditors_json' => ['nullable', 'array'],
            'auditors_json.*' => ['integer', 'exists:users,id'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,closed,cancelled'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $audit = DB::transaction(function () use ($validated, $user): QmhAudit {
            $audit = QmhAudit::query()->create([
                'title' => $validated['title'],
                'audit_type' => $validated['audit_type'],
                'scope' => $validated['scope'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'status' => $validated['status'] ?? 'draft',
                'migration_phase' => 'pivot_only',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $audit->syncAuditors($this->extractAuditorIds($validated), $user->id);

            return $audit;
        });

        $this->auditTrailService->log(
            tableName: 'qmh_audits',
            recordId: $audit->id,
            action: 'CREATE',
            newValues: $audit->toArray(),
            changedBy: (int) $user->id,
            reason: 'Pembuatan audit QMH'
        );

        return redirect()->route('quality.audit.show', $audit)->with('success', 'Audit QMH berhasil dibuat.');
    }

    public function show(Request $request, QmhAudit $audit): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canViewAudit($user, $audit), 403);

        $audit->load(['creator', 'temuans.creator', 'temuans.updater', 'auditors:id,name']);

        return view('quality.audit.show', [
            'audit' => $audit,
            'canManage' => $this->canEditAudit($user, $audit),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function update(Request $request, QmhAudit $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditAudit($user, $audit), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'audit_type' => ['required', 'in:internal,eksternal,surveillance'],
            'scope' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'auditors' => ['nullable', 'array'],
            'auditors.*' => ['integer', 'exists:users,id'],
            'auditors_json' => ['nullable', 'array'],
            'auditors_json.*' => ['integer', 'exists:users,id'],
            'status' => ['required', 'in:draft,scheduled,in_progress,closed,cancelled'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        $before = $audit->toArray();

        DB::transaction(function () use ($audit, $validated, $user): void {
            $audit->fill([
                'title' => $validated['title'],
                'audit_type' => $validated['audit_type'],
                'scope' => $validated['scope'] ?? null,
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $user->id,
            ]);
            $audit->save();

            $audit->syncAuditors($this->extractAuditorIds($validated), $user->id);
        });

        $this->auditTrailService->log(
            tableName: 'qmh_audits',
            recordId: $audit->id,
            action: 'UPDATE',
            oldValues: $before,
            newValues: $audit->fresh()?->toArray(),
            changedBy: (int) $user->id,
            reason: 'Pembaruan audit QMH'
        );

        return redirect()->route('quality.audit.show', $audit)->with('success', 'Audit QMH berhasil diperbarui.');
    }

    public function destroy(Request $request, QmhAudit $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canDeleteAudit($user, $audit), 403);

        $audit->delete();

        return redirect()->route('quality.audit.index')->with('success', 'Audit QMH berhasil dihapus.');
    }

    public function storeTemuan(Request $request, QmhAudit $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditAudit($user, $audit), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'severity' => ['required', 'in:minor,major,kritis'],
            'corrective_action' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:open,in_progress,resolved,closed'],
        ])->validate();

        QmhAuditTemuan::query()->create([
            'audit_id' => $audit->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'severity' => $validated['severity'],
            'corrective_action' => $validated['corrective_action'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => $validated['status'] ?? 'open',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $latestTemuan = $audit->temuans()->latest('id')->first();
        if ($latestTemuan) {
            $this->auditTrailService->log(
                tableName: 'qmh_audit_temuans',
                recordId: $latestTemuan->id,
                action: 'CREATE',
                newValues: $latestTemuan->toArray(),
                changedBy: (int) $user->id,
                reason: 'Tambah temuan audit'
            );
        }

        return redirect()->route('quality.audit.show', $audit)->with('success', 'Temuan audit berhasil ditambahkan.');
    }

    public function updateTemuan(Request $request, QmhAudit $audit, QmhAuditTemuan $temuan): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditAudit($user, $audit), 403);
        abort_unless((int) $temuan->audit_id === (int) $audit->id, 404);

        $validated = validator($request->all(), [
            'corrective_action' => ['nullable', 'string'],
            'status' => ['required', 'in:open,in_progress,resolved,closed'],
        ])->validate();

        $before = $temuan->toArray();

        DB::transaction(function () use ($temuan, $validated, $user) {
            $temuan->corrective_action = $validated['corrective_action'] ?? $temuan->corrective_action;
            $temuan->status = $validated['status'];
            $temuan->updated_by = $user->id;

            if ($validated['status'] === 'resolved' && $temuan->resolved_at === null) {
                $temuan->resolved_at = now();
            }

            if ($validated['status'] === 'closed' && $temuan->closed_at === null) {
                $temuan->closed_at = now();
            }

            $temuan->save();
        });

        $this->auditTrailService->log(
            tableName: 'qmh_audit_temuans',
            recordId: $temuan->id,
            action: 'UPDATE',
            oldValues: $before,
            newValues: $temuan->fresh()?->toArray(),
            changedBy: (int) $user->id,
            reason: 'Pembaruan temuan audit'
        );

        return redirect()->route('quality.audit.show', $audit)->with('success', 'Temuan audit berhasil diperbarui.');
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.audit.view', 'qmh.audit.view.all', 'qmh.view']);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.audit.view.all', 'qmh.view']);
    }

    private function canCreate(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.audit.create', 'qmh.audit.create.all', 'qmh.create']);
    }

    private function canEditAudit(User $user, QmhAudit $audit): bool
    {
        if ($user->hasAnyPermission(['qmh.audit.edit', 'qmh.audit.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $audit->created_by === (int) $user->id;
    }

    private function canDeleteAudit(User $user, QmhAudit $audit): bool
    {
        if ($user->hasAnyPermission(['qmh.audit.delete', 'qmh.audit.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $audit->created_by === (int) $user->id;
    }

    private function canViewAudit(User $user, QmhAudit $audit): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        if ($audit->auditAuditors()->where('user_id', $user->id)->exists()) {
            return true;
        }

        return (int) $audit->created_by === (int) $user->id;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, int|string>
     */
    private function extractAuditorIds(array $validated): array
    {
        $auditors = $validated['auditors'] ?? null;

        if (! is_array($auditors)) {
            $auditors = $validated['auditors_json'] ?? [];
        }

        return is_array($auditors) ? $auditors : [];
    }
}
