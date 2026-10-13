<x-app-layout>
    <x-slot name="header"><x-page-header class="inventory-page-header" :title="'Pemusnahan '.$disposal->batch_number" :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemusnahan Sampel', 'href' => route('inventory.disposal.index', ['tab' => 'history'])], ['label' => 'Hasil batch']]" /></x-slot>
    <div class="inventory-workflow space-y-6">
        @if(session('success'))<p role="status" class="inventory-status-success rounded-md p-4">{{ session('success') }}</p>@endif
        <section class="card space-y-4">
            <h2 class="font-semibold semantic-text">Batch selesai: {{ count($disposal->manifest_rows) }} sampel</h2>
            <dl class="grid gap-4 sm:grid-cols-2 semantic-text">
                <div><dt class="font-semibold">Tanggal eksekusi</dt><dd>{{ $disposal->recorded_executed_at->format('d M Y H:i') }} {{ $disposal->manifest_snapshot['timezone'] ?? config('app.timezone') }}</dd></div>
                <div><dt class="font-semibold">Metode</dt><dd>{{ $disposal->method_label }}</dd></div>
                <div><dt class="font-semibold">Pelaksana</dt><dd>{{ $disposal->executed_by_name ?: $disposal->executedBy?->display_name_with_title }} · {{ $disposal->executed_by_role }} · {{ $disposal->executed_by_identity }}</dd></div>
                <div><dt class="font-semibold">Approver / Kepala Farmapol</dt><dd>{{ $disposal->approver_name ?: 'Tidak tercatat (legacy)' }}<span class="block">{{ $disposal->approver_role }} {{ $disposal->approver_identity }}</span></dd></div>
                <div><dt class="font-semibold">Saksi</dt><dd>@foreach($disposal->witness_entries_for_display as $witness)<p>{{ $witness['name'] }} · {{ $witness['role'] }} · {{ $witness['identity'] }}</p>@endforeach</dd></div>
                <div><dt class="font-semibold">Catatan</dt><dd class="break-words">{{ $disposal->notes ?: 'Tidak ada catatan tambahan' }}</dd></div>
            </dl>
            @if($disposal->manifest_snapshot)<p class="text-sm semantic-text-muted">Snapshot manifest saat review, retensi {{ $disposal->manifest_snapshot['retention_days'] }} hari. Acuan dan identitas berikut tidak dihitung ulang dari proses sampel saat ini.</p>@else<p class="text-sm semantic-text-muted">Batch legacy: daftar ditampilkan dari relasi historis yang tersedia.</p>@endif
            <a href="{{ route('inventory.disposal.pdf', $disposal) }}" class="btn btn-primary">Unduh berita acara PDF</a>
        </section>
        <div class="card overflow-x-auto">
            <table class="min-w-full text-sm semantic-text"><caption class="sr-only">Manifest sampel yang dimusnahkan</caption><thead><tr><th scope="col" class="p-3 text-left">Sampel</th><th scope="col" class="p-3 text-left">Permintaan / tersangka</th><th scope="col" class="p-3 text-left">LHU</th><th scope="col" class="p-3 text-left">Tanggal acuan</th></tr></thead><tbody>
                @foreach($disposal->manifest_rows as $row)<tr class="border-t semantic-border"><td class="p-3">{{ $row['sample_code'] }} · {{ $row['description'] }}</td><td class="p-3">{{ $row['request_number'] }} · {{ $row['suspect_name'] }}<span class="block">Perkara: {{ $row['case_number'] ?: 'Tidak tercatat' }} · {{ $row['case_date'] ? \Carbon\Carbon::parse($row['case_date'])->format('d M Y') : 'Tanggal tidak tercatat' }}</span></td><td class="p-3">{{ $row['lhu_number'] }}</td><td class="p-3">{{ $row['completed_at'] ? \Carbon\Carbon::parse($row['completed_at'])->format('d M Y') : 'Tidak tercatat' }}</td></tr>@endforeach
            </tbody></table>
        </div>
        <section class="card"><h2 class="font-semibold semantic-text">Dokumentasi</h2><div class="mt-4 grid gap-4 sm:grid-cols-2">
            @forelse($disposal->documentation_photos_for_display as $photo)
                @if($photo['exists'])<a href="{{ $photo['url'] }}" class="semantic-text underline"><img src="{{ $photo['url'] }}" alt="Dokumentasi {{ $disposal->batch_number }}: {{ $photo['original_name'] }}" class="max-w-full rounded-md">{{ $photo['original_name'] }}</a>@else<p class="semantic-text-muted">Dokumentasi {{ $photo['original_name'] }} tidak tersedia.</p>@endif
            @empty<p role="status" class="semantic-text-muted">Tidak ada foto dokumentasi; lampiran foto bersifat opsional.</p>@endforelse
        </div></section>
    </div>
</x-app-layout>
