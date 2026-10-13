<section aria-labelledby="transaction-review-title" x-data="inventoryTransactionReview({ operation: @js($operation), allowed: @js(($mode ?? 'disabled') === \App\Models\InventoryMutationMode::ACTIVE) })" class="mt-6 rounded-lg border semantic-border semantic-surface p-4">
    <h2 id="transaction-review-title" class="font-semibold semantic-text">3. Tinjau transaksi</h2>
    <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm semantic-text break-words">
        <div><dt class="font-semibold">Item</dt><dd x-text="item"></dd></div>
        <div><dt class="font-semibold">Lot</dt><dd x-text="lot"></dd></div>
        <div><dt class="font-semibold">{{ $operation === 'receipt' ? 'Lokasi penerimaan' : 'Lokasi asal' }}</dt><dd x-text="source"></dd></div>
        @if($operation === 'transfer')<div><dt class="font-semibold">Lokasi tujuan</dt><dd x-text="destination"></dd></div>@endif
        <div><dt class="font-semibold">Jumlah dan satuan</dt><dd x-text="quantity"></dd></div>
        <div><dt class="font-semibold">Referensi</dt><dd x-text="reference"></dd></div>
        <div class="sm:col-span-2"><dt class="font-semibold">Catatan / alasan</dt><dd class="break-words" x-text="notes"></dd></div>
    </dl>
    <p role="status" aria-live="polite" class="mt-3 text-sm semantic-text-muted" x-text="effect"></p>
    <p class="mt-2 text-xs semantic-text-muted">Saldo hanya berubah setelah server mengonfirmasi transaksi. Periksa identitas, lokasi, dan jumlah sebelum menyimpan.</p>
</section>
