@props([
    'name',
    'label',
    'type' => 'text',
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'value' => null
])

<div class="space-y-1">
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
        @if($required)
            <span class="text-red-500">*</span>
        @endif
    </label>

    @if($help)
        <p class="text-xs text-gray-500">{{ $help }}</p>
    @endif

    <input type="{{ $type }}"
           id="{{ $name }}"
           name="{{ $name }}"
           value="{{ old($name, $value) }}"
           @if($placeholder) placeholder="{{ $placeholder }}" @endif
           @if($required) required @endif
           {{ $attributes->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm ' . ($errors->has($name) ? 'border-red-300 text-red-900 focus:border-red-500 focus:ring-red-500' : '')]) }}>

    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
