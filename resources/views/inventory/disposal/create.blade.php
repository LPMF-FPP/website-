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
                <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                    class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 active:translate-y-[1px]">
                    Kembali
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if($selectedSamples->isEmpty())
            <section class="rounded-[28px] border border-amber-200 bg-gradient-to-br from-white via-amber-50 to-rose-50 px-6 py-12 text-center shadow-[0_24px_60px_-36px_rgba(180,83,9,0.35)] sm:px-8">
                <div class="mx-auto max-w-2xl space-y-4">
                    <div class="inline-flex items-center rounded-full border border-amber-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">
                        Batch Tidak Tersedia
                    </div>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                        Tidak ada sampel eligible yang bisa dimuat ke batch eksekusi.
                    </h2>
                    <p class="mx-auto max-w-xl text-sm leading-7 text-slate-600 sm:text-base">
                        Sampel bisa saja sudah diproses, tidak lagi memenuhi syarat retensi, atau parameter `sample_ids` yang digunakan tidak valid.
                    </p>
                    <div class="pt-2">
                        <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                            class="inline-flex items-center rounded-full border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 active:translate-y-[1px]">
                            Kembali ke Daftar Sampel
                        </a>
                    </div>
                </div>
            </section>
        @else
            @php
                $interpretationDates = $selectedSamples
                    ->map(function ($sample) {
                        return optional(
                            $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first()
                        )->completed_at;
                    })
                    ->filter();

                $oldestInterpretation = $interpretationDates->min();
                $latestInterpretation = $interpretationDates->max();

                $executionSectionOpen = $errors->hasAny([
                    'method',
                    'executor_name',
                    'executor_role',
                    'executor_identity',
                ]) || old('method') || old('executor_name') || old('executor_role') || old('executor_identity');

                $witnessSectionOpen = $errors->has('witnesses')
                    || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'witnesses.'))
                    || old('witnesses');

                $approverSectionOpen = $errors->hasAny([
                    'approver_name',
                    'approver_role',
                    'approver_identity',
                ]) || old('approver_name') || old('approver_role') || old('approver_identity');

                $documentationSectionOpen = $errors->hasAny([
                    'documentation_photos',
                    'documentation_photos.*',
                    'notes',
                ]) || old('notes');

                $finalSectionOpen = $errors->isNotEmpty();
            @endphp

            @if(!empty($selectedMonthLabel))
                <section class="overflow-hidden rounded-[28px] border border-amber-200 bg-gradient-to-r from-amber-50 via-white to-orange-50 shadow-[0_24px_60px_-36px_rgba(217,119,6,0.35)]">
                    <div class="flex flex-col gap-4 px-6 py-5 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                        <div class="space-y-2">
                            <div class="inline-flex items-center rounded-full border border-amber-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-amber-700">
                                Mode Bulanan
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                                    Batch disposal untuk {{ $selectedMonthLabel }} sudah siap dieksekusi.
                                </h2>
                                <p class="mt-1 max-w-3xl text-sm leading-7 text-slate-600">
                                    Semua sampel eligible dengan interpretasi selesai pada bulan ini dimuat otomatis ke form agar proses disposal rutin tetap konsisten dan cepat dieksekusi.
                                </p>
                            </div>
                        </div>

                        <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                            class="inline-flex items-center justify-center rounded-full border border-amber-200 bg-white px-5 py-2.5 text-sm font-semibold text-amber-700 shadow-sm transition hover:bg-amber-50 active:translate-y-[1px]">
                            Ganti Bulan
                        </a>
                    </div>
                </section>
            @endif

            <form action="{{ route('inventory.disposal.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <section class="overflow-hidden rounded-[30px] border border-rose-100 bg-gradient-to-br from-white via-rose-50 to-orange-50 shadow-[0_24px_60px_-32px_rgba(190,24,93,0.28)]">
                    <div class="space-y-5 px-5 py-5 lg:px-7 lg:py-7">
                        <div class="space-y-5">
                            <div class="inline-flex items-center rounded-full border border-rose-200 bg-white/80 px-3 py-1 text-xs font-semibold uppercase tracking-[0.22em] text-rose-700">
                                Disposal Execution Desk
                            </div>

                            <div class="space-y-3">
                                <h2 class="max-w-3xl text-[2rem] font-semibold tracking-tight text-slate-900 md:text-[2.6rem] md:leading-[1.05]">
                                    Finalisasi batch pemusnahan dengan jejak audit yang jelas, saksi lengkap, dan dokumentasi siap cetak.
                                </h2>
                                <p class="max-w-2xl text-sm leading-7 text-slate-600 md:text-base">
                                    Tinjau batch terlebih dahulu, lalu isi detail pelaksana, saksi, otorisasi, dan dokumentasi sebelum mengeksekusi pemusnahan.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Jumlah Sampel</div>
                                    <div class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ $selectedSamples->count() }}</div>
                                    <p class="mt-1 text-xs text-slate-500">Sampel masuk dalam batch eksekusi</p>
                                </div>
                                <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur sm:col-span-1 xl:col-span-2">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Rentang Interpretasi</div>
                                    <div class="mt-2 text-sm font-semibold text-slate-900">
                                        {{ $oldestInterpretation ? $oldestInterpretation->format('d M Y') : '-' }}
                                        <span class="text-slate-400">s/d</span>
                                        {{ $latestInterpretation ? $latestInterpretation->format('d M Y') : '-' }}
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Memudahkan verifikasi batch dan berita acara</p>
                                </div>
                                <div class="rounded-2xl border border-white/70 bg-white/80 p-4 shadow-sm backdrop-blur">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status Batch</div>
                                    <div class="mt-2 text-sm font-semibold text-rose-700">Siap Dieksekusi</div>
                                    <p class="mt-1 text-xs text-slate-500">Audit trail akan terbentuk saat disimpan</p>
                                </div>
                            </div>
                            <div class="rounded-[24px] border border-slate-200/70 bg-slate-950 p-5 text-slate-50 shadow-[0_20px_45px_-25px_rgba(15,23,42,0.8)]">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Ringkasan Operasi</div>
                                        <h3 class="mt-2 text-lg font-semibold text-white">Checklist sebelum submit</h3>
                                    </div>
                                    <div class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-slate-300">
                                        Audit Ready
                                    </div>
                                </div>

                                <div class="mt-4 space-y-2.5 text-sm leading-6 text-slate-300">
                                    <p>Pastikan metode pemusnahan sesuai berita acara.</p>
                                    <p>Minimal satu saksi valid wajib tersedia sebelum eksekusi.</p>
                                    <p>Periksa nama pelaksana dan Kepala Farmapol agar PDF tidak perlu koreksi ulang.</p>
                                </div>

                                <div class="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-4 text-sm leading-6 text-rose-100">
                                    Batch disposal yang sudah disimpan tidak dapat dibatalkan dari form ini.
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[0.98fr_1.02fr]">
                    <div class="space-y-6">
                        <details class="group rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" @if($executionSectionOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Eksekusi</div>
                                    <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Informasi pelaksana</h3>
                                    <p class="mt-1 text-sm text-slate-500">Isi manual hanya bila identitas penandatangan berbeda dari akun login aktif.</p>
                                </div>
                                <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                            </summary>

                            <div class="mt-6 grid gap-5 border-t border-slate-100 pt-6 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <label for="method" class="block text-sm font-medium text-slate-700">
                                            Metode Pemusnahan <span class="text-red-500">*</span>
                                        </label>
                                        <select name="method" id="method" required
                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-rose-400 focus:ring-rose-400">
                                            <option value="">-- Pilih Metode --</option>
                                            @foreach($methods as $value => $label)
                                                <option value="{{ $value }}" {{ old('method') === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('method')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label for="executor_name" class="block text-sm font-medium text-slate-700">Nama Pelaksana</label>
                                        <input type="text" name="executor_name" id="executor_name"
                                            value="{{ old('executor_name') }}"
                                            placeholder="Kosongkan untuk memakai user login"
                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                        <p class="text-xs text-slate-500">Pelaksana default akan diambil dari akun login saat field dikosongkan.</p>
                                        @error('executor_name')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label for="executor_role" class="block text-sm font-medium text-slate-700">Jabatan Pelaksana</label>
                                        <input type="text" name="executor_role" id="executor_role"
                                            value="{{ old('executor_role') }}"
                                            placeholder="Contoh: Ketua Tim Pemusnahan"
                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                        @error('executor_role')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="space-y-2">
                                        <label for="executor_identity" class="block text-sm font-medium text-slate-700">Identitas Pelaksana</label>
                                        <input type="text" name="executor_identity" id="executor_identity"
                                            value="{{ old('executor_identity') }}"
                                            placeholder="Contoh: NRP: 12345678"
                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                        @error('executor_identity')
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                        </details>

                        <details class="group rounded-[28px] border border-slate-200 bg-white p-6 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" x-data="disposalWitnesses()" @if($witnessSectionOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Saksi</div>
                                    <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Daftar saksi pemusnahan</h3>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan saksi internal atau eksternal. Minimal satu entri saksi harus valid sebelum batch dieksekusi.</p>
                                </div>
                                <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                            </summary>

                            <div class="mt-6 border-t border-slate-100 pt-6">
                                <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h4 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Daftar Saksi</h4>
                                            <p class="mt-1 text-sm text-slate-500">Minimal satu saksi harus valid. Pilih user aktif atau isi data saksi manual.</p>
                                        </div>
                                        <button type="button"
                                            @click="addWitness()"
                                            class="inline-flex items-center justify-center rounded-full border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 shadow-sm transition hover:bg-rose-50 active:translate-y-[1px]">
                                            Tambah Saksi
                                        </button>
                                    </div>

                                    @error('witnesses')
                                        <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                                    @enderror

                                    <div class="mt-5 space-y-4">
                                        <template x-for="(witness, index) in witnesses" :key="index">
                                            <div class="rounded-[24px] border border-white bg-white p-5 shadow-sm shadow-slate-200/60">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <h5 class="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500" x-text="`Saksi ${index + 1}`"></h5>
                                                        <p class="mt-1 text-sm text-slate-500">Isi manual hanya bila perlu override atau untuk saksi non-user.</p>
                                                    </div>
                                                    <button type="button"
                                                        x-show="witnesses.length > 1"
                                                        @click="removeWitness(index)"
                                                        class="text-sm font-semibold text-red-600 transition hover:text-red-700">
                                                        Hapus
                                                    </button>
                                                </div>

                                                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                                    <div class="space-y-2 xl:col-span-2">
                                                        <label class="block text-sm font-medium text-slate-700" :for="`witness-user-${index}`">Saksi dari User Sistem</label>
                                                        <select :name="`witnesses[${index}][user_id]`" :id="`witness-user-${index}`" x-model="witness.user_id"
                                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition focus:border-rose-400 focus:ring-rose-400">
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
                                                            <p class="text-sm text-red-600" x-text="errors[`witnesses.${index}.user_id`]"></p>
                                                        </template>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <label class="block text-sm font-medium text-slate-700" :for="`witness-name-${index}`">Nama Manual</label>
                                                        <input type="text" :name="`witnesses[${index}][name]`" :id="`witness-name-${index}`" x-model="witness.name"
                                                            placeholder="Nama saksi"
                                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                                        <template x-if="errors[`witnesses.${index}.name`]">
                                                            <p class="text-sm text-red-600" x-text="errors[`witnesses.${index}.name`]"></p>
                                                        </template>
                                                    </div>

                                                    <div class="space-y-2">
                                                        <label class="block text-sm font-medium text-slate-700" :for="`witness-role-${index}`">Jabatan Manual</label>
                                                        <input type="text" :name="`witnesses[${index}][role]`" :id="`witness-role-${index}`" x-model="witness.role"
                                                            placeholder="Contoh: Penyidik"
                                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                                        <template x-if="errors[`witnesses.${index}.role`]">
                                                            <p class="text-sm text-red-600" x-text="errors[`witnesses.${index}.role`]"></p>
                                                        </template>
                                                    </div>

                                                    <div class="space-y-2 md:col-span-2 xl:col-span-4">
                                                        <label class="block text-sm font-medium text-slate-700" :for="`witness-identity-${index}`">Identitas Saksi</label>
                                                        <input type="text" :name="`witnesses[${index}][identity]`" :id="`witness-identity-${index}`" x-model="witness.identity"
                                                            placeholder="Contoh: NRP: 12345678"
                                                            class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                                        <template x-if="errors[`witnesses.${index}.identity`]">
                                                            <p class="text-sm text-red-600" x-text="errors[`witnesses.${index}.identity`]"></p>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                        <details class="group rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]">
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Manifest Batch</div>
                                    <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Sampel yang akan dimusnahkan</h3>
                                    <p class="mt-1 text-sm text-slate-500">Manifest batch tetap terlihat saat Anda mengisi form eksekusi.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                        {{ $selectedSamples->count() }} sampel
                                    </div>
                                    <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                                </div>
                            </summary>

                            <div class="mt-4 overflow-hidden rounded-3xl border border-slate-200">
                                <div class="max-h-[620px] overflow-auto">
                                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead class="sticky top-0 z-10 bg-slate-50/95 text-slate-500 backdrop-blur">
                                            <tr>
                                                <th class="px-4 py-3 text-left font-semibold">Kode Sampel</th>
                                                <th class="px-4 py-3 text-left font-semibold">No. LHU</th>
                                                <th class="px-4 py-3 text-left font-semibold">Tersangka</th>
                                                <th class="px-4 py-3 text-left font-semibold">Jenis</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 bg-white">
                                            @foreach($selectedSamples as $sample)
                                                @php
                                                    $lhuProcess = $sample->testProcesses->where('stage', 'interpretation')->whereNotNull('completed_at')->first();
                                                    $lhuNumber = $lhuProcess?->metadata['lhu_number'] ?? '-';
                                                @endphp
                                                <tr class="align-top transition hover:bg-rose-50/40">
                                                    <td class="px-4 py-3 font-medium text-slate-900">
                                                        {{ $sample->sample_code }}
                                                        <input type="hidden" name="sample_ids[]" value="{{ $sample->id }}">
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-600">{{ $lhuNumber }}</td>
                                                    <td class="px-4 py-3 text-slate-600">{{ $sample->testRequest?->suspect_name ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-slate-600">{{ $sample->short_description ?? $sample->sample_form }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>

                        <details class="group rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" @if($approverSectionOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Otorisasi</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Data Kepala Farmapol</h3>
                                <p class="mt-1 text-sm text-slate-500">Data ini akan dipakai pada tampilan detail dan PDF berita acara pemusnahan.</p>
                                </div>
                                <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                            </summary>

                            <div class="mt-5 space-y-4 border-t border-slate-100 pt-5">
                                <div class="space-y-2">
                                    <label for="approver_name" class="block text-sm font-medium text-slate-700">Nama Kepala Farmapol</label>
                                    <input type="text" name="approver_name" id="approver_name"
                                        value="{{ old('approver_name') }}"
                                        placeholder="Isi nama Kepala Farmapol"
                                        class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                    @error('approver_name')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="approver_role" class="block text-sm font-medium text-slate-700">Pangkat atau Jabatan</label>
                                    <input type="text" name="approver_role" id="approver_role"
                                        value="{{ old('approver_role') }}"
                                        placeholder="Contoh: KBP / KOMBES POL."
                                        class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                    @error('approver_role')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="approver_identity" class="block text-sm font-medium text-slate-700">Identitas Kepala Farmapol</label>
                                    <input type="text" name="approver_identity" id="approver_identity"
                                        value="{{ old('approver_identity') }}"
                                        placeholder="Contoh: NRP: 12345678"
                                        class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">
                                    @error('approver_identity')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </details>

                        <details class="group rounded-[28px] border border-slate-200 bg-white p-5 shadow-[0_20px_45px_-30px_rgba(15,23,42,0.18)]" @if($documentationSectionOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Dokumentasi & Catatan</div>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">Lampiran pemusnahan</h3>
                                <p class="mt-1 text-sm text-slate-500">Unggah dokumentasi visual dan tambahkan catatan operasional bila diperlukan.</p>
                                </div>
                                <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-slate-500 transition group-open:rotate-180">⌄</span>
                            </summary>

                            <div class="mt-5 space-y-5 border-t border-slate-100 pt-5">
                                <div class="space-y-2">
                                    <label for="documentation_photos" class="block text-sm font-medium text-slate-700">Foto Dokumentasi</label>
                                    <input type="file" name="documentation_photos[]" id="documentation_photos" multiple accept="image/*"
                                        class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700 shadow-sm transition file:mr-4 file:rounded-full file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800 focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400/30">
                                    <p class="text-xs leading-6 text-slate-500">Maksimal 5 foto, format JPG/JPEG/PNG/WEBP, ukuran maksimum 5 MB per file.</p>
                                    @error('documentation_photos')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error('documentation_photos.*')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="space-y-2">
                                    <label for="notes" class="block text-sm font-medium text-slate-700">Catatan</label>
                                    <textarea name="notes" id="notes" rows="5"
                                        placeholder="Catatan tambahan tentang proses pemusnahan, kondisi pelaksanaan, atau hal penting lain..."
                                        class="w-full rounded-2xl border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-rose-400 focus:ring-rose-400">{{ old('notes') }}</textarea>
                                    @error('notes')
                                        <p class="text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </details>

                        <details class="group rounded-[28px] border border-rose-200 bg-rose-50/70 p-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]" @if($finalSectionOpen) open @endif>
                            <summary class="flex cursor-pointer list-none items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Sebelum eksekusi</h3>
                                    <p class="mt-1 text-sm text-slate-500">Checklist final dan tombol submit batch disposal.</p>
                                </div>
                                <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-full border border-rose-200 bg-white text-slate-500 transition group-open:rotate-180">⌄</span>
                            </summary>

                            <div class="mt-4 border-t border-rose-100 pt-4">
                                <div class="space-y-2 text-sm leading-7 text-slate-600">
                                    <p>Verifikasi jumlah sampel, metode pemusnahan, dan identitas penandatangan.</p>
                                    <p>Pastikan minimal satu saksi valid sudah diisi dan dokumentasi siap diunggah bila diperlukan.</p>
                                    <p>Gunakan tombol batal bila masih perlu revisi pemilihan batch dari halaman daftar disposal.</p>
                                </div>

                                <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                                    <a href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}"
                                        class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 active:translate-y-[1px]">
                                        Batal
                                    </a>
                                    <button type="submit"
                                        class="inline-flex items-center justify-center rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-[0_16px_35px_-20px_rgba(225,29,72,0.8)] transition hover:bg-rose-700 active:translate-y-[1px]"
                                        onclick="return confirm('Apakah Anda yakin ingin memusnahkan {{ $selectedSamples->count() }} sampel? Tindakan ini tidak dapat dibatalkan.')">
                                        Eksekusi Pemusnahan
                                    </button>
                                </div>
                            </div>
                        </details>
                    </div>
                </section>
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
