@props([
    'active' => '', // overview|governance|documents|rapat|audit|kum|templates|reports
])

@php
    $primaryItems = [
        [
            'key' => 'overview',
            'label' => 'Ringkasan',
            'href' => route('quality.index'),
        ],
        [
            'key' => 'documents',
            'label' => 'Dokumen',
            'href' => route('quality.documents.index'),
        ],
    ];

    $governanceItems = [];

    if (auth()->user()?->hasAnyPermission(['qmh.view', 'qmh.rapat.view', 'qmh.audit.view', 'qmh.kum.view'])) {
        $governanceItems[] = [
            'key' => 'governance',
            'label' => 'Governance',
            'href' => route('quality.governance.index'),
        ];
    }

    if (auth()->user()?->hasAnyPermission(['qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.view'])) {
        $governanceItems[] = [
            'key' => 'rapat',
            'label' => 'Rapat',
            'href' => route('quality.rapat.index'),
        ];
    }

    if (auth()->user()?->hasAnyPermission(['qmh.audit.view', 'qmh.audit.view.all', 'qmh.view'])) {
        $governanceItems[] = [
            'key' => 'audit',
            'label' => 'Audit',
            'href' => route('quality.audit.index'),
        ];
    }

    if (auth()->user()?->hasAnyPermission(['qmh.kum.view', 'qmh.kum.view.all', 'qmh.view'])) {
        $governanceItems[] = [
            'key' => 'kum',
            'label' => 'KUM',
            'href' => route('quality.kum.index'),
        ];
    }

    if (auth()->user()?->hasPermission('qmh.template.manage')) {
        $primaryItems[] = [
            'key' => 'templates',
            'label' => 'Template',
            'href' => route('quality.templates.index'),
        ];
    }

    if (auth()->user()?->hasPermission('qmh.report')) {
        $primaryItems[] = [
            'key' => 'reports',
            'label' => 'Laporan',
            'href' => route('quality.reports.index'),
        ];
    }

    $activeKey = is_string($active) ? $active : '';
    $governanceActive = in_array($activeKey, ['governance', 'rapat', 'audit', 'kum'], true);

    $iconPath = static fn (string $key): string => match ($key) {
        'overview' => 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z',
        'documents' => 'M7 3h7l5 5v13H7V3Zm7 1.5V9h4.5',
        'templates' => 'M4 6h16M4 12h10M4 18h16M17 10l3 3-3 3',
        'reports' => 'M4 19h16M7 16V8M12 16V5M17 16v-4',
        'governance' => 'M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4Z',
        'rapat' => 'M8 7V3m8 4V3M4 11h16M6 5h12a2 2 0 0 1 2 2v12H4V7a2 2 0 0 1 2-2Z',
        'audit' => 'M11 5h8M11 12h8M11 19h8M3 5h.01M3 12h.01M3 19h.01',
        'kum' => 'M6 4h9l5 5v11H6V4Zm8 1v4h4',
        default => 'M12 4v16M4 12h16',
    };
@endphp

<nav aria-label="Navigasi QMH" class="mt-3 space-y-2">
    <div class="qmh-subnav-primary inline-flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white/60 p-1">
        @foreach ($primaryItems as $item)
            @php
                $isActive = $activeKey === ($item['key'] ?? '');
            @endphp
            <a
                href="{{ $item['href'] }}"
                class="qmh-subnav-item qmh-subnav-anim group inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 {{ $isActive ? 'bg-primary-100 text-primary-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                @if($isActive) aria-current="page" @endif
            >
                <svg class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $isActive ? 'scale-105' : 'group-hover:translate-x-0.5 group-hover:scale-105' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath($item['key'] ?? '') }}" />
                </svg>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    @if (!empty($governanceItems))
        <div class="rounded-xl border p-1.5 transition-all duration-200 {{ $governanceActive ? 'border-primary-300 bg-gradient-to-r from-primary-50 to-white shadow-sm' : 'border-primary-100 bg-white/80 hover:border-primary-200 hover:shadow-sm' }}">
            <div class="qmh-subnav-governance flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-xs font-semibold uppercase tracking-wide text-primary-700 shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500 animate-pulse" aria-hidden="true"></span>
                    Governance Suite
                </span>

                @foreach ($governanceItems as $item)
                    @php
                        $isActive = $activeKey === ($item['key'] ?? '');
                    @endphp
                    <a
                        href="{{ $item['href'] }}"
                        class="qmh-subnav-item qmh-subnav-anim group inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition duration-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 {{ $isActive ? 'bg-primary-600 text-white shadow-sm' : 'text-primary-700 hover:bg-white hover:text-primary-900' }}"
                        @if($isActive) aria-current="page" @endif
                    >
                        <svg class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $isActive ? 'scale-105' : 'group-hover:translate-x-0.5 group-hover:scale-105' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath($item['key'] ?? '') }}" />
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</nav>
