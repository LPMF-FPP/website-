@csrf

@php
    $jurisdictionLabel = $investigator->is_polri ? 'Satker' : 'Instansi';
@endphp

<div class="space-y-6">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Nama</label>
            <input type="text" value="{{ $investigator->name }}" disabled
                class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">NRP</label>
            <input type="text" value="{{ $investigator->nrp }}" disabled
                class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Pangkat</label>
            <input type="text" name="rank" value="{{ old('rank', $investigator->rank) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            @error('rank')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">{{ $jurisdictionLabel }}</label>
            <input type="text" name="jurisdiction" value="{{ old('jurisdiction', $investigator->jurisdiction) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            @error('jurisdiction')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">No. HP</label>
            <input type="text" name="phone" value="{{ old('phone', $investigator->phone) }}" required
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            @error('phone')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $investigator->email) }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            @error('email')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700">Tipe</label>
        <input type="text" value="{{ $investigator->is_polri ? 'Polri' : 'Non-Polri' }}" disabled
            class="mt-1 block w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm">
    </div>
</div>
