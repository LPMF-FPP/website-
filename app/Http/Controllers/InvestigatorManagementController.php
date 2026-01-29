<?php

namespace App\Http\Controllers;

use App\Models\Investigator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvestigatorManagementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('investigators.view');

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

        return view('investigators.index', [
            'investigators' => $investigators,
            'jurisdictions' => $jurisdictions,
            'filters' => [
                'type' => $type,
                'jurisdiction' => $jurisdiction,
                'q' => $keyword,
            ],
        ]);
    }

    public function show(Investigator $investigator): View
    {
        Gate::authorize('investigators.view');

        $requests = $investigator->testRequests()
            ->select('id', 'request_number', 'case_number', 'status', 'submitted_at', 'created_at')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('investigators.show', [
            'investigator' => $investigator,
            'requests' => $requests,
        ]);
    }

    public function edit(Investigator $investigator): View
    {
        Gate::authorize('investigators.edit');

        return view('investigators.edit', [
            'investigator' => $investigator,
        ]);
    }

    public function update(Request $request, Investigator $investigator): RedirectResponse
    {
        Gate::authorize('investigators.edit');

        $validated = $request->validate([
            'rank' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('investigators', 'email')->ignore($investigator->id)],
            'jurisdiction' => ['required', 'string', 'max:255'],
        ]);

        $investigator->update($validated);

        return redirect()
            ->route('investigators.show', $investigator)
            ->with('success', 'Biodata penyidik berhasil diperbarui.');
    }

    public function destroy(Investigator $investigator): RedirectResponse
    {
        Gate::authorize('investigators.delete');

        if ($investigator->testRequests()->exists()) {
            return back()->with('error', 'Penyidik tidak bisa dihapus karena masih memiliki permintaan.');
        }

        try {
            $investigator->delete();

            return redirect()
                ->route('investigators.index')
                ->with('success', 'Penyidik berhasil dihapus.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal menghapus penyidik.');
        }
    }

    private function likeOperator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}
