@php
    $isEdit = isset($visit) && $visit !== null && $visit->exists;
    $casePurposes = \App\Enums\GuestVisitPurpose::casePurposes();
    $allPurposes = \App\Enums\GuestVisitPurpose::all();
    $selectedInvestigatorId = old('investigator_id', $visit?->investigator_id ?? null);
    $visitName = old('visitor_name', $visit?->visitor_name ?? '');
    $visitIdentity = old('visitor_identity', $visit?->visitor_identity ?? '');
    $visitInstitution = old('visitor_institution', $visit?->visitor_institution ?? '');
    $visitRelation = old('visitor_relation', $visit?->visitor_relation ?? '');
    $visitPhone = old('visitor_phone', $visit?->visitor_phone ?? '');
    $visitSameAsOwner = old('same_as_owner', isset($visit) ? ($visit->isVisitorVerified() ? false : true) : true);
    $currentPurpose = old('purpose', $visit?->purpose ?? '');
@endphp

<div x-data="guestBookForm({{ Js::from([
    'isEdit' => $isEdit,
    'selectedInvestigatorId' => $selectedInvestigatorId,
    'oldVisitorName' => $visitName,
    'oldVisitorIdentity' => $visitIdentity,
    'oldVisitorInstitution' => $visitInstitution,
    'oldVisitorRelation' => $visitRelation,
    'oldVisitorPhone' => $visitPhone,
    'oldSameAsOwner' => $visitSameAsOwner,
    'currentPurpose' => $currentPurpose,
    'casePurposes' => $casePurposes,
]) }})" class="space-y-6">

    {{-- Info Kunjungan --}}
    <div class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Info Kunjungan</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="visit_date" class="block text-sm font-medium text-gray-700">Tanggal *</label>
                <input type="date" name="visit_date" id="visit_date"
                       value="{{ old('visit_date', $visit?->visit_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       required>
                @error('visit_date') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visit_time" class="block text-sm font-medium text-gray-700">Jam *</label>
                <input type="time" name="visit_time" id="visit_time"
                       value="{{ old('visit_time', $visit?->visit_time ?? now()->format('H:i')) }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       required>
                @error('visit_time') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="purpose" class="block text-sm font-medium text-gray-700">Keperluan *</label>
                <select name="purpose" id="purpose" x-model="purpose"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                        required>
                    <option value="">Pilih keperluan...</option>
                    @foreach($allPurposes as $p)
                        <option value="{{ $p }}" @selected(old('purpose', $visit?->purpose ?? '') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
                @error('purpose') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div x-show="purpose === 'Lainnya'" x-cloak class="md:col-span-2">
                <label for="purpose_detail" class="block text-sm font-medium text-gray-700">Sebutkan keperluan *</label>
                <input type="text" name="purpose_detail" id="purpose_detail"
                       value="{{ old('purpose_detail', $visit?->purpose_detail ?? '') }}"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Tulis keperluan singkat..."
                       maxlength="255">
                @error('purpose_detail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="host_id" class="block text-sm font-medium text-gray-700">Petugas Penerima</label>
                <select name="host_id" id="host_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Pilih petugas...</option>
                    @foreach($hosts as $host)
                        <option value="{{ $host->id }}" @selected(old('host_id', $visit?->host_id ?? '') == $host->id)>{{ $host->name }}</option>
                    @endforeach
                </select>
                @error('host_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="md:col-span-2">
                <label for="notes" class="block text-sm font-medium text-gray-700">Catatan</label>
                <textarea name="notes" id="notes" rows="2"
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="Catatan tambahan...">{{ old('notes', $visit?->notes ?? '') }}</textarea>
                @error('notes') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- Pemilik Kasus (hanya create, hanya keperluan kasus) --}}
    <div x-show="showCaseFields" x-cloak class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Pemilik Kasus *</h3>
        <div>
            <select name="investigator_id" id="investigator_id" x-model="selectedId"
                    :required="showCaseFields"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                <option value="">Cari penyidik...</option>
                @foreach($investigators as $inv)
                    <option value="{{ $inv->id }}"
                            data-name="{{ $inv->name }}"
                            data-nrp="{{ $inv->nrp }}"
                            data-phone="{{ $inv->phone }}"
                            data-institution="{{ $inv->jurisdiction ?? $inv->institution }}"
                            @selected(old('investigator_id') == $inv->id)>
                        {{ $inv->full_name ?? $inv->name }} ({{ $inv->nrp }}) - {{ $inv->jurisdiction ?? $inv->institution }}
                    </option>
                @endforeach
            </select>
            @error('investigator_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Pihak Yang Datang — Mode Kasus --}}
    <div x-show="showCaseFields" x-cloak class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Pihak Yang Datang</h3>

        <div class="mb-4">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="same_as_owner" value="1" x-model="sameAsOwner"
                       :disabled="!selectedId"
                       class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-gray-700">
                    Pemilik kasus = pihak yang datang<br>
                    <span class="text-xs text-gray-400">Nama & data otomatis terisi dari data penyidik</span>
                </span>
            </label>
        </div>

        <div x-show="!sameAsOwner" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="visitor_name" class="block text-sm font-medium text-gray-700">Nama *</label>
                <input type="text" name="visitor_name" id="visitor_name" x-model="visitorName"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nama lengkap">
                @error('visitor_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_identity" class="block text-sm font-medium text-gray-700">NRP / Identitas</label>
                <input type="text" name="visitor_identity" id="visitor_identity" x-model="visitorIdentity"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nomor identitas">
                @error('visitor_identity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_relation" class="block text-sm font-medium text-gray-700">Relasi *</label>
                <select name="visitor_relation" id="visitor_relation" x-model="visitorRelation"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="">Pilih relasi...</option>
                    @foreach(['Penyidik', 'Staf', 'Kurir', 'Perwakilan', 'Lainnya'] as $rel)
                        <option value="{{ $rel }}">{{ $rel }}</option>
                    @endforeach
                </select>
                @error('visitor_relation') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_phone" class="block text-sm font-medium text-gray-700">Telepon</label>
                <input type="text" name="visitor_phone" id="visitor_phone" x-model="visitorPhone"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nomor telepon">
                @error('visitor_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div x-show="sameAsOwner" x-cloak class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <span class="block text-sm font-medium text-gray-500">Nama (otomatis)</span>
                <p class="text-sm font-medium text-gray-900" x-text="visitorName || '—'"></p>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-500">NRP / Identitas</span>
                <p class="text-sm font-medium text-gray-900" x-text="visitorIdentity || '—'"></p>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-500">Relasi</span>
                <p class="text-sm font-medium text-gray-900">Penyidik</p>
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-500">Telepon</span>
                <p class="text-sm font-medium text-gray-900" x-text="visitorPhone || '—'"></p>
            </div>
            <input type="hidden" name="visitor_name" :value="visitorName">
            <input type="hidden" name="visitor_identity" :value="visitorIdentity">
            <input type="hidden" name="visitor_relation" value="Penyidik">
            <input type="hidden" name="visitor_phone" :value="visitorPhone">
        </div>
    </div>

    {{-- Pihak Yang Datang — Mode Non-Kasus --}}
    <div x-show="!showCaseFields" x-cloak class="bg-white rounded-lg shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-4">Pihak Yang Datang</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="visitor_name_nc" class="block text-sm font-medium text-gray-700">Nama *</label>
                <input type="text" name="visitor_name" id="visitor_name_nc" x-model="visitorName"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nama lengkap">
                @error('visitor_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_identity_nc" class="block text-sm font-medium text-gray-700">Identitas (NIK/NRP) *</label>
                <input type="text" name="visitor_identity" id="visitor_identity_nc" x-model="visitorIdentity"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nomor identitas">
                @error('visitor_identity') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_institution" class="block text-sm font-medium text-gray-700">Asal Instansi *</label>
                <input type="text" name="visitor_institution" id="visitor_institution" x-model="visitorInstitution"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nama instansi/perusahaan">
                @error('visitor_institution') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="visitor_phone_nc" class="block text-sm font-medium text-gray-700">Telepon *</label>
                <input type="text" name="visitor_phone" id="visitor_phone_nc" x-model="visitorPhone"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                       placeholder="Nomor telepon">
                @error('visitor_phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('guestBookForm', (config) => ({
        purpose: config.currentPurpose,
        casePurposes: config.casePurposes,
        selectedId: config.selectedInvestigatorId || '',
        sameAsOwner: config.oldSameAsOwner,
        visitorName: config.oldVisitorName,
        visitorIdentity: config.oldVisitorIdentity,
        visitorInstitution: config.oldVisitorInstitution,
        visitorRelation: config.oldVisitorRelation,
        visitorPhone: config.oldVisitorPhone,

        get showCaseFields() {
            return this.casePurposes.includes(this.purpose);
        },

        init() {
            this.$watch('purpose', (val) => {
                if (!this.casePurposes.includes(val)) {
                    this.sameAsOwner = false;
                }
            });

            this.$watch('sameAsOwner', (val) => {
                if (val && this.selectedId) {
                    this.autoFill();
                }
            });

            this.$watch('selectedId', (val) => {
                if (val && this.sameAsOwner) {
                    this.autoFill();
                }
            });
        },

        autoFill() {
            const sel = document.getElementById('investigator_id');
            if (!sel || !sel.selectedOptions.length) return;
            const opt = sel.selectedOptions[0];
            this.visitorName = opt.dataset.name || '';
            this.visitorIdentity = opt.dataset.nrp || '';
            this.visitorPhone = opt.dataset.phone || '';
            this.visitorRelation = 'Penyidik';
        },
    }));
});
</script>
