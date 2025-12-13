@props([
    // Backward compatibility: either pass label+variant, or a single 'status' value
    'status' => null,
    'label' => null,
    'variant' => null,
    'subtle' => false,
    'icon' => null,
    'showIcon' => false,
    'dot' => false,
])

@php
    // Legacy mapping
    $raw = $status ?? $variant ?? 'neutral';
    $map = [
        'pending' => ['warning', 'Menunggu'],
        'in_progress' => ['info', 'Diproses'],
        'processing' => ['info', 'Diproses'],
        'completed' => ['success', 'Selesai'],
        'done' => ['success', 'Selesai'],
        'rejected' => ['danger', 'Ditolak'],
        'failed' => ['danger', 'Gagal'],
        'cancelled' => ['danger', 'Dibatalkan'],
        'ready_for_delivery' => ['primary', 'Siap Diserahkan'],
        'delivered' => ['secondary', 'Diserahkan'],
        'draft' => ['neutral', 'Draft'],
    ];

    if (!$variant) {
        [$variant, $autoLabel] = $map[$raw] ?? ['neutral', ucfirst(str_replace('_', ' ', $raw))];
        $label = $label ?? $autoLabel;
    } else {
        $label = $label ?? ucfirst(str_replace('_', ' ', $raw));
    }

    $variant = $variant ?: 'neutral';

    // Semantic color fallback mapping (subtle background via color family 100) remains for now.
    $variantColors = [
        'primary' => ['bg-primary-100 text-primary-800 ring-primary-300', 'bg-primary-600 text-white'],
        'secondary' => ['bg-secondary-100 text-secondary-800 ring-secondary-300', 'bg-secondary-600 text-white'],
        'success' => ['bg-success-100 text-success-800 ring-success-300', 'bg-success-600 text-white'],
        'warning' => ['bg-warning-100 text-warning-800 ring-warning-300', 'bg-warning-500 text-accent-900'],
        'danger' => ['bg-danger-100 text-danger-800 ring-danger-300', 'bg-danger-600 text-white'],
        'info' => ['bg-info-100 text-info-800 ring-info-300', 'bg-info-600 text-white'],
        'neutral' => ['bg-accent-100 text-accent-700 ring-accent-300', 'bg-accent-500 text-white'],
    ];

    [$bgSubtle, $bgBold] = $variantColors[$variant] ?? $variantColors['neutral'];

    $base = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset';

    // When semantic subtle mode desired in future: could map to --pd-sem-color-* tokens.
    $classes = $base . ' ' . ($subtle ? $bgSubtle : $bgBold . ' shadow-sm');

    $iconMap = [
        'success' => 'check-circle',
        'warning' => 'alert-triangle',
        'danger' => 'x-circle',
        'error' => 'x-circle',
        'info' => 'info',
        'primary' => 'dot',
        'secondary' => 'dot',
        'neutral' => 'dot',
    ];

    $chosenIcon = $icon ?? ($iconMap[$variant] ?? null);
    $showIcon = $showIcon || $dot; // dot implies icon placeholder
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if($showIcon)
        @if($dot)
            <span class="inline-block h-2 w-2 rounded-full bg-current opacity-70"></span>
        @elseif($chosenIcon)
            <x-icon :name="$chosenIcon" size="xs" class="opacity-70" />
        @endif
    @endif
    <span>{{ $label }}</span>
</span>

{{-- Deprecated simple version kept for reference:
<span {{ $attributes->merge(['class' => trim("badge {$class}")]) }}>{{ $label }}</span>
--}}
