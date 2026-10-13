<x-app-layout>
    <x-slot name="header"><x-page-header class="inventory-page-header" title="Pemusnahan Sampel" :breadcrumbs="[['label' => 'Inventori', 'href' => route('inventory.dashboard')], ['label' => 'Pemusnahan Sampel']]" /></x-slot>
    <div class="inventory-workflow space-y-6" x-data="sampleDisposalSelection()">
        @if(session('success'))<p role="status" class="inventory-status-success rounded-md p-4">{{ session('success') }}</p>@endif
        @if(session('error'))<p role="alert" class="inventory-status-danger rounded-md p-4">{{ session('error') }}</p>@endif
        <nav aria-label="Tab pemusnahan" class="flex flex-wrap gap-3" @click.capture="carrySelection($event)">
            <a class="btn {{ $tab === 'eligible' ? 'btn-primary' : 'border-sem semantic-text' }}" href="{{ route('inventory.disposal.index', ['tab' => 'eligible']) }}" @if($tab === 'eligible') aria-current="page" @endif>Sampel siap ditinjau</a>
            <a class="btn {{ $tab === 'history' ? 'btn-primary' : 'border-sem semantic-text' }}" href="{{ route('inventory.disposal.index', ['tab' => 'history']) }}" @if($tab === 'history') aria-current="page" @endif>Riwayat pemusnahan</a>
        </nav>
        @if($tab === 'eligible')
            <section class="card space-y-3">
                <h2 class="font-semibold semantic-text">{{ $eligibleOverview['total'] }} sampel memenuhi syarat pemusnahan</h2>
                <p class="text-sm semantic-text-muted">Sampel baru dapat dimusnahkan setelah disimpan minimal {{ $retentionDays }} hari sejak hasil interpretasi terakhir yang memiliki nomor laporan hasil uji (LHU). Tanggal acuan dan umur sampel dihitung dari hasil tersebut. Aturan ini hanya berlaku untuk sampel, bukan stok inventori.</p>
                @if(auth()->user()->hasPermission('sample-disposal.execute'))
                    <div class="flex flex-wrap gap-3">
                        <a class="btn btn-primary" href="{{ route('inventory.disposal.create', ['all' => 1]) }}">Tinjau semua sampel</a>
                        @foreach($monthlySummaries as $month)
                            <a class="btn border-sem semantic-text" href="{{ route('inventory.disposal.create', ['month' => $month['key']]) }}">{{ $month['label'] }} ({{ $month['count'] }})</a>
                        @endforeach
                    </div>
                    <form method="GET" action="{{ route('inventory.disposal.create') }}" class="flex flex-wrap items-center gap-3">
                        <template x-for="id in selected" :key="id"><input type="hidden" name="sample_ids[]" :value="id"></template>
                        <button type="submit" :disabled="selected.length === 0" class="btn btn-primary">Tinjau pilihan (<span x-text="selected.length"></span>)</button>
                        <button type="button" @click="clear()" class="btn border-sem semantic-text">Hapus pilihan</button>
                    </form>
                @endif
            </section>
            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm semantic-text">
                    <caption class="sr-only">Daftar sampel yang memenuhi syarat pemusnahan, tanggal acuan, dan umur</caption>
                    <thead><tr><th scope="col" class="p-3 text-left">Pilih</th><th scope="col" class="p-3 text-left">Sampel / permintaan</th><th scope="col" class="p-3 text-left">Nomor LHU</th><th scope="col" class="p-3 text-left">Tanggal acuan</th><th scope="col" class="p-3 text-right">Umur</th></tr></thead>
                    <tbody>
                        @forelse($eligibleSamples as $sample)
                            @php($projection = $projections[$sample->id])
                            <tr class="border-t semantic-border">
                                <td class="p-3">@if(auth()->user()->hasPermission('sample-disposal.execute'))<label class="inline-flex min-h-11 min-w-11 items-center justify-center"><input type="checkbox" :checked="selected.includes(@js((string) $sample->id))" @change="toggle(@js((string) $sample->id), $event.target.checked)" aria-label="Pilih {{ $sample->sample_code }}"></label>@else<span class="semantic-text-muted">Lihat saja</span>@endif</td>
                                <td class="p-3">{{ $projection['sample_code'] }}<span class="block semantic-text-muted">{{ $projection['request_number'] }} · {{ $projection['suspect_name'] }}</span></td>
                                <td class="p-3">{{ $projection['lhu_number'] }}</td>
                                <td class="p-3">{{ \Carbon\Carbon::parse($projection['completed_at'])->format('d M Y') }}</td>
                                <td class="p-3 text-right">{{ $projection['age_days'] }} hari</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" role="status" class="p-6 text-center">Belum ada sampel yang memenuhi syarat pemusnahan. Sampel harus memiliki hasil interpretasi dengan nomor LHU, sudah melewati masa simpan, dan belum dimusnahkan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div @click.capture="carrySelection($event)">{{ $eligibleSamples->links() }}</div>
        @else
            <div class="card overflow-x-auto">
                <table class="min-w-full text-sm semantic-text"><caption class="sr-only">Riwayat batch pemusnahan</caption><thead><tr><th scope="col" class="p-3 text-left">Batch</th><th scope="col" class="p-3 text-left">Tanggal</th><th scope="col" class="p-3 text-left">Metode</th><th scope="col" class="p-3 text-right">Sampel</th></tr></thead><tbody>
                @forelse($disposals as $disposal)<tr class="border-t semantic-border"><td class="p-3"><a class="inline-flex min-h-11 items-center underline" href="{{ route('inventory.disposal.show', $disposal) }}">{{ $disposal->batch_number }}</a></td><td class="p-3">{{ $disposal->recorded_executed_at->format('d M Y H:i') }}</td><td class="p-3">{{ $disposal->method_label }}</td><td class="p-3 text-right">{{ $disposal->sample_count }}</td></tr>
                @empty<tr><td colspan="4" role="status" class="p-6 text-center">Belum ada riwayat pemusnahan.</td></tr>@endforelse
                </tbody></table>
            </div>
            {{ $disposals->links() }}
        @endif
    </div>
</x-app-layout>
