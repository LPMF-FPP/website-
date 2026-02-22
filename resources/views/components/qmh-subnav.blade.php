@props([
    'active' => '', // overview|governance|documents|rapat|audit|kum|templates|reports
])

@php
    $activeKey = is_string($active) ? $active : '';
    $inGovernanceContext = in_array($activeKey, ['governance', 'rapat', 'audit', 'kum'], true);

    $items = [
        [
            'key' => 'overview',
            'label' => 'Ringkasan',
            'href' => route('quality.index'),
        ],
    ];

    if (auth()->user()?->hasAnyPermission(['qmh.view', 'qmh.rapat.view', 'qmh.audit.view', 'qmh.kum.view'])) {
        $items[] = [
            'key' => 'governance',
            'label' => 'Tata Kelola',
            'href' => route('quality.governance.index'),
        ];
    }

    $items[] = [
        'key' => 'documents',
        'label' => 'Dokumen',
        'href' => route('quality.documents.index'),
    ];

    if (auth()->user()?->hasPermission('qmh.template.manage')) {
        $items[] = [
            'key' => 'templates',
            'label' => 'Template',
            'href' => route('quality.templates.index'),
        ];
    }

    if (auth()->user()?->hasPermission('qmh.report')) {
        $items[] = [
            'key' => 'reports',
            'label' => 'Laporan',
            'href' => route('quality.reports.index'),
        ];
    }

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

<nav aria-label="Navigasi QMH" class="mt-3">
    <div class="qmh-subnav-primary inline-flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white/60 p-1">
        @foreach ($items as $item)
            @php
                $itemKey = $item['key'] ?? '';
                $isActive = ($itemKey === 'governance' && $inGovernanceContext) || $activeKey === $itemKey;
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
</nav>
