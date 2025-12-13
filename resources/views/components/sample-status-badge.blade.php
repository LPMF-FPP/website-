<div @class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $colors[0] ?? 'bg-gray-100', $colors[1] ?? 'text-gray-800'])>
    @if($showLabel)
        {{ $label }}
    @else
        <span class="sr-only">{{ $label }}</span>
    @endif
</div>
