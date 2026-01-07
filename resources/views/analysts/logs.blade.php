@php
    use Illuminate\Support\Str;

    $formatValue = function ($value) {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    };

    $buildChanges = function ($before, $after) use ($formatValue) {
        $before = is_array($before) ? $before : [];
        $after = is_array($after) ? $after : [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];

        foreach ($keys as $key) {
            $beforeValue = $before[$key] ?? null;
            $afterValue = $after[$key] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = $key.': '.$formatValue($beforeValue).' -> '.$formatValue($afterValue);
        }

        return $changes;
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Log Aktivitas"
            :breadcrumbs="[[ 'label' => 'Pengguna', 'href' => route('analysts.index') ], [ 'label' => $analyst->name ], [ 'label' => 'Log' ]]"
        >
            <x-slot name="actions">
                <a href="{{ route('analysts.show', $analyst) }}"
                    class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Kembali</a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-page-section title="Filter Log">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Aksi</label>
                    <select name="action" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua aksi</option>
                        @foreach($actions as $actionOption)
                            <option value="{{ $actionOption }}" @selected(($filters['action'] ?? '') === $actionOption)>
                                {{ Str::of($actionOption)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jenis Objek</label>
                    <select name="subject_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Semua objek</option>
                        @foreach($subjectTypes as $typeOption)
                            <option value="{{ $typeOption }}" @selected(($filters['subject_type'] ?? '') === $typeOption)>
                                {{ class_basename($typeOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Mulai</label>
                    <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sampai</label>
                    <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div class="flex items-end gap-3">
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700">Terapkan</button>
                    <a href="{{ route('analysts.logs', $analyst) }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-inset ring-gray-200 transition hover:text-primary-600">Reset</a>
                </div>
            </form>
        </x-page-section>

        <x-page-section title="Riwayat Aktivitas">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Waktu</th>
                            <th class="px-4 py-3 text-left">Aksi</th>
                            <th class="px-4 py-3 text-left">Objek</th>
                            <th class="px-4 py-3 text-left">Ringkas Perubahan</th>
                            <th class="px-4 py-3 text-left">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm text-gray-700">
                        @forelse($logs as $log)
                            @php
                                $changes = $buildChanges($log->before, $log->after);
                                $subjectLabel = $log->subject_type ? class_basename($log->subject_type) : '-';
                                $subjectId = $log->subject_id ? '#'.$log->subject_id : '';
                                $metaSummary = [];
                                if (is_array($log->meta ?? null)) {
                                    foreach ($log->meta as $metaKey => $metaValue) {
                                        if (is_array($metaValue)) {
                                            continue;
                                        }
                                        $metaSummary[] = $metaKey.': '.$formatValue($metaValue);
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $log->created_at->format('d M Y H:i') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-gray-900">{{ Str::of($log->action)->replace('_', ' ')->title() }}</div>
                                    <div class="text-xs text-gray-500">
                                        @if($log->actor)
                                            Aktor: {{ $log->actor->name }}
                                        @endif
                                        @if($log->target)
                                            | Target: {{ $log->target->name }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{ $subjectLabel }} {{ $subjectId }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($changes)
                                        <div class="text-xs text-gray-600">
                                            {{ implode(', ', array_slice($changes, 0, 3)) }}
                                        </div>
                                    @elseif($metaSummary)
                                        <div class="text-xs text-gray-600">
                                            {{ implode(', ', array_slice($metaSummary, 0, 3)) }}
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    {{ $log->ip ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada log aktivitas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        </x-page-section>
    </div>
</x-app-layout>
