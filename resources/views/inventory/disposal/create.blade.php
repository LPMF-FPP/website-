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
                                        {{ $sample->testRequest?->suspect_name ?? '-' }}
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
                <div class="card" x-data="disposalWitnesses()">
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

                        <div>
                            <label for="executor_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Nama Pelaksana (Manual)
                            </label>
                            <input type="text" name="executor_name" id="executor_name"
                                value="{{ old('executor_name') }}"
                                placeholder="Kosongkan untuk pakai user login"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('executor_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="executor_role" class="block text-sm font-medium text-gray-700 mb-1">
                                Jabatan Pelaksana (Manual)
                            </label>
                            <input type="text" name="executor_role" id="executor_role"
                                value="{{ old('executor_role') }}"
                                placeholder="Kosongkan untuk pakai data user login"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('executor_role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="executor_identity" class="block text-sm font-medium text-gray-700 mb-1">
                                Identitas Pelaksana (Manual)
                            </label>
                            <input type="text" name="executor_identity" id="executor_identity"
                                value="{{ old('executor_identity') }}"
                                placeholder="Contoh: NRP: 12345678"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('executor_identity')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="md:col-span-2 space-y-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Daftar Saksi</label>
                                    <p class="text-xs text-gray-500">Tambahkan satu atau lebih saksi. Anda bisa pilih user sistem atau isi manual.</p>
                                </div>
                                <button type="button"
                                    @click="addWitness()"
                                    class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-primary-700 shadow-sm ring-1 ring-primary-200 hover:bg-primary-50">
                                    + Tambah Saksi
                                </button>
                            </div>

                            @error('witnesses')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <template x-for="(witness, index) in witnesses" :key="index">
                                <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <h4 class="text-sm font-semibold text-gray-900" x-text="`Saksi ${index + 1}`"></h4>
                                        <button type="button"
                                            x-show="witnesses.length > 1"
                                            @click="removeWitness(index)"
                                            class="text-sm font-medium text-red-600 hover:text-red-700">
                                            Hapus
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div class="md:col-span-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-1" :for="`witness-user-${index}`">Saksi (User)</label>
                                            <select :name="`witnesses[${index}][user_id]`" :id="`witness-user-${index}`" x-model="witness.user_id"
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
                                                    <option value="{{ $witnessUser->id }}">
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
                                            <template x-if="errors[`witnesses.${index}.user_id`]">
                                                <p class="mt-1 text-sm text-red-600" x-text="errors[`witnesses.${index}.user_id`]"></p>
                                            </template>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1" :for="`witness-name-${index}`">Nama Saksi (Manual)</label>
                                            <input type="text" :name="`witnesses[${index}][name]`" :id="`witness-name-${index}`" x-model="witness.name"
                                                placeholder="Isi jika perlu override atau saksi eksternal"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <template x-if="errors[`witnesses.${index}.name`]">
                                                <p class="mt-1 text-sm text-red-600" x-text="errors[`witnesses.${index}.name`]"></p>
                                            </template>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1" :for="`witness-role-${index}`">Jabatan Saksi (Manual)</label>
                                            <input type="text" :name="`witnesses[${index}][role]`" :id="`witness-role-${index}`" x-model="witness.role"
                                                placeholder="Contoh: Kepala Lab / Penyidik"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <template x-if="errors[`witnesses.${index}.role`]">
                                                <p class="mt-1 text-sm text-red-600" x-text="errors[`witnesses.${index}.role`]"></p>
                                            </template>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1" :for="`witness-identity-${index}`">Identitas Saksi (Manual)</label>
                                            <input type="text" :name="`witnesses[${index}][identity]`" :id="`witness-identity-${index}`" x-model="witness.identity"
                                                placeholder="Contoh: NRP: 12345678"
                                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                                            <template x-if="errors[`witnesses.${index}.identity`]">
                                                <p class="mt-1 text-sm text-red-600" x-text="errors[`witnesses.${index}.identity`]"></p>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
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

                        <div>
                            <label for="approver_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Nama Kepala Farmapol (Manual)
                            </label>
                            <input type="text" name="approver_name" id="approver_name"
                                value="{{ old('approver_name') }}"
                                placeholder="Isi nama Kepala Farmapol"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('approver_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="approver_role" class="block text-sm font-medium text-gray-700 mb-1">
                                Pangkat Kepala Farmapol (Manual)
                            </label>
                            <input type="text" name="approver_role" id="approver_role"
                                value="{{ old('approver_role') }}"
                                placeholder="Contoh: AKBP / KBP / KOMBES POL."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('approver_role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="approver_identity" class="block text-sm font-medium text-gray-700 mb-1">
                                Identitas Kepala Farmapol (Manual)
                            </label>
                            <input type="text" name="approver_identity" id="approver_identity"
                                value="{{ old('approver_identity') }}"
                                placeholder="Contoh: NRP: 12345678"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            @error('approver_identity')
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

    <script type="application/json" id="disposal-witness-rows">@json($witnessRows)</script>
    <script type="application/json" id="disposal-witness-errors">@json($errors->getMessages())</script>

    @push('scripts')
    <script>
        function disposalWitnesses() {
            const witnessRows = JSON.parse(document.getElementById('disposal-witness-rows').textContent || '[]');
            const witnessErrors = JSON.parse(document.getElementById('disposal-witness-errors').textContent || '{}');

            return {
                witnesses: witnessRows,
                errors: witnessErrors,
                addWitness() {
                    this.witnesses.push({ user_id: '', name: '', role: '', identity: '' });
                },
                removeWitness(index) {
                    this.witnesses.splice(index, 1);

                    const nextErrors = {};

                    Object.entries(this.errors).forEach(([key, value]) => {
                        const match = key.match(/^witnesses\.(\d+)\.(.+)$/);

                        if (!match) {
                            nextErrors[key] = value;
                            return;
                        }

                        const errorIndex = Number(match[1]);
                        const field = match[2];

                        if (errorIndex === index) {
                            return;
                        }

                        const nextKey = errorIndex > index
                            ? `witnesses.${errorIndex - 1}.${field}`
                            : key;

                        nextErrors[nextKey] = value;
                    });

                    this.errors = nextErrors;

                    if (this.witnesses.length === 0) {
                        this.addWitness();
                    }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
