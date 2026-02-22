<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header title="Dashboard QMH">
                <x-slot name="actions">
                    <a href="{{ route('quality.documents.create') }}" class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        + Buat Dokumen
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="overview" />
        </div>
    </x-slot>

    @php
        $filters = $dashboard['filters'] ?? [];
        $alerts = $dashboard['alerts'] ?? [];
        $queue = $dashboard['queue'] ?? ['active_tab' => 'mine', 'tabs' => [], 'rows' => []];
        $governance = $dashboard['governance'] ?? [];
        $activities = $dashboard['activities'] ?? [];

        $activeQueueTab = $queue['active_tab'] ?? 'mine';
        $activeClause = $filters['clause'] ?? null;
        $activeDocType = $filters['doc_type'] ?? null;
        $activePeriod = (int) ($filters['period'] ?? 30);

        $periodOptions = [
            ['value' => 7, 'label' => '7 Hari'],
            ['value' => 14, 'label' => '14 Hari'],
            ['value' => 30, 'label' => '30 Hari'],
            ['value' => 90, 'label' => '90 Hari'],
        ];

        $iconPath = static fn (string $icon): string => match ($icon) {
            'shield' => 'M12 3 4 7v5c0 5 3.5 8.5 8 9 4.5-.5 8-4 8-9V7l-8-4Z',
            'calendar' => 'M8 3v3m8-3v3M4 10h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z',
            'filter' => 'M4 5h16M7 12h10m-7 7h4',
            'alert' => 'M12 9v4m0 4h.01M3.34 16h17.32c.86 0 1.4-.93.97-1.68L13.97 4.8a1.11 1.11 0 0 0-1.94 0l-7.66 9.52c-.43.75.11 1.68.97 1.68Z',
            'clock' => 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'audit' => 'M9 5h10M9 12h10M9 19h10M4 5h.01M4 12h.01M4 19h.01',
            'queue' => 'M4 6h16M4 12h16M4 18h16',
            'mine' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0ZM4 20a6 6 0 0 1 12 0',
            'overdue' => 'M12 7v5l3 3M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0Z',
            'done' => 'M5 13l4 4L19 7',
            'document' => 'M7 3h7l5 5v13H7V3Zm7 1.5V9h4.5',
            'hash' => 'M9 3 7 21M17 3l-2 18M4 9h16M3 15h16',
            'hourglass' => 'M7 3h10M7 21h10M8 3c0 4 3 4 4 6-1 2-4 2-4 6m8-12c0 4-3 4-4 6 1 2 4 2 4 6',
            'eye' => 'M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
            'assign' => 'M15 7h6m-3-3v6M9 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-6 7a6 6 0 0 1 12 0',
            'defer' => 'M12 7v5l3 2M3 12a9 9 0 1 0 18 0 9 9 0 0 0-18 0Z',
            'send' => 'M3 12 20 4l-7 16-2-6-8-2Z',
            'archive' => 'M4 7h16v11H4V7Zm3-3h10l1 3H6l1-3Z',
            'rapat' => 'M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z',
            'kum' => 'M6 4h9l5 5v11H6V4Zm8 1v4h4',
            'governance' => 'M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4Z',
            'activity' => 'M4 12h4l2-5 4 10 2-5h4',
            default => 'M12 4v16M4 12h16',
        };
    @endphp

    <div class="space-y-6" x-data="qmhQueueTable(@js($queue['rows'] ?? []))">
        <section class="rounded-2xl border border-cyan-100 bg-gradient-to-r from-white via-cyan-50/40 to-white p-4 shadow-sm lg:p-5">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-cyan-800">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('shield') }}" />
                        </svg>
                        Command Center
                    </div>
                    <h2 class="text-xl font-semibold text-slate-900">Dokumen QMH yang benar-benar bisa ditindaklanjuti</h2>
                    <p class="max-w-2xl text-sm text-slate-600">Fokus pada antrean kerja, risiko tertunda, dan snapshot tata kelola agar tim bisa mengeksekusi prioritas tanpa konteks yang berisik.</p>
                </div>

                <form method="GET" action="{{ route('quality.index') }}" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <input type="hidden" name="queue_tab" value="{{ $activeQueueTab }}">

                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="dashboard-period">Periode</label>
                        <select id="dashboard-period" name="period" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            @foreach($periodOptions as $option)
                                <option value="{{ $option['value'] }}" @selected((int) $activePeriod === (int) $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="dashboard-clause">Klausul</label>
                        <select id="dashboard-clause" name="clause" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Klausul</option>
                            @foreach([4, 5, 6, 7, 8] as $clause)
                                <option value="{{ $clause }}" @selected((int) $activeClause === (int) $clause)>Klausul {{ $clause }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-600" for="dashboard-doc-type">Jenis</label>
                        <select id="dashboard-doc-type" name="doc_type" class="w-full rounded-lg border-slate-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="">Semua Jenis</option>
                            <option value="sop" @selected($activeDocType === 'sop')>SOP</option>
                            <option value="ik" @selected($activeDocType === 'ik')>IK</option>
                            <option value="formulir" @selected($activeDocType === 'formulir')>Formulir</option>
                            <option value="pendukung" @selected($activeDocType === 'pendukung')>Pendukung</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="submit" class="inline-flex min-h-[44px] items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            Terapkan
                        </button>
                        <a href="{{ route('quality.index') }}" class="inline-flex min-h-[44px] items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <section class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach($alerts as $alert)
                        <a href="{{ $alert['href'] ?? '#' }}" class="inline-flex min-h-[44px] items-center gap-2 rounded-full border border-amber-200 bg-white px-3 py-2 text-xs font-semibold text-amber-900 transition hover:-translate-y-0.5 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            <svg class="h-3.5 w-3.5 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath($alert['icon'] ?? 'alert') }}" />
                            </svg>
                            <span>{{ $alert['label'] ?? '-' }}</span>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800">{{ $alert['count'] ?? 0 }}</span>
                        </a>
                    @endforeach
                </div>

                <a href="{{ route('quality.documents.index') }}" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold text-amber-900 transition hover:text-amber-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5" />
                    </svg>
                    Lihat semua masalah
                </a>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-12">
            <section class="xl:col-span-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('queue') }}" />
                            </svg>
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-800">Work Queue Saya</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ count($queue['rows'] ?? []) }} item ditampilkan</span>
                    </div>

                    <div class="mt-3 inline-flex flex-wrap gap-2" role="tablist" aria-label="Tab antrean kerja">
                        @foreach(($queue['tabs'] ?? []) as $tab)
                            @php
                                $isActiveTab = ($tab['key'] ?? '') === $activeQueueTab;
                                $tabIcon = match ($tab['key'] ?? '') {
                                    'mine' => 'mine',
                                    'overdue' => 'overdue',
                                    'done' => 'done',
                                    default => 'queue',
                                };
                            @endphp
                            <a
                                href="{{ $tab['href'] ?? '#' }}"
                                role="tab"
                                aria-selected="{{ $isActiveTab ? 'true' : 'false' }}"
                                class="inline-flex min-h-[44px] items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 {{ $isActiveTab ? 'border-primary-300 bg-primary-100 text-primary-900 shadow-sm' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath($tabIcon) }}" />
                                </svg>
                                <span>{{ $tab['label'] ?? '-' }}</span>
                                <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px]">{{ $tab['count'] ?? 0 }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div x-cloak x-show="selectedCount > 0" class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-800">
                            Aksi massal
                            <span class="text-slate-500" x-text="`(${selectedCount} dipilih)`"></span>
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="setHelperMessage('Distribusi massal tersedia di modul dokumen. Buka daftar dokumen untuk proses lanjutan.')" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('send') }}" />
                                </svg>
                                Distribusi
                            </button>
                            <button type="button" @click="setHelperMessage('Arsipkan massal akan aktif setelah workflow arsip dokumen diaktifkan.')" class="inline-flex min-h-[44px] items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('archive') }}" />
                                </svg>
                                Arsipkan
                            </button>
                        </div>
                    </div>
                    <p x-cloak x-show="helperMessage" class="mt-2 text-xs text-slate-500" x-text="helperMessage" aria-live="polite"></p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50">
                        <tr>
                            <th class="w-10 px-4 py-3 text-left">
                                <label class="sr-only" for="qmh-dashboard-select-all">Pilih semua antrean</label>
                                <input id="qmh-dashboard-select-all" type="checkbox" :checked="allSelectedOnPage" @change="toggleSelectAll($event.target.checked)" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600">
                            </th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Dokumen</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Klausul</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Umur</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Aksi</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse(($queue['rows'] ?? []) as $row)
                            <tr x-bind:class="isSelected({{ $row['id'] }}) ? 'bg-primary-50/60' : 'bg-white'" class="transition hover:bg-slate-50/80">
                                <td class="px-4 py-3 align-top">
                                    <label class="sr-only" for="queue-row-{{ $row['id'] }}">Pilih dokumen {{ $row['doc_code'] }}</label>
                                    <input id="queue-row-{{ $row['id'] }}" type="checkbox" :checked="isSelected({{ $row['id'] }})" @change="toggleSelection({{ $row['id'] }}, $event.target.checked)" class="mt-1 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-600">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-semibold text-slate-900">{{ $row['doc_code'] }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $row['title'] }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">Klausul {{ $row['clause'] }}</td>
                                <td class="px-4 py-3">
                                    <x-status-badge :label="$row['status_label']" :variant="$row['status_variant']" subtle="true" />
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['age_label'] }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ $row['show_url'] }}" class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('eye') }}" />
                                            </svg>
                                            Buka
                                        </a>

                                        @if($row['can_manage'] && is_string($row['assign_url']))
                                            <a href="{{ $row['assign_url'] }}" class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('assign') }}" />
                                                </svg>
                                                Assign
                                            </a>
                                            <a href="{{ $row['defer_url'] }}" class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('defer') }}" />
                                                </svg>
                                                Tunda
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                                    <div class="mx-auto max-w-sm space-y-1">
                                        <p class="font-medium text-slate-700">Tidak ada antrean pada tab ini.</p>
                                        <p class="text-xs">Ubah tab atau filter agar pekerjaan yang relevan langsung terlihat.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <aside class="xl:col-span-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                <div class="mb-4 flex items-center gap-2">
                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('governance') }}" />
                    </svg>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-800">Governance Snapshot</h3>
                </div>

                <div class="space-y-3">
                    @foreach($governance as $card)
                        <a href="{{ $card['href'] ?? '#' }}" class="group flex min-h-[44px] items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-3 transition hover:-translate-y-0.5 hover:border-primary-200 hover:bg-primary-50/60 hover:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                            <div class="min-w-0 pr-2">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-slate-500 group-hover:text-primary-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath($card['icon'] ?? 'governance') }}" />
                                    </svg>
                                    <p class="truncate text-sm font-medium text-slate-800">{{ $card['label'] ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-semibold text-slate-900">{{ $card['count'] ?? 0 }}</p>
                                <p class="text-[11px] uppercase tracking-wide text-slate-500">item</p>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-4 border-t border-slate-100 pt-4">
                    <a href="{{ route('quality.governance.index') }}" class="inline-flex min-h-[44px] items-center gap-2 text-sm font-semibold text-primary-700 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1">
                        Buka Tata Kelola
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-5-5 5 5-5 5" />
                        </svg>
                    </a>
                </div>
            </aside>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath('activity') }}" />
                    </svg>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-800">Activity Feed</h3>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($activities as $activity)
                    <a href="{{ $activity['href'] ?? '#' }}" class="flex min-h-[44px] items-start justify-between gap-3 px-4 py-3 transition hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-inset">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900">{{ $activity['title'] ?? '-' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $activity['meta'] ?? '-' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-600">{{ $activity['time_label'] ?? '-' }}</span>
                    </a>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">
                        Aktivitas terbaru belum tersedia.
                    </div>
                @endforelse
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('qmhQueueTable', (rows) => ({
                rows,
                selectedIds: [],
                helperMessage: '',

                get rowIds() {
                    return (this.rows || []).map((row) => String(row.id));
                },

                get selectedCount() {
                    return this.selectedIds.length;
                },

                get allSelectedOnPage() {
                    return this.rowIds.length > 0 && this.selectedIds.length === this.rowIds.length;
                },

                toggleSelectAll(checked) {
                    this.selectedIds = checked ? [...this.rowIds] : [];
                    this.helperMessage = '';
                },

                toggleSelection(id, checked) {
                    const key = String(id);

                    if (checked && !this.selectedIds.includes(key)) {
                        this.selectedIds.push(key);
                    }

                    if (!checked) {
                        this.selectedIds = this.selectedIds.filter((selectedId) => selectedId !== key);
                    }

                    this.helperMessage = '';
                },

                isSelected(id) {
                    return this.selectedIds.includes(String(id));
                },

                setHelperMessage(message) {
                    this.helperMessage = message;
                },
            }));
        });
    </script>
    @endpush
</x-app-layout>
