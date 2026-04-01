<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Eksekusi Pemusnahan"
            :breadcrumbs="[
                ['label' => 'Inventori', 'route' => 'inventory.dashboard'],
                ['label' => 'Pemusnahan Sampel', 'route' => 'inventory.disposal.index'],
                ['label' => 'Eksekusi']
            ]"
        >
            <x-slot name="actions">
                <a href="{{ route('inventory.disposal.index') }}"
                    class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                    ← Kembali
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if($selectedSamples->isEmpty())
            <div class="card text-center py-12">
                <div class="text-4xl mb-4">⚠️</div>
                <p class="text-gray-600 mb-4">Tidak ada sampel yang dipilih atau sampel tidak eligible.</p>
                <a href="{{ route('inventory.disposal.index') }}" class="text-primary-600 hover:underline">
                    Kembali ke daftar sampel
                </a>
            </div>
        @else
            <form action="{{ route('inventory.disposal.store') }}" method="POST" class="space-y-6">
                @csrf

                {{-- Selected Samples Summary --}}
                <div class="card">
                    <h3 class="text-lg font-semibold mb-4">Sampel yang akan Dimusnahkan ({{ $selectedSamples->count() }})</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Kode Sampel</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">No. LHU</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Tersangka</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Jenis</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($selectedSamples as $sample)
                                @php
                                    $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                                    $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ $sample->sample_code }}
                                        <input type="hidden" name="sample_ids[]" value="{{ $sample->id }}">
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $lhuNumber }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $sample->testRequest?->investigator?->suspect_name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">
                                        {{ $sample->short_description ?? $sample->sample_form }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Disposal Form --}}
                <div class="card">
                    <h3 class="text-lg font-semibold mb-4">Detail Pemusnahan</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Method --}}
                        <div>
                            <label for="method" class="block text-sm font-medium text-gray-700 mb-1">
                                Metode Pemusnahan <span class="text-red-500">*</span>
                            </label>
                            <select name="method" id="method" required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Metode --</option>
                                @foreach($methods as $value => $label)
                                    <option value="{{ $value }}" {{ old('method') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('method')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Witness User --}}
                        <div class="md:col-span-2">
                            <label for="witness_user_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Saksi (User)
                            </label>
                            <select name="witness_user_id" id="witness_user_id"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                <option value="">-- Pilih Saksi --</option>
                                @foreach($witnessUsers as $witnessUser)
                                    @php
                                        $identityNumber = $witnessUser->nrp ?: $witnessUser->nip;
                                        $identityLabel = $witnessUser->nrp ? 'NRP' : ($witnessUser->nip ? 'NIP' : null);
                                        $identityText = $identityLabel && $identityNumber
                                            ? "{$identityLabel}: {$identityNumber}"
                                            : null;
                                    @endphp
                                    <option value="{{ $witnessUser->id }}" {{ (string) old('witness_user_id') === (string) $witnessUser->id ? 'selected' : '' }}>
                                        {{ $witnessUser->display_name_with_title }}
                                        @if($witnessUser->rank)
                                            — {{ $witnessUser->rank }}
                                        @endif
                                        @if($identityText)
                                            — {{ $identityText }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Opsional. Jika tidak dipilih, isi nama dan jabatan saksi secara manual.</p>
                            @error('witness_user_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="witness_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Nama Saksi (Manual)
                            </label>
                            <input type="text" name="witness_name" id="witness_name"
                                value="{{ old('witness_name') }}"
                                placeholder="Isi jika saksi bukan user sistem"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('witness_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="witness_role" class="block text-sm font-medium text-gray-700 mb-1">
                                Jabatan Saksi (Manual)
                            </label>
                            <input type="text" name="witness_role" id="witness_role"
                                value="{{ old('witness_role') }}"
                                placeholder="Contoh: Kepala Lab / Penyidik"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('witness_role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Notes --}}
                        <div class="md:col-span-2">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                Catatan (Opsional)
                            </label>
                            <textarea name="notes" id="notes" rows="3"
                                placeholder="Catatan tambahan tentang proses pemusnahan..."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex justify-end gap-3">
                    <a href="{{ route('inventory.disposal.index') }}"
                        class="inline-flex items-center rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">
                        Batal
                    </a>
                    <button type="submit"
                        class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700"
                        onclick="return confirm('Apakah Anda yakin ingin memusnahkan {{ $selectedSamples->count() }} sampel? Tindakan ini tidak dapat dibatalkan.')">
                        🔥 Eksekusi Pemusnahan
                    </button>
                </div>
            </form>
        @endif
    </div>
</x-app-layout>
