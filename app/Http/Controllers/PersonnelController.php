<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Investigator;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PersonnelController extends Controller
{
    public function __construct(
        private readonly RoleCatalog $roleCatalog
    ) {}

    /**
     * Display the unified personnel management page.
     */
    public function index(Request $request): View
    {
        // Check permissions - need access to either user management or investigator management
        if (! Gate::allows('manage-users') && ! Gate::allows('investigators.view')) {
            abort(403);
        }

        $activeTab = $request->query('tab', 'staff');

        // If user only has access to one tab, force that tab
        if (! Gate::allows('manage-users') && Gate::allows('investigators.view')) {
            $activeTab = 'penyidik';
        } elseif (Gate::allows('manage-users') && ! Gate::allows('investigators.view')) {
            $activeTab = 'staff';
        }

        $data = [
            'activeTab' => $activeTab,
            'filters' => $request->only(['q', 'role', 'status', 'type', 'jurisdiction']),
        ];

        // Load data based on active tab to optimize performance
        if ($activeTab === 'staff') {
            $data = array_merge($data, $this->getStaffData($request));
        } else {
            $data = array_merge($data, $this->getInvestigatorData($request));
        }

        return view('personnel.index', $data);
    }

    /**
     * Get data for Staff tab
     */
    private function getStaffData(Request $request): array
    {
        if (! Gate::allows('manage-users')) {
            return [];
        }

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

        $staff = $query
            ->addSelect(['last_activity_at' => $lastActivitySub])
            ->withCasts(['last_activity_at' => 'datetime'])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $manageableRoles = $this->roleCatalog->staffRoles();
        $availableRoles = User::query()
            ->select('role')
            ->whereNotNull('role')
            ->distinct()
            ->orderBy('role')
            ->pluck('role')
            ->values()
            ->merge($manageableRoles)
            ->map(fn ($role) => is_string($role) ? $this->roleCatalog->normalize($role) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'staff' => $staff,
            'availableRoles' => $availableRoles,
            'manageableRoles' => $manageableRoles,
        ];
    }

    /**
     * Get data for Penyidik tab
     */
    private function getInvestigatorData(Request $request): array
    {
        if (! Gate::allows('investigators.view')) {
            return [];
        }

        $type = $request->string('type')->toString();
        $jurisdiction = $request->string('jurisdiction')->toString();
        $keyword = trim($request->string('q')->toString());

        $query = Investigator::query();

        if ($type === 'polri') {
            $query->where('is_polri', true);
        } elseif ($type === 'non_polri') {
            $query->where('is_polri', false);
        }

        if ($jurisdiction !== '') {
            $query->where('jurisdiction', $jurisdiction);
        }

        if ($keyword !== '') {
            $like = $this->likeOperator();
            $keyword = str_replace('%', '\\%', $keyword);

            $query->where(function ($builder) use ($like, $keyword) {
                $pattern = "%{$keyword}%";
                $builder->where('name', $like, $pattern)
                    ->orWhere('nrp', $like, $pattern)
                    ->orWhere('phone', $like, $pattern)
                    ->orWhere('email', $like, $pattern)
                    ->orWhere('jurisdiction', $like, $pattern);
            });
        }

        $investigators = $query
            ->withCount('testRequests')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $jurisdictions = Investigator::query()
            ->select('jurisdiction')
            ->whereNotNull('jurisdiction')
            ->distinct()
            ->orderBy('jurisdiction')
            ->pluck('jurisdiction')
            ->filter()
            ->values();

        return [
            'investigators' => $investigators,
            'jurisdictions' => $jurisdictions,
        ];
    }

    /**
     * Get the database specific case-insensitive LIKE operator.
     */
    private function likeOperator(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }
}
