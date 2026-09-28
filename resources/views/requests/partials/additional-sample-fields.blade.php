<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="supplement_reason" class="block text-sm font-medium text-gray-700">Alasan penambahan <span class="text-red-600">*</span></label>
        <textarea id="supplement_reason" name="supplement_reason" rows="2" required maxlength="2000" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('supplement_reason') }}</textarea>
        @error('supplement_reason')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="short_description" class="block text-sm font-medium text-gray-700">Deskripsi singkat <span class="text-red-600">*</span></label>
        <input id="short_description" name="short_description" value="{{ old('short_description') }}" required maxlength="255" class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('short_description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sample_form" class="block text-sm font-medium text-gray-700">Bentuk sampel <span class="text-red-600">*</span></label>
        <select id="sample_form" name="sample_form" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <option value="">Pilih bentuk</option>
            @foreach ($sampleForms as $form)
                <option value="{{ $form }}" @selected(old('sample_form') === $form)>{{ ucfirst($form) }}</option>
            @endforeach
        </select>
        @error('sample_form')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sample_category" class="block text-sm font-medium text-gray-700">Kategori sampel <span class="text-red-600">*</span></label>
        <select id="sample_category" name="sample_category" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <option value="">Pilih kategori</option>
            @foreach ($sampleCategories as $category => $label)
                <option value="{{ $category }}" @selected(old('sample_category') === $category)>{{ $label }}</option>
            @endforeach
        </select>
        @error('sample_category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div id="other-sample-category-wrapper" class="hidden sm:col-span-2">
        <label for="other_sample_category" class="block text-sm font-medium text-gray-700">Jenis kategori lainnya <span class="text-red-600">*</span></label>
        <select id="other_sample_category" name="other_sample_category" class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <option value="">Pilih jenis</option>
            @foreach ($otherSampleOptions as $key => $label)
                <option value="{{ $key }}" @selected(old('other_sample_category') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('other_sample_category')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="package_quantity" class="block text-sm font-medium text-gray-700">Jumlah <span class="text-red-600">*</span></label>
        <input id="package_quantity" type="number" min="1" step="1" name="package_quantity" value="{{ old('package_quantity', 1) }}" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('package_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="unit" class="block text-sm font-medium text-gray-700">Satuan <span class="text-red-600">*</span></label>
        <input id="unit" name="unit" value="{{ old('unit') }}" required maxlength="50" class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('unit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="sample_weight" class="block text-sm font-medium text-gray-700">Berat bersih (opsional)</label>
        <input id="sample_weight" type="number" min="0" step="0.01" name="sample_weight" value="{{ old('sample_weight') }}" class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
        @error('sample_weight')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="condition" class="block text-sm font-medium text-gray-700">Kondisi sampel <span class="text-red-600">*</span></label>
        <select id="condition" name="condition" required class="mt-1 min-h-11 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            @foreach (['baik' => 'Baik', 'rusak' => 'Rusak', 'basah' => 'Basah', 'kering' => 'Kering'] as $value => $label)
                <option value="{{ $value }}" @selected(old('condition', 'baik') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('condition')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2">
        <label for="sample_description" class="block text-sm font-medium text-gray-700">Keterangan tambahan</label>
        <textarea id="sample_description" name="sample_description" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('sample_description') }}</textarea>
        @error('sample_description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
