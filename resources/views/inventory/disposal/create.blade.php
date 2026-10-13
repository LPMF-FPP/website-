<x-app-layout>
    <x-slot name="header"><x-page-header class="inventory-page-header" title="Tinjau Pemusnahan Sampel" :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemusnahan Sampel', 'href' => route('inventory.disposal.index')], ['label' => 'Tinjau batch']]" /></x-slot>
    <div class="inventory-workflow space-y-6">
        @if(session('error'))<p role="alert" class="inventory-status-danger rounded-md p-4">{{ session('error') }}</p>@endif
        @if($errors->any())<div role="alert" tabindex="-1" x-data="{}" x-init="$nextTick(() => $el.focus())" class="inventory-status-danger rounded-md p-4"><p class="font-semibold">Pemusnahan belum dieksekusi. Periksa dan tinjau ulang:</p><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><a class="inline-flex min-h-11 items-center underline" href="{{ route('inventory.disposal.index') }}">Segarkan pemilihan batch</a></div>@endif
        <section class="card space-y-3">
            <h2 class="font-semibold semantic-text">1. Manifest {{ count($manifest['samples']) }} sampel @if($selectedMonthLabel) (Mode Bulanan: {{ $selectedMonthLabel }}) @endif</h2>
            <p class="text-sm semantic-text-muted">Retensi {{ $manifest['retention_days'] }} hari, acuan interpretasi yang memenuhi LHU dan retensi. Zona waktu {{ $manifest['timezone'] }}. Pilihan ini diperiksa ulang saat eksekusi; pemusnahan tidak dapat dibatalkan.</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm semantic-text"><caption class="sr-only">Manifest lengkap sampel yang akan dimusnahkan</caption><thead><tr><th scope="col" class="p-3 text-left">Sampel / keterangan</th><th scope="col" class="p-3 text-left">Permintaan / tersangka</th><th scope="col" class="p-3 text-left">LHU</th><th scope="col" class="p-3 text-left">Acuan / umur</th></tr></thead><tbody>
                    @foreach($manifest['samples'] as $row)<tr class="border-t semantic-border"><td class="p-3">{{ $row['sample_code'] }} · {{ $row['description'] }}</td><td class="p-3">{{ $row['request_number'] }} · {{ $row['suspect_name'] }}<span class="block">Perkara: {{ $row['case_number'] ?: 'Tidak tercatat' }} · {{ $row['case_date'] ? \Carbon\Carbon::parse($row['case_date'])->format('d M Y') : 'Tanggal tidak tercatat' }}</span></td><td class="p-3">{{ $row['lhu_number'] }}</td><td class="p-3">{{ \Carbon\Carbon::parse($row['completed_at'])->format('d M Y') }} · {{ $row['age_days'] }} hari</td></tr>@endforeach
                </tbody></table>
            </div>
        </section>
        @php
            $usersForForm = $witnessUsers->map(fn ($user) => ['id' => (string) $user->id, 'name' => $user->display_name_with_title ?: $user->name, 'role' => $user->rank])->all();
        @endphp
        <form id="sample-disposal-form" method="POST" enctype="multipart/form-data" action="{{ route('inventory.disposal.store') }}" class="space-y-6" x-data="sampleDisposalExecution({ rows: @js($witnessRows), users: @js($usersForForm) })" @input="invalidate($event)" @change="invalidate($event)" @submit="submit($event)" @pageshow.window="restore($event)">
            @csrf
            <input type="hidden" name="review_token" value="{{ $reviewToken }}">
            <input type="hidden" name="sample_ids_json" value="{{ json_encode(array_column($manifest['samples'], 'sample_id')) }}">
            <section class="card space-y-4">
                <h2 class="font-semibold semantic-text">2. Bukti pelaksanaan</h2>
                <label for="method" class="block font-medium">Metode Pemusnahan (wajib)</label><select id="method" name="method" required class="min-h-11 w-full rounded-md"><option value="">Pilih metode</option>@foreach($methods as $key => $label)<option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>@endforeach</select>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="executor_name" class="block font-medium">Nama pelaksana</label><input id="executor_name" name="executor_name" maxlength="255" value="{{ old('executor_name', auth()->user()->display_name_with_title ?: auth()->user()->name) }}" class="mt-1 min-h-11 w-full rounded-md"></div>
                    <div><label for="executor_role" class="block font-medium">Pangkat / jabatan pelaksana</label><input id="executor_role" name="executor_role" maxlength="255" value="{{ old('executor_role', auth()->user()->rank) }}" class="mt-1 min-h-11 w-full rounded-md"></div>
                    <div><label for="executor_identity" class="block font-medium">Identitas pelaksana</label><input id="executor_identity" name="executor_identity" maxlength="255" value="{{ old('executor_identity') }}" class="mt-1 min-h-11 w-full rounded-md" placeholder="Opsional, default profil akun"></div>
                </div>
                <fieldset class="space-y-4"><legend class="font-semibold semantic-text">Daftar Saksi (minimal satu)</legend>
                    <template x-for="(row, index) in rows" :key="row.key">
                        <div class="grid gap-3 rounded-md border semantic-border p-3 sm:grid-cols-2">
                            <div><label :for="'witness-user-'+row.key" class="block font-medium">Pilih akun saksi atau isi manual</label><select :id="'witness-user-'+row.key" :name="`witnesses[${index}][user_id]`" x-model="row.user_id" @change="selectWitness(index)" class="mt-1 min-h-11 w-full rounded-md"><option value="">Saksi manual</option>@foreach($witnessUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->rank ?: 'Isi jabatan manual' }})</option>@endforeach</select></div>
                            <div><label :for="'witness-name-'+row.key" class="block font-medium">Nama saksi</label><input :id="'witness-name-'+row.key" :name="`witnesses[${index}][name]`" x-model="row.name" maxlength="255" class="mt-1 min-h-11 w-full rounded-md"></div>
                            <div><label :for="'witness-role-'+row.key" class="block font-medium">Jabatan saksi</label><input :id="'witness-role-'+row.key" :name="`witnesses[${index}][role]`" x-model="row.role" maxlength="255" class="mt-1 min-h-11 w-full rounded-md"></div>
                            <div><label :for="'witness-identity-'+row.key" class="block font-medium">Identitas saksi (opsional)</label><input :id="'witness-identity-'+row.key" :name="`witnesses[${index}][identity]`" x-model="row.identity" maxlength="255" class="mt-1 min-h-11 w-full rounded-md"></div>
                            <button type="button" @click="removeWitness(index)" :disabled="rows.length === 1" class="btn border-sem semantic-text">Hapus saksi</button>
                        </div>
                    </template>
                    <button type="button" @click="addWitness()" class="btn border-sem semantic-text">Tambah saksi</button>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div><label for="approver_name" class="block font-medium">Nama approver (wajib)</label><input id="approver_name" name="approver_name" required maxlength="255" value="{{ old('approver_name') }}" class="mt-1 min-h-11 w-full rounded-md"></div>
                    <div><label for="approver_role" class="block font-medium">Pangkat / jabatan approver (wajib)</label><input id="approver_role" name="approver_role" required maxlength="255" value="{{ old('approver_role') }}" class="mt-1 min-h-11 w-full rounded-md"></div>
                    <div><label for="approver_identity" class="block font-medium">Identitas approver (opsional)</label><input id="approver_identity" name="approver_identity" maxlength="255" value="{{ old('approver_identity') }}" class="mt-1 min-h-11 w-full rounded-md"></div>
                </div>
                <div><label for="documentation_photos" class="block font-medium">Foto dokumentasi (opsional)</label><input id="documentation_photos" name="documentation_photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" class="mt-1 min-h-11 w-full rounded-md border p-2"><p class="mt-1 text-sm semantic-text-muted">Maksimal 5 foto JPG/JPEG/PNG/WEBP, 5 MB per foto. Foto baru disimpan privat dan diakses melalui izin pemusnahan sampel.</p></div>
                <div><label for="notes" class="block font-medium">Catatan (opsional)</label><textarea id="notes" name="notes" maxlength="1000" rows="3" class="mt-1 w-full rounded-md">{{ old('notes') }}</textarea></div>
            </section>
            <section class="card space-y-4">
                <h2 class="font-semibold semantic-text">3. Konfirmasi eksekusi</h2>
                <p class="inventory-status-warning rounded-md p-3">{{ count($manifest['samples']) }} sampel akan dimusnahkan dan dicatat sebagai satu batch. Stok inventori tidak berubah. Perubahan manifest membatalkan eksekusi dan memerlukan review baru.</p>
                <label class="flex min-h-11 items-start gap-3"><input type="checkbox" name="review_confirmed" value="1" x-model="confirmed" required class="mt-1"><span>Saya sudah meninjau seluruh manifest, metode, pelaksana, saksi, approver dan dokumentasi. Saya memahami pemusnahan tidak dapat dibatalkan.</span></label>
                <div class="flex flex-wrap gap-3"><a href="{{ route('inventory.disposal.index', ['selected' => implode(',', array_column($manifest['samples'], 'sample_id'))]) }}" class="btn border-sem semantic-text">Batal dan pilih ulang</a><button id="execute-sample-disposal" type="submit" :disabled="pending || !confirmed" class="btn btn-primary"><span x-text="pending ? 'Memeriksa dan mengeksekusi…' : 'Eksekusi Pemusnahan'"></span></button></div>
                <p x-cloak x-show="pending" role="status" class="semantic-text-muted">Jangan kirim ulang; server sedang memeriksa manifest dan mencatat hasil.</p>
            </section>
        </form>
    </div>
</x-app-layout>
