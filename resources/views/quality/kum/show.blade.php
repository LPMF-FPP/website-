<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                :title="$kum->title"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'KUM', 'route' => 'quality.kum.index'],
                    ['label' => 'Detail KUM'],
                ]"
            />

            <x-qmh-subnav active="kum" />
        </div>
    </x-slot>

    <div class="space-y-6" x-data="qmhKumPage()">
        <div class="grid gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Periode</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">
                    <span x-text="periodLabel('{{ $kum->period }}')"></span> {{ $kum->year }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jadwal</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $kum->scheduled_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Lokasi</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $kum->location ?: '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-1">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass('{{ $kum->status }}')">
                        {{ strtoupper($kum->status) }}
                    </span>
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Agenda</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $kum->agenda ?: 'Belum ada agenda.' }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Peserta</h3>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                @forelse(($kum->participants_json ?? []) as $participant)
                    <li>{{ $participant }}</li>
                @empty
                    <li class="list-none text-gray-500">Belum ada daftar peserta.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Notulensi</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $kum->minutes_content ?: 'Belum ada notulensi.' }}</p>
        </div>

        @if($canManage)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Generate Action Item dari Keputusan</h3>
                <form method="POST" action="{{ route('quality.kum.action-items.store', $kum) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid gap-3 md:grid-cols-2">
                        <input type="text" name="decisions[0][item]" value="{{ old('decisions.0.item') }}" placeholder="Keputusan / action" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                        <input type="date" name="decisions[0][due_date]" value="{{ old('decisions.0.due_date') }}" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                    </div>
                    <div>
                        <label for="decision-assignee" class="mb-1 block text-xs font-medium text-gray-600">Assignee (opsional, default pembuat KUM)</label>
                        <select id="decision-assignee" name="decisions[0][assignee_id]" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Auto-assign ke pembuat</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected((int) old('decisions.0.assignee_id') === (int) $user->id)>
                                    {{ $user->name }} ({{ $user->role }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <textarea name="decisions[0][description]" rows="2" placeholder="Deskripsi tambahan" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">{{ old('decisions.0.description') }}</textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Generate Action Item</button>
                    </div>
                </form>
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('quality.kum.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Kembali ke Daftar KUM</a>
                <a href="{{ route('quality.governance.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Buka Ruang Kerja Tata Kelola</a>
            </div>
        </div>
    </div>
</x-app-layout>
