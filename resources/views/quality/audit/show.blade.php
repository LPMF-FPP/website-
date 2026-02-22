<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                :title="$audit->title"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Audit', 'route' => 'quality.audit.index'],
                    ['label' => 'Detail Audit'],
                ]"
            />

            <x-qmh-subnav active="audit" />
        </div>
    </x-slot>

    <div class="space-y-6" x-data="qmhAuditPage()">
        <div class="grid gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Tipe</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ ucfirst($audit->audit_type) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Jadwal</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $audit->scheduled_at?->format('d M Y H:i') ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                <p class="mt-1">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="badgeClass('{{ $audit->status }}')">
                        {{ strtoupper($audit->status) }}
                    </span>
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Lokasi</p>
                <p class="mt-1 text-sm font-semibold text-gray-900">{{ $audit->location ?: '-' }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Ruang Lingkup</h3>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $audit->scope ?: 'Belum ada ruang lingkup.' }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Auditor</h3>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse($audit->auditors as $auditor)
                    <span class="inline-flex items-center rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700">{{ $auditor->name }}</span>
                @empty
                    <p class="text-sm text-gray-500">Belum ada auditor ditetapkan.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Temuan Audit</h3>

            <div class="mt-3 space-y-3">
                @forelse($audit->temuans as $temuan)
                    <div class="rounded-md border border-gray-200 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium text-gray-900">{{ $temuan->title }}</p>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="severityClass('{{ $temuan->severity }}')">
                                {{ strtoupper($temuan->severity) }}
                            </span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="badgeClass('{{ $temuan->status }}')">
                                {{ strtoupper($temuan->status) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $temuan->description }}</p>
                        <p class="mt-1 text-xs text-gray-500">Due: {{ optional($temuan->due_date)->format('d M Y') ?? '-' }}</p>

                        @if($canManage)
                            <form method="POST" action="{{ route('quality.audit.temuan.update', [$audit, $temuan]) }}" class="mt-3 grid gap-2 md:grid-cols-3">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="corrective_action" value="{{ $temuan->corrective_action }}" placeholder="Corrective action" class="rounded-md border-gray-300 text-xs focus:border-primary-600 focus:ring-primary-600 md:col-span-2">
                                <div class="flex gap-2">
                                    <select name="status" class="rounded-md border-gray-300 text-xs focus:border-primary-600 focus:ring-primary-600">
                                        @foreach(['open', 'in_progress', 'resolved', 'closed'] as $status)
                                            <option value="{{ $status }}" @selected($temuan->status === $status)>{{ strtoupper($status) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-md border border-gray-300 px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">Update</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Belum ada temuan audit.</p>
                @endforelse
            </div>

            @if($canManage)
                <form method="POST" action="{{ route('quality.audit.temuan.store', $audit) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4">
                    @csrf
                    <input type="text" name="title" placeholder="Judul temuan" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required>
                    <textarea name="description" rows="3" placeholder="Deskripsi temuan" class="w-full rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600" required></textarea>
                    <div class="grid gap-2 md:grid-cols-3">
                        <select name="severity" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                            <option value="minor">MINOR</option>
                            <option value="major">MAJOR</option>
                            <option value="kritis">KRITIS</option>
                        </select>
                        <input type="date" name="due_date" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                        <input type="text" name="corrective_action" placeholder="Rencana tindakan" class="rounded-md border-gray-300 text-sm focus:border-primary-600 focus:ring-primary-600">
                    </div>
                    <button type="submit" class="rounded-md bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-700">Tambah Temuan</button>
                </form>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a href="{{ route('quality.audit.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Kembali ke Daftar Audit</a>
                <a href="{{ route('quality.governance.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50">Buka Ruang Kerja Tata Kelola</a>
            </div>
        </div>
    </div>
</x-app-layout>
