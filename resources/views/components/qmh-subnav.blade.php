@props([
    'active' => '', // overview|documents|create|pendukung|templates|reports
])

@php
    $items = [
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
        [
            'key' => 'create',
            'label' => 'Buat Dokumen',
            'href' => route('quality.documents.create'),
        ],
        [
            'key' => 'pendukung',
            'label' => 'Dokumen Pendukung',
            'href' => route('quality.pendukung.index'),
        ],
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
                class="rounded-md px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-primary-100 text-primary-800' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                @if($isActive) aria-current="page" @endif
            >
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
