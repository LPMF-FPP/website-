<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Governance Workspace"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Governance Workspace'],
                ]"
            />

            <x-qmh-subnav active="governance" />
        </div>
    </x-slot>

    <div x-data="qmhGovernancePage()" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Rapat</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="summary.rapat_count">{{ $summary['rapat_count'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">Audit</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="summary.audit_count">{{ $summary['audit_count'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-gray-500">KUM</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900" x-text="summary.kum_count">{{ $summary['kum_count'] }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-amber-700">Due Soon (7 Hari)</p>
                <p class="mt-1 text-2xl font-semibold text-amber-900" x-text="summary.due_soon_count">{{ $summary['due_soon_count'] }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-rose-700">Overdue</p>
                <p class="mt-1 text-2xl font-semibold text-rose-900" x-text="summary.overdue_count">{{ $summary['overdue_count'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Action Items Due Soon</h3>
                    <button type="button" @click="refreshSummary" class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Refresh</button>
                </div>

                <div class="mt-4 space-y-3">
                    @forelse($dueSoonItems as $item)
                        <div class="rounded-md border border-gray-200 px-3 py-2">
                            <p class="text-sm font-medium text-gray-900">{{ $item->title }}</p>
                            <p class="mt-1 text-xs text-gray-600">Rapat: {{ $item->rapat?->title ?? '-' }}</p>
                            <div class="mt-1 flex items-center gap-2 text-xs">
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-700">{{ strtoupper($item->status) }}</span>
                                <span class="text-gray-600">Due: {{ optional($item->due_date)->format('d M Y') ?? '-' }}</span>
                                <span class="text-gray-600">PIC: {{ $item->assignee?->name ?? '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Belum ada action item due soon.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-gray-900">Quick Actions</h3>
                <div class="mt-4 space-y-2">
                    <a href="{{ route('quality.rapat.index') }}" class="block rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Kelola Rapat</a>
                    <a href="{{ route('quality.audit.index') }}" class="block rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Kelola Audit</a>
                    <a href="{{ route('quality.kum.index') }}" class="block rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Kelola KUM</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
