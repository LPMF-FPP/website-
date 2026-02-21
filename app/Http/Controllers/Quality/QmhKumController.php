<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhKum;
use App\Models\User;
use App\Services\Quality\KumActionItemGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class QmhKumController extends Controller
{
    public function __construct(private readonly KumActionItemGenerator $kumActionItemGenerator) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canView($user), 403);

        $filters = validator($request->only(['search', 'year', 'period', 'status']), [
            'search' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period' => ['nullable', 'in:q1,q2,q3,q4,annual'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,completed,closed'],
        ])->validate();

        $kums = QmhKum::query()
            ->search($filters['search'] ?? null)
            ->when(isset($filters['year']), fn ($query) => $query->where('year', (int) $filters['year']))
            ->when(isset($filters['period']), fn ($query) => $query->where('period', $filters['period']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(! $this->canViewAll($user), fn ($query) => $query->where('created_by', $user->id))
            ->orderByDesc('year')
            ->orderBy('period')
            ->paginate(15)
            ->appends($request->query());

        return view('quality.kum.index', [
            'kums' => $kums,
            'canCreate' => $this->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        return view('quality.kum.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canCreate($user), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', 'in:q1,q2,q3,q4,annual'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'minutes_content' => ['nullable', 'string'],
            'participants_json' => ['nullable', 'array'],
            'participants_json.*' => ['string', 'max:255'],
            'participants_json_text' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,in_progress,completed,closed'],
        ])->validate();

        $participants = $validated['participants_json'] ?? null;
        if ($participants === null && isset($validated['participants_json_text'])) {
            $participants = collect(preg_split('/\r\n|\r|\n/', $validated['participants_json_text']))
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->values()
                ->all();
        }

        $kum = QmhKum::query()->create([
            'title' => $validated['title'],
            'year' => (int) $validated['year'],
            'period' => $validated['period'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'location' => $validated['location'] ?? null,
            'agenda' => $validated['agenda'] ?? null,
            'minutes_content' => $validated['minutes_content'] ?? null,
            'participants_json' => $participants ?? [],
            'status' => $validated['status'] ?? 'draft',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return redirect()->route('quality.kum.show', $kum)->with('success', 'KUM QMH berhasil dibuat.');
    }

    public function show(Request $request, QmhKum $kum): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canViewKum($user, $kum), 403);

        $kum->load(['creator', 'updater']);

        return view('quality.kum.show', [
            'kum' => $kum,
            'canManage' => $this->canEditKum($user, $kum),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }

    public function update(Request $request, QmhKum $kum): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditKum($user, $kum), 403);

        $validated = validator($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period' => ['required', 'in:q1,q2,q3,q4,annual'],
            'scheduled_at' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'agenda' => ['nullable', 'string'],
            'minutes_content' => ['nullable', 'string'],
            'participants_json' => ['nullable', 'array'],
            'participants_json.*' => ['string', 'max:255'],
            'participants_json_text' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,scheduled,in_progress,completed,closed'],
        ])->validate();

        $participants = $validated['participants_json'] ?? null;
        if ($participants === null && isset($validated['participants_json_text'])) {
            $participants = collect(preg_split('/\r\n|\r|\n/', $validated['participants_json_text']))
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->map(fn ($value) => trim((string) $value))
                ->values()
                ->all();
        }

        $kum->fill([
            'title' => $validated['title'],
            'year' => (int) $validated['year'],
            'period' => $validated['period'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'location' => $validated['location'] ?? null,
            'agenda' => $validated['agenda'] ?? null,
            'minutes_content' => $validated['minutes_content'] ?? null,
            'participants_json' => $participants ?? [],
            'status' => $validated['status'],
        ]);
        $kum->updated_by = $user->id;
        $kum->save();

        return redirect()->route('quality.kum.show', $kum)->with('success', 'KUM QMH berhasil diperbarui.');
    }

    public function destroy(Request $request, QmhKum $kum): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canDeleteKum($user, $kum), 403);

        $kum->delete();

        return redirect()->route('quality.kum.index')->with('success', 'KUM QMH berhasil dihapus.');
    }

    public function storeActionItems(Request $request, QmhKum $kum): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $this->canEditKum($user, $kum), 403);

        $validated = validator($request->all(), [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.item' => ['required', 'string', 'max:255'],
            'decisions.*.description' => ['nullable', 'string'],
            'decisions.*.assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'decisions.*.due_date' => ['required', 'date'],
        ])->validate();

        try {
            $created = $this->kumActionItemGenerator->generate($kum, $validated['decisions'], $user);
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('quality.kum.show', $kum)
                ->withErrors(['decisions' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()->route('quality.kum.show', $kum)
            ->with('success', sprintf('%d action item berhasil dibuat dari keputusan KUM.', count($created)));
    }

    private function canView(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.kum.view', 'qmh.kum.view.all', 'qmh.view']);
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.kum.view.all', 'qmh.view']);
    }

    private function canCreate(User $user): bool
    {
        return $user->hasAnyPermission(['qmh.kum.create', 'qmh.kum.create.all', 'qmh.create']);
    }

    private function canEditKum(User $user, QmhKum $kum): bool
    {
        if ($user->hasAnyPermission(['qmh.kum.edit', 'qmh.kum.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $kum->created_by === (int) $user->id;
    }

    private function canDeleteKum(User $user, QmhKum $kum): bool
    {
        if ($user->hasAnyPermission(['qmh.kum.delete', 'qmh.kum.create.all', 'qmh.create'])) {
            return true;
        }

        return (int) $kum->created_by === (int) $user->id;
    }

    private function canViewKum(User $user, QmhKum $kum): bool
    {
        if ($this->canViewAll($user)) {
            return true;
        }

        return (int) $kum->created_by === (int) $user->id;
    }
}
