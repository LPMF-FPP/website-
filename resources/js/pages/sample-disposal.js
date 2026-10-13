export function sampleDisposalSelection() {
    return {
        selected: [],
        init() {
            this.selected = (new URL(window.location.href).searchParams.get('selected') || '').split(',').filter((id) => /^[1-9]\d*$/.test(id));
        },
        toggle(id, checked) {
            this.selected = checked ? [...new Set([...this.selected, id])] : this.selected.filter((value) => value !== id);
            this.persist();
        },
        clear() {
            this.selected = [];
            this.persist();
        },
        persist() {
            const url = new URL(window.location.href);
            if (this.selected.length) {
                url.searchParams.set('selected', this.selected.join(','));
            } else {
                url.searchParams.delete('selected');
            }
            window.history.replaceState({}, '', url);
        },
        carrySelection(event) {
            const link = event.target.closest('a[href]');
            if (link) {
                const url = new URL(link.href);
                if (this.selected.length) {
                    url.searchParams.set('selected', this.selected.join(','));
                } else {
                    url.searchParams.delete('selected');
                }
                link.href = url.toString();
            }
        },
    };
}

export function sampleDisposalExecution(config) {
    return {
        rows: [], confirmed: false, pending: false, nextKey: 0,
        init() {
            this.rows = config.rows.map((row) => ({ ...row, key: ++this.nextKey }));
        },
        restore(event) {
            if (event.persisted) {
                this.pending = false;
                this.confirmed = false;
            }
        },
        invalidate(event) {
            if (event.target.name !== 'review_confirmed') {
                this.confirmed = false;
            }
        },
        addWitness() {
            this.rows.push({ key: ++this.nextKey, user_id: '', name: '', role: '', identity: '' });
            this.confirmed = false;
        },
        removeWitness(index) {
            if (this.rows.length > 1) {
                this.rows.splice(index, 1);
                this.confirmed = false;
            }
        },
        selectWitness(index) {
            const user = config.users.find((entry) => entry.id === this.rows[index].user_id);
            if (user) {
                this.rows[index].name = user.name;
                this.rows[index].role = user.role || '';
            } else {
                this.rows[index].name = '';
                this.rows[index].role = '';
            }
            this.rows[index].identity = '';
            this.confirmed = false;
        },
        submit(event) {
            if (this.pending || !this.confirmed) {
                event.preventDefault();
                return;
            }
            this.pending = true;
        },
    };
}
