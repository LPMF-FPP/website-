<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\Settings\SettingsWriter;
use App\Support\ActivityLogger;
use App\Support\RoleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnalystController extends Controller
{
    public function __construct(
        protected PermissionService $permissionService,
        protected RoleCatalog $roleCatalog,
        protected SettingsWriter $settingsWriter
    ) {
        $this->middleware(function ($request, $next) {
            Gate::authorize('manage-users');

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $keyword = trim($request->string('q')->toString());

        $query = User::query();

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($keyword !== '') {
            $like = $this->likeOperator();
            $keyword = str_replace('%', '\\%', $keyword);

            $query->where(function ($builder) use ($like, $keyword) {
                $pattern = "%{$keyword}%";

                $builder->where('name', $like, $pattern)
                    ->orWhere('email', $like, $pattern)
                    ->orWhere('nrp', $like, $pattern)
                    ->orWhere('nip', $like, $pattern);
            });
        }

        $lastActivitySub = ActivityLog::select('created_at')
            ->whereColumn('actor_user_id', 'users.id')
            ->latest('created_at')
            ->limit(1);

        $analysts = $query
            ->addSelect(['last_activity_at' => $lastActivitySub])
            ->withCasts(['last_activity_at' => 'datetime'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $availableRoles = $this->roleCatalog->allKnownRoles();

        return view('analysts.index', [
            'analysts' => $analysts,
            'roles' => $this->roleOptions(),
            'availableRoles' => $availableRoles,
            'filters' => [
                'role' => $role,
                'status' => $status,
                'q' => $keyword,
            ],
        ]);
    }

    public function show(User $analyst): View
    {
        $lastActivity = ActivityLog::where('actor_user_id', $analyst->id)
            ->latest()
            ->first()?->created_at;

        $recentLogs = ActivityLog::query()
            ->with(['actor:id,name', 'target:id,name'])
            ->where(function ($query) use ($analyst) {
                $query->where('actor_user_id', $analyst->id)
                    ->orWhere('target_user_id', $analyst->id);
            })
            ->latest()
            ->limit(10)
            ->get();

        // Get permission data for UI
        $permissionsData = $this->permissionService->getPermissionsForUI($analyst);
        $allModules = $this->permissionService->getAllModules();

        return view('analysts.show', [
            'analyst' => $analyst,
            'lastActivity' => $lastActivity,
            'roles' => $this->roleOptions($analyst->role),
            'recentLogs' => $recentLogs,
            'permissionsData' => $permissionsData,
            'allModules' => $allModules,
        ]);
    }

    public function logs(Request $request, User $analyst): View
    {
        $action = $request->string('action')->toString();
        $subjectType = $request->string('subject_type')->toString();
        $startDate = $request->string('start_date')->toString();
        $endDate = $request->string('end_date')->toString();

        $query = ActivityLog::query()
            ->where(function ($builder) use ($analyst) {
                $builder->where('actor_user_id', $analyst->id)
                    ->orWhere('target_user_id', $analyst->id);
            });

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        if ($startDate !== '') {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate !== '') {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query
            ->with(['actor:id,name', 'target:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $actions = ActivityLog::query()
            ->where(function ($builder) use ($analyst) {
                $builder->where('actor_user_id', $analyst->id)
                    ->orWhere('target_user_id', $analyst->id);
            })
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        $subjectTypes = ActivityLog::query()
            ->where(function ($builder) use ($analyst) {
                $builder->where('actor_user_id', $analyst->id)
                    ->orWhere('target_user_id', $analyst->id);
            })
            ->whereNotNull('subject_type')
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type');

        return view('analysts.logs', [
            'analyst' => $analyst,
            'logs' => $logs,
            'actions' => $actions,
            'subjectTypes' => $subjectTypes,
            'filters' => [
                'action' => $action,
                'subject_type' => $subjectType,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function updateRole(Request $request, User $analyst): RedirectResponse
    {
        if ($analyst->id === $request->user()->id) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role sendiri.']);
        }

        $validated = $request->validate([
            'role' => ['required', Rule::in($this->roleOptions($analyst->role))],
        ]);

        $previousRole = $analyst->role;

        if ($previousRole === $validated['role']) {
            return back()->with('success', 'Peran pengguna tidak berubah.');
        }

        $analyst->update(['role' => $validated['role']]);

        // Reset permissions to new role defaults
        $analyst->resetPermissionsToRole();

        ActivityLogger::log(
            'USER_ROLE_CHANGED',
            $analyst->id,
            $analyst,
            ['role' => $previousRole],
            ['role' => $validated['role']],
            [
                'from' => $previousRole,
                'to' => $validated['role'],
                'permissions_reset' => true,
            ]
        );

        return back()->with('success', 'Peran pengguna berhasil diperbarui. Permission telah direset ke default role baru.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role_name' => ['required', 'string', 'max:100'],
            'clone_from' => ['required', 'string'],
        ]);

        $newRole = $this->roleCatalog->normalize($validated['role_name']);
        if ($newRole === '') {
            return back()->withErrors([
                'role_name' => 'Nama role tidak valid. Gunakan huruf/angka, spasi, garis bawah, atau tanda minus.',
            ]);
        }

        $existingRoles = $this->roleCatalog->allKnownRoles();
        if (in_array($newRole, $existingRoles, true)) {
            return back()->withErrors([
                'role_name' => 'Role tersebut sudah ada.',
            ]);
        }

        $cloneFrom = $this->roleCatalog->normalize($validated['clone_from']);
        if (! in_array($cloneFrom, $existingRoles, true)) {
            return back()->withErrors([
                'clone_from' => 'Role sumber untuk menyalin permission tidak ditemukan.',
            ]);
        }

        DB::transaction(function () use ($newRole, $cloneFrom, $request): void {
            $permissionIds = RolePermission::query()
                ->where('role', $cloneFrom)
                ->pluck('permission_id')
                ->all();

            foreach ($permissionIds as $permissionId) {
                RolePermission::query()->firstOrCreate([
                    'role' => $newRole,
                    'permission_id' => $permissionId,
                ]);
            }

            $updatedRoles = collect($this->roleCatalog->staffRoles())
                ->push($newRole)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $this->settingsWriter->put([
                'security' => [
                    'available_roles' => $updatedRoles,
                ],
            ], 'USER_ROLE_TYPE_CREATED', $request->user());
        });

        return back()->with('success', 'Role baru berhasil ditambahkan: '.Str::of($newRole)->replace('_', ' ')->title());
    }

    public function updatePermissions(Request $request, User $analyst): RedirectResponse
    {
        if ($analyst->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat mengubah permission sendiri.']);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['boolean'],
        ]);

        // Get current permissions before update for logging
        $beforePermissions = $analyst->getAllPermissions()
            ->filter(fn ($p) => $p['is_custom'])
            ->pluck('has_access', 'name')
            ->toArray();

        // Sync permissions
        $this->permissionService->syncUserPermissions($analyst, $validated['permissions']);

        // Get updated permissions for logging
        $afterPermissions = $analyst->getAllPermissions()
            ->filter(fn ($p) => $p['is_custom'])
            ->pluck('has_access', 'name')
            ->toArray();

        // Log changes
        $granted = [];
        $revoked = [];

        foreach ($validated['permissions'] as $permissionId => $isGranted) {
            $permission = Permission::find($permissionId);
            if ($permission) {
                if ($isGranted) {
                    $granted[] = $permission->display_name;
                } else {
                    $revoked[] = $permission->display_name;
                }
            }
        }

        if (! empty($granted) || ! empty($revoked)) {
            ActivityLogger::log(
                'USER_PERMISSIONS_UPDATED',
                $analyst->id,
                $analyst,
                $beforePermissions,
                $afterPermissions,
                [
                    'granted' => $granted,
                    'revoked' => $revoked,
                ]
            );
        }

        return back()->with('success', 'Akses halaman berhasil diperbarui.');
    }

    public function resetPermissions(Request $request, User $analyst): RedirectResponse
    {
        if ($analyst->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat mereset permission sendiri.']);
        }

        // Get current custom permissions before reset for logging
        $beforePermissions = $analyst->getAllPermissions()
            ->filter(fn ($p) => $p['is_custom'])
            ->pluck('has_access', 'name')
            ->toArray();

        // Reset to role defaults
        $analyst->resetPermissionsToRole();

        if (! empty($beforePermissions)) {
            ActivityLogger::log(
                'USER_PERMISSIONS_RESET',
                $analyst->id,
                $analyst,
                $beforePermissions,
                null,
                [
                    'role' => $analyst->role,
                    'message' => 'Permission direset ke default role',
                ]
            );
        }

        return back()->with('success', 'Akses halaman berhasil direset ke default role.');
    }

    public function disable(Request $request, User $analyst): RedirectResponse
    {
        if ($analyst->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if (! $analyst->is_active) {
            return back()->with('success', 'Pengguna sudah nonaktif.');
        }

        $reason = $request->input('reason');
        $before = ['is_active' => $analyst->is_active];

        $analyst->forceFill(['is_active' => false])->save();
        DB::table('sessions')->where('user_id', $analyst->id)->delete();

        ActivityLogger::log(
            'USER_DISABLED',
            $analyst->id,
            $analyst,
            $before,
            ['is_active' => false],
            ['reason' => $reason]
        );

        return back()->with('success', 'Pengguna berhasil dinonaktifkan.');
    }

    public function enable(Request $request, User $analyst): RedirectResponse
    {
        if ($analyst->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat mengaktifkan akun sendiri.']);
        }

        if ($analyst->is_active) {
            return back()->with('success', 'Pengguna sudah aktif.');
        }

        $before = ['is_active' => $analyst->is_active];
        $analyst->forceFill(['is_active' => true])->save();

        ActivityLogger::log(
            'USER_ENABLED',
            $analyst->id,
            $analyst,
            $before,
            ['is_active' => true]
        );

        return back()->with('success', 'Pengguna berhasil diaktifkan.');
    }

    public function create(): View
    {
        return view('analysts.create', [
            'analyst' => new User,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $user = User::create($data);

        ActivityLogger::log(
            'USER_CREATED',
            $user->id,
            $user,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ]
        );

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data staff berhasil ditambahkan.');
    }

    public function edit(User $analyst): View
    {
        return view('analysts.edit', [
            'analyst' => $analyst,
            'roles' => $this->roleOptions($analyst->role),
        ]);
    }

    public function update(Request $request, User $analyst): RedirectResponse
    {
        $data = $this->validatedData($request, $analyst->id, $analyst->role);

        if ($analyst->id === $request->user()->id &&
            array_key_exists('role', $data) &&
            $data['role'] !== $analyst->role) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role sendiri.']);
        }

        $analyst->fill($data);
        $changes = $analyst->getDirty();

        if ($changes !== []) {
            $before = Arr::only($analyst->getOriginal(), array_keys($changes));
            $analyst->save();

            $logChanges = Arr::except($changes, ['password', 'remember_token']);
            $logBefore = Arr::except($before, ['password', 'remember_token']);

            if (array_key_exists('role', $logChanges)) {
                ActivityLogger::log(
                    'USER_ROLE_CHANGED',
                    $analyst->id,
                    $analyst,
                    ['role' => $logBefore['role'] ?? null],
                    ['role' => $logChanges['role']],
                    [
                        'from' => $logBefore['role'] ?? null,
                        'to' => $logChanges['role'],
                    ]
                );

                unset($logChanges['role'], $logBefore['role']);
            }

            if ($logChanges !== []) {
                ActivityLogger::log(
                    'USER_UPDATED',
                    $analyst->id,
                    $analyst,
                    $logBefore,
                    $logChanges,
                    ['email' => $analyst->email]
                );
            }
        }

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data staff berhasil diperbarui.');
    }

    public function destroy(User $analyst): RedirectResponse
    {
        if ($analyst->id === request()->user()?->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat menghapus akun sendiri.']);
        }

        $before = [
            'name' => $analyst->name,
            'email' => $analyst->email,
            'role' => $analyst->role,
            'is_active' => $analyst->is_active,
        ];

        DB::table('sessions')->where('user_id', $analyst->id)->delete();
        $analyst->delete();

        ActivityLogger::log(
            'USER_DELETED',
            $analyst->id,
            $analyst,
            $before,
            null
        );

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data staff berhasil dihapus.');
    }

    protected function validatedData(Request $request, ?int $analystId = null, ?string $currentRole = null): array
    {
        $passwordRule = ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'];
        if (! $analystId) {
            $passwordRule = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($analystId)],
            'role' => ['required', Rule::in($this->roleOptions($currentRole))],
            'title_prefix' => ['nullable', 'string', 'max:50'],
            'title_suffix' => ['nullable', 'string', 'max:50'],
            'rank' => ['nullable', 'string', 'max:100'],
            'nrp' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:50'],
            'password' => $passwordRule,
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ]);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        return $validated;
    }

    private function roleOptions(?string $currentRole = null): array
    {
        $roles = $this->roleCatalog->staffRoles();

        if ($currentRole) {
            $normalizedCurrentRole = $this->roleCatalog->normalize($currentRole);
            if ($normalizedCurrentRole !== '' && ! in_array($normalizedCurrentRole, $roles, true)) {
                $roles[] = $normalizedCurrentRole;
            }
        }

        return collect($roles)
            ->unique()
            ->values()
            ->all();
    }

    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
