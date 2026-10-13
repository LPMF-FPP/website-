<section class="card space-y-4" aria-labelledby="sample-disposal-summary-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 id="sample-disposal-summary-title" class="font-semibold semantic-text">Pemusnahan Sampel</h3>
        <a href="{{ route('inventory.disposal.index') }}" class="inline-flex min-h-11 items-center font-semibold text-primary-800 underline dark:text-primary-300">Kelola pemusnahan</a>
    </div>
    <p class="text-sm semantic-text-muted">Retensi {{ \App\Models\Sample::disposalRetentionDays() }} hari dari interpretasi yang memenuhi syarat LHU dan retensi. Workflow ini tidak mengubah stok inventori.</p>
    <dl class="grid gap-4 sm:grid-cols-3 semantic-text">
        <div><dt class="text-sm semantic-text-muted">Selesai uji tercatat</dt><dd data-testid="disposal-finished-count" class="mt-1 text-2xl font-semibold">{{ $finishedSamplesCount }}</dd></div>
        <div><dt class="text-sm semantic-text-muted">Sampel siap ditinjau</dt><dd data-testid="disposal-eligible-count" class="mt-1 text-2xl font-semibold">{{ $eligibleSamplesCount }}</dd></div>
        <div><dt class="text-sm semantic-text-muted">Dimusnahkan bulan ini</dt><dd data-testid="disposal-disposed-month-count" class="mt-1 text-2xl font-semibold">{{ $disposedThisMonthCount }}</dd></div>
    </dl>
    @if($recentSampleDisposals->isNotEmpty())
        <ul class="flex flex-wrap gap-3">
            @foreach($recentSampleDisposals as $disposal)<li><a href="{{ route('inventory.disposal.show', $disposal) }}" class="inline-flex min-h-11 items-center font-mono semantic-text underline">{{ $disposal->batch_number }} ({{ $disposal->sample_count }} sampel)</a></li>@endforeach
        </ul>
    @endif
</section>
