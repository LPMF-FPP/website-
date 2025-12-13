@props(['status'])
@php
    $map = [
        'completed' => [__('tracking.badge_status.completed'), 'bg-success-600 text-white'],
        'current' => [__('tracking.badge_status.current'), 'bg-primary-600 text-white'],
        'pending' => [__('tracking.badge_status.pending'), 'bg-sem-mute text-sem-dim'],
    ];
    [$label, $classes] = $map[$status] ?? ['-', 'bg-sem-mute text-sem-dim'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-block px-2 py-0.5 text-xs rounded ' . $classes]) }}>{{ $label }}</span>
