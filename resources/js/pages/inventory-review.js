export function inventoryTransactionReview(config) {
    return {
        item: 'Belum dipilih',
        lot: 'Belum dipilih',
        source: 'Belum dipilih',
        destination: 'Belum dipilih',
        quantity: '-',
        reference: '-',
        notes: '-',
        effect: 'Lengkapi pilihan dan jumlah untuk melihat efek saldo.',
        ready: false,
        submitting: false,
        form: null,
        observer: null,
        refresh: null,
        onSubmit: null,
        init() {
            this.form = this.$el.closest('form');
            this.form.querySelectorAll('input, select, textarea').forEach((input) => {
                const label = input.previousElementSibling;
                if (label?.tagName === 'LABEL' && !label.htmlFor) {
                    if (!input.id) {
                        input.id = `${config.operation}-${input.name || 'field'}`;
                    }
                    label.htmlFor = input.id;
                }
            });
            this.refresh = () => this.update();
            this.onSubmit = (event) => {
                if (!event.defaultPrevented) {
                    this.submitting = true;
                    this.update();
                }
            };
            this.form.addEventListener('input', this.refresh);
            this.form.addEventListener('change', this.refresh);
            this.form.addEventListener('submit', this.onSubmit);
            this.observer = new MutationObserver((records) => {
                if (records.some((record) => !this.$el.contains(record.target))) {
                    this.update();
                }
            });
            this.observer.observe(this.form, { childList: true, subtree: true, characterData: true });
            this.update();
        },
        update() {
            const field = (name) => this.form.querySelector(`[name="${name}"]`);
            const value = (name) => field(name)?.value || '';
            const selected = (name) => field(name)?.selectedOptions?.[0]?.textContent.trim() || 'Belum dipilih';
            const mode = this.form.querySelector('[name="lot_mode"]:checked')?.value || '';
            const unit = field('item_id')?.selectedOptions?.[0]?.dataset.uom || '';
            const amount = Number(value('qty'));
            const policy = field('item_id')?.selectedOptions?.[0]?.dataset.lotPolicy;
            const validMode = policy === 'untracked' ? mode === 'untracked'
                : policy === 'lot_tracked' && (mode === 'existing' || (config.operation === 'receipt' && mode === 'new'));
            const validLot = mode === 'untracked' || (mode === 'existing' && Boolean(value('lot_id')))
                || (mode === 'new' && Boolean(value('new_lot_no').trim()));
            this.item = value('item_id') ? selected('item_id') : 'Belum dipilih';
            this.lot = mode === 'untracked' ? 'Tanpa lot (untracked)' : mode === 'new' ? value('new_lot_no') || 'Nomor lot belum diisi' : value('lot_id') ? selected('lot_id') : 'Belum dipilih';
            this.source = value('from_location_id') ? selected('from_location_id') : selected('location_id');
            this.destination = selected('to_location_id');
            this.quantity = amount > 0 ? `${amount.toLocaleString('id-ID', { minimumFractionDigits: 3, maximumFractionDigits: 3 })} ${unit}` : '-';
            this.reference = selected('reference_type');
            this.notes = value('notes').trim() || 'Tidak ada catatan tambahan';
            const locationReady = config.operation === 'transfer'
                ? Boolean(value('from_location_id') && value('to_location_id') && value('from_location_id') !== value('to_location_id'))
                : Boolean(value('location_id'));
            const available = value('expected_available_qty');
            const balanceReady = config.operation === 'receipt' || Boolean(value('expected_on_hand_qty') && available && amount <= Number(available));
            const destinationReady = config.operation !== 'transfer' || Boolean(this.form.querySelector('#destination-on-hand')?.textContent.match(/\d/));
            const disposalReady = config.operation !== 'dispose' || Boolean(value('reason_code') && field('review_confirmed')?.checked);
            this.ready = Boolean(config.allowed && value('item_id') && validMode && validLot && locationReady && balanceReady && destinationReady && disposalReady && amount >= 0.001 && amount <= 99999999999.999 && Math.abs(amount * 1000 - Math.round(amount * 1000)) < 0.000001);
            this.effect = this.ready ? config.operation === 'receipt' ? `Saldo lokasi bertambah ${this.quantity}.`
                : config.operation === 'transfer' ? `Saldo asal berkurang ${this.quantity}, saldo tujuan bertambah sama besar. Total inventori tetap.`
                    : `Saldo lokasi berkurang ${this.quantity}.` : 'Pilihan, jumlah, atau konteks saldo belum siap. Lengkapi dan muat konteks terkini.';
            const submit = this.form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = !this.ready || this.submitting;
            }
        },
        destroy() {
            this.observer?.disconnect();
            this.form?.removeEventListener('input', this.refresh);
            this.form?.removeEventListener('change', this.refresh);
            this.form?.removeEventListener('submit', this.onSubmit);
        },
    };
}
