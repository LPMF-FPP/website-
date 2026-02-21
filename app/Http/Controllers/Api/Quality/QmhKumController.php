<?php

namespace App\Http\Controllers\Api\Quality;

use App\Http\Controllers\Controller;
use App\Models\QmhKum;
use App\Models\User;
use App\Services\Quality\KumActionItemGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class QmhKumController extends Controller
{
    public function __construct(private readonly KumActionItemGenerator $kumActionItemGenerator) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyPermission(['qmh.kum.view', 'qmh.kum.view.all', 'qmh.view']), 403);

        $kums = QmhKum::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('year'), fn ($query) => $query->where('year', (int) $request->input('year')))
            ->when($request->filled('period'), fn ($query) => $query->where('period', $request->string('period')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when(! $user->hasAnyPermission(['qmh.kum.view.all', 'qmh.view']), fn ($query) => $query->where('created_by', $user->id))
            ->orderByDesc('year')
            ->orderBy('period')
            ->paginate((int) $request->input('per_page', 15))
            ->appends($request->query());

        return response()->json($kums);
    }

    public function storeActionItems(Request $request, QmhKum $kum): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $user->hasAnyPermission(['qmh.kum.edit', 'qmh.kum.create.all', 'qmh.create']) && (int) $kum->created_by !== (int) $user->id) {
            abort(403);
        }

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
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => sprintf('%d action item berhasil dibuat dari keputusan KUM.', count($created)),
            'count' => count($created),
        ], 201);
    }
}
