<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnalystController extends Controller
{
    protected array $analystRoles = ['analyst', 'lab_analyst', 'petugas_lab'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['admin', 'supervisor'])) {
                abort(403, 'Unauthorized');
            }
            return $next($request);
        });
    }

    public function index(): View
    {
        $analysts = User::query()
            ->whereIn('role', $this->analystRoles)
            ->orderBy('name')
            ->paginate(20);

        return view('analysts.index', [
            'analysts' => $analysts,
            'roles' => $this->analystRoles,
        ]);
    }

    public function create(): View
    {
        return view('analysts.create', [
            'analyst' => new User(),
            'roles' => $this->analystRoles,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        User::create($data);

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data analis berhasil ditambahkan.');
    }

    public function edit(User $analyst): View
    {
        if (!in_array($analyst->role, $this->analystRoles)) {
            abort(404);
        }

        return view('analysts.edit', [
            'analyst' => $analyst,
            'roles' => $this->analystRoles,
        ]);
    }

    public function update(Request $request, User $analyst): RedirectResponse
    {
        if (!in_array($analyst->role, $this->analystRoles)) {
            abort(404);
        }

        $data = $this->validatedData($request, $analyst->id);

        $analyst->update($data);

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data analis berhasil diperbarui.');
    }

    public function destroy(User $analyst): RedirectResponse
    {
        if (!in_array($analyst->role, $this->analystRoles)) {
            abort(404);
        }

        $analyst->delete();

        return redirect()
            ->route('analysts.index')
            ->with('success', 'Data analis berhasil dihapus.');
    }

    protected function validatedData(Request $request, ?int $analystId = null): array
    {
        $passwordRule = ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'];
        if (!$analystId) {
            $passwordRule = ['required', 'string', 'min:8', 'confirmed'];
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($analystId)],
            'role' => ['required', Rule::in($this->analystRoles)],
            'title_prefix' => ['nullable', 'string', 'max:50'],
            'title_suffix' => ['nullable', 'string', 'max:50'],
            'rank' => ['nullable', 'string', 'max:100'],
            'nrp' => ['nullable', 'string', 'max:50'],
            'nip' => ['nullable', 'string', 'max:50'],
            'password' => $passwordRule,
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        return $validated;
    }
}
