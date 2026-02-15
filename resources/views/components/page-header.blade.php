@props([
    'title' => '',
    'breadcrumbs' => [], // e.g., [['label' => 'Permintaan', 'href' => route('requests.index')], ['label' => 'Detail']]
    'class' => ''
])

<div class="{{ $class }}">
    @if (is_array($breadcrumbs) && count($breadcrumbs) > 0)
        <nav class="mb-2" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-600">
                @foreach ($breadcrumbs as $i => $crumb)
                    @php
                        $label = is_array($crumb) ? ($crumb['label'] ?? '') : '';
                        $href = null;
                        if (is_array($crumb) && isset($crumb['href']) && is_string($crumb['href']) && $crumb['href'] !== '') {
                            $href = $crumb['href'];
                        }
                        if ($href === null && is_array($crumb) && isset($crumb['route']) && is_string($crumb['route']) && $crumb['route'] !== '') {
                            $params = is_array($crumb['params'] ?? null) ? ($crumb['params'] ?? []) : [];
                            try {
                                $href = route($crumb['route'], $params);
                            } catch (\Throwable $e) {
                                report($e);
                                $href = null;
                            }
                        }
                        $isLast = $i === count($breadcrumbs) - 1;
                    @endphp

                    <li class="flex items-center gap-x-2">
                        @if (! $isLast)
                            @if (is_string($href) && $href !== '')
                                <a href="{{ $href }}" class="hover:text-primary-700 hover:underline">
                                    {{ $label }}
                                </a>
                            @else
                                <span>{{ $label }}</span>
                            @endif
                            <span class="text-gray-400" aria-hidden="true">/</span>
                        @else
                            <span class="font-medium text-gray-800" aria-current="page">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
    @endif

    <div class="flex items-start justify-between gap-4">
        <h2 class="font-semibold text-xl text-primary-900 leading-tight">{{ $title }}</h2>
        @if (isset($actions))
            <div class="shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
