<?php

namespace App\Services;

use App\Models\TestRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class DisposisiTableService
{
    public function getTableData(array $filters = []): Collection
    {
        return $this->buildQuery($filters)->get()->map(fn ($r) => $this->formatRow($r));
    }

    public function getPaginatedTableData(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $paginator = $this->buildQuery($filters)->paginate($perPage);
        $paginator->getCollection()->transform(fn ($r) => $this->formatRow($r));
        $paginator->fragment('disposisi');

        return $paginator;
    }

    protected function buildQuery(array $filters = [])
    {
        $query = TestRequest::query()
            ->with(['suspects', 'samples', 'delivery'])
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('suspect_name', 'ilike', "%{$search}%")
                    ->orWhereHas('suspects', fn ($s) => $s->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('samples', fn ($s) => $s->where('sample_code', 'ilike', "%{$search}%"));
            });
        }

        return $query;
    }

    protected function formatRow(TestRequest $request): array
    {
        $namaTsk = $request->suspects->isNotEmpty()
            ? $request->suspects->pluck('name')->join(' + ')
            : ($request->suspect_name ?? '-');

        $samples = $request->samples->pluck('sample_code')->sort()->values();
        $noSampel = $this->formatSampleRange($samples);

        $spDate = $request->delivery?->surat_pengantar_date;
        $status = $this->detectStatus($request);

        // Fallback untuk completed date (handle legacy data)
        // HANYA jika status completed
        $completedDate = null;
        if ($request->status === 'completed') {
            $completedDate = $request->completed_at ?? $request->updated_at;
        }

        // Hasil: Bisa dari completed_at atau updated_at saat ready_for_delivery
        // Jika ready_for_delivery, tampilkan updated_at sebagai tanggal hasil keluar
        $hasilDate = $request->completed_at ??
            (($request->status === 'completed' || $request->status === 'ready_for_delivery') ? $request->updated_at : null);

        return [
            'id' => $request->id,
            'nama_tsk' => strtoupper($namaTsk),
            'no_sampel' => $noSampel,
            'masuk' => $request->submitted_at ?? $request->created_at,
            'urmin' => $request->verified_at,
            'hasil' => $hasilDate,
            'sp' => $spDate,
            'ambil' => $completedDate,
            'status' => $status,
            'request_number' => $request->request_number,
            'has_delivery' => $request->delivery !== null,
        ];
    }

    protected function formatSampleRange(Collection $samples): string
    {
        if ($samples->isEmpty()) {
            return '-';
        }
        if ($samples->count() === 1) {
            return $samples->first();
        }

        $first = $samples->first();
        $last = $samples->last();

        preg_match('/^([A-Z]+)\s*(\d+)/', $first, $firstMatch);
        preg_match('/^([A-Z]+)\s*(\d+)/', $last, $lastMatch);

        if ($firstMatch && $lastMatch && $firstMatch[1] === $lastMatch[1]) {
            $prefix = $firstMatch[1];
            $start = ltrim($firstMatch[2], '0') ?: '0';
            $end = ltrim($lastMatch[2], '0') ?: '0';
            preg_match('/\|\s*(\d{4})/', $first, $yearMatch);
            $year = $yearMatch[1] ?? date('Y');
            $shortYear = substr($year, -2);

            return "{$prefix} {$start}-{$end}|{$shortYear}";
        }

        return $first.' - '.$last;
    }

    protected function detectStatus(TestRequest $request): string
    {
        $masuk = $request->submitted_at ?? $request->created_at;

        if ($request->delivery?->collected_at) {
            return 'completed';
        }

        if (! $request->verified_at && $masuk && $masuk->diffInDays(now()) > 14) {
            return 'stuck_urmin';
        }

        if ($request->verified_at && ! $request->completed_at && $request->verified_at->diffInDays(now()) > 7) {
            return 'stuck_hasil';
        }

        return 'in_progress';
    }
}
