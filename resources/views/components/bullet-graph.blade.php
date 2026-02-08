@props([
    'label' => null,
    'actual' => 0,
    'min' => 0,
    'uom' => '',
    'max' => null,
])

@php
    $actualVal = (float) $actual;
    $minVal = (float) $min;

    $maxVal = $max !== null ? (float) $max : null;
    if ($maxVal === null) {
        // Simple heuristic so the bar has a stable scale even without explicit max.
        $maxVal = max($minVal * 2, $actualVal, 1);
    }

    $percent = $maxVal > 0 ? min(100, max(0, ($actualVal / $maxVal) * 100)) : 0;
    $minPercent = $maxVal > 0 ? min(100, max(0, ($minVal / $maxVal) * 100)) : 0;

    $tone = 'bg-emerald-500';
    if ($minVal > 0 && $actualVal < $minVal) {
        $tone = 'bg-red-500';
    } elseif ($minVal > 0 && $actualVal < ($minVal * 1.2)) {
        $tone = 'bg-amber-500';
    }
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }} data-bullet-graph>
    @if($label)
        <div class="flex items-baseline justify-between gap-3">
            <div class="text-sm font-semibold text-gray-900">{{ $label }}</div>
            <div class="text-xs font-mono text-gray-600">{{ number_format($actualVal, 2) }} {{ $uom }}</div>
        </div>
    @endif

    <div class="mt-2 relative h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-2 {{ $tone }}" style="width: {{ $percent }}%"></div>
        <div class="absolute inset-y-0 w-0.5 bg-gray-900/70" style="left: {{ $minPercent }}%" title="Min Stock"></div>
    </div>

    <div class="mt-1 text-[11px] text-gray-500">
        Min: <span class="font-mono">{{ number_format($minVal, 2) }}</span> {{ $uom }}
    </div>
</div>
