{{-- Form Input Component --}}
@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'help' => null,
    'error' => null,
    'required' => false
])

@php
$inputClasses = 'form-input';
if ($error) {
    $inputClasses .= ' form-input-error';
}
@endphp

<div class="form-group">
    @if($label)
        <label for="{{ $name }}" class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($value) value="{{ $value }}" @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => $inputClasses]) }}
    >

    @if($help)
        <p class="form-help">{{ $help }}</p>
    @endif

    @if($error)
        <p class="form-error">{{ $error }}</p>
    @endif
</div>
