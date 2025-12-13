{{-- Enhanced Alert Component with Typography and Icons --}}
@props([
    'type' => 'info',
    'title' => null,
    'dismissible' => false,
    'icon' => null,
    'bordered' => true,
    'filled' => false
])

@php
$classes = 'alert relative';

$iconName = $icon ?? match($type) {
    'success' => 'check',
    'warning' => 'warning',
    'error' => 'x',
    'info' => 'info',
    default => 'info'
};

$classes .= match($type) {
    'success' => $filled ? ' alert-success-filled bg-green-50 border-green-200 text-green-800' : ' alert-success border-green-300 bg-green-50',
    'warning' => $filled ? ' alert-warning-filled bg-yellow-50 border-yellow-200 text-yellow-800' : ' alert-warning border-yellow-300 bg-yellow-50',
    'error' => $filled ? ' alert-error-filled bg-red-50 border-red-200 text-red-800' : ' alert-error border-red-300 bg-red-50',
    'info' => $filled ? ' alert-info-filled bg-blue-50 border-blue-200 text-blue-800' : ' alert-info border-blue-300 bg-blue-50',
    default => $filled ? ' alert-info-filled bg-blue-50 border-blue-200 text-blue-800' : ' alert-info border-blue-300 bg-blue-50'
};

if (!$bordered) {
    $classes .= ' border-0';
}
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}
    role="alert">
    <div class="flex items-start gap-3">
        <!-- Icon -->
        <div class="flex-shrink-0 mt-0.5">
            <x-icon
                :name="$iconName"
                size="md"
                :color="match($type) {
                    'success' => 'success',
                    'warning' => 'warning',
                    'error' => 'danger',
                    'info' => 'info',
                    default => 'info'
                }"
                class="opacity-80"
            />
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
            @if($title)
                <h3 class="typography-subtitle font-display font-semibold mb-1 tracking-tight">
                    {{ $title }}
                </h3>
                <div class="typography-body font-body leading-relaxed">
                    {{ $slot }}
                </div>
            @else
                <div class="typography-body font-body leading-relaxed">
                    {{ $slot }}
                </div>
            @endif
        </div>

        <!-- Dismiss Button -->
        @if($dismissible)
            <div class="flex-shrink-0">
                <button
                    type="button"
                    onclick="this.closest('[role=alert]')?.remove()"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-md transition-colors duration-200 hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    aria-label="Dismiss alert"
                >
                    <x-icon name="x" size="sm" class="opacity-60 hover:opacity-80" />
                </button>
            </div>
        @endif
    </div>

    <!-- Optional Action Buttons -->
    @if(isset($actions))
        <div class="mt-4 flex items-center gap-3">
            {{ $actions }}
        </div>
    @endif
</div>
