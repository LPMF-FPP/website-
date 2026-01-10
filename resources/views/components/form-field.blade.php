@props([
    'name',
    'label',
    'type' => 'text',
    'value' => '',
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $errorId = $name . '-error';
    $helpId = $name . '-help';
    $hasError = $errors->has($name);
    
    $ariaDescribedBy = [];
    if ($help) $ariaDescribedBy[] = $helpId;
    if ($hasError) $ariaDescribedBy[] = $errorId;
    $ariaDescribedByString = !empty($ariaDescribedBy) ? implode(' ', $ariaDescribedBy) : null;
@endphp

<div class="space-y-1">
    {{-- Label --}}
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if($required)
            <span class="text-red-500" aria-hidden="true">*</span>
            <span class="sr-only">{{ __('(required)') }}</span>
        @endif
    </label>

    {{-- Input --}}
    <input 
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $id }}"
        value="{{ old($name, $value) }}"
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($required) required aria-required="true" @endif
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" @endif
        @if($ariaDescribedByString) aria-describedby="{{ $ariaDescribedByString }}" @endif
        {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md shadow-sm ' . 
            ($hasError 
                ? 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500' 
                : 'border-primary-200 focus:border-primary-500 focus:ring-primary-500')
        ]) }}
    >

    {{-- Help Text --}}
    @if($help)
        <p id="{{ $helpId }}" class="mt-1 text-sm text-gray-500">
            {{ $help }}
        </p>
    @endif

    {{-- Error Message --}}
    @error($name)
        <p id="{{ $errorId }}" class="mt-1 text-sm text-red-600" role="alert">
            {{ $message }}
        </p>
    @enderror
</div>

{{-- 
    Usage Examples:

    1. Basic Text Input:
    <x-form-field name="first_name" label="First Name" />

    2. Required Email with Placeholder:
    <x-form-field 
        name="email" 
        label="Email Address" 
        type="email" 
        required 
        placeholder="you@example.com" 
    />

    3. With Help Text:
    <x-form-field 
        name="password" 
        label="Password" 
        type="password" 
        help="Must be at least 8 characters." 
    />

    4. Pre-filled Value (automatically handles old() input, fallback to prop):
    <x-form-field name="bio" label="Biography" :value="$user->bio" />
--}}
