@props([
    'active' => '', // overview|governance|documents|rapat|audit|kum|templates|reports
])

@php
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
            'label' => 'Governance',
            'href' => route('quality.governance.index'),
        ];
    }

    $items[] = [
        'key' => 'documents',
        'label' => 'Dokumen',
        'href' => route('quality.documents.index'),
    ];

    if (auth()->user()?->hasAnyPermission(['qmh.rapat.view', 'qmh.rapat.view.all', 'qmh.view'])) {
        $items[] = [
            'key' => 'rapat',
            'label' => 'Rapat',
            'href' => route('quality.rapat.index'),
        ];
    }

    if (auth()->user()?->hasAnyPermission(['qmh.audit.view', 'qmh.audit.view.all', 'qmh.view'])) {
        $items[] = [
            'key' => 'audit',
            'label' => 'Audit',
            'href' => route('quality.audit.index'),
        ];
    }

    if (auth()->user()?->hasAnyPermission(['qmh.kum.view', 'qmh.kum.view.all', 'qmh.view'])) {
        $items[] = [
            'key' => 'kum',
            'label' => 'KUM',
            'href' => route('quality.kum.index'),
        ];
    }

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

    $activeKey = is_string($active) ? $active : '';
@endphp

<nav aria-label="Navigasi QMH" class="mt-3">
    <div class="inline-flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-white/60 p-1">
        @foreach ($items as $item)
            @php
                $isActive = $activeKey === ($item['key'] ?? '');
            @endphp
            <a
                href="{{ $item['href'] }}"
                class="rounded-md px-3 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600 focus-visible:ring-offset-1 {{ $isActive ? 'bg-primary-100 text-primary-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                @if($isActive) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
