export function magicToolbar(config) {
    let controller = null;
    let sequence = 0;
    let snapshot = null;
    return {
        aiOpen: false, aiLoading: false, aiApplying: false, aiPrompt: '', aiResult: '', aiError: '', aiRequestId: '',
        shareDraft: false,
        destination: config.destination,
        context() { return config.contextType?.() || 'general'; },
        preservesContextTokens(draft, result) {
            return (config.preserveTokens || []).every(token => result.split(`{${token}}`).length - 1 >= draft.split(`{${token}}`).length - 1);
        },
        identity() { return JSON.stringify([config.recordIdentity?.(), this.context(), config.editorSession?.()]); },
        getTextarea() { return document.getElementById(config.textareaId); },
        getModelValue() { return config.target.split('.').reduce((value, key) => value?.[key], this.$data) || ''; },
        setModelValue(value) {
            const textarea = this.getTextarea();
            if (textarea) { textarea.value = value; textarea.dispatchEvent(new Event('input', { bubbles: true })); }
        },
        init() {
            this.$watch(() => this.identity(), () => this.closeAiModal());
            this.$watch(config.target, () => {
                if (snapshot && this.getModelValue() !== snapshot.draft) { this.invalidate('Draf telah berubah. Buat respons baru untuk draf ini.'); }
            });
            this.$watch('aiPrompt', () => {
                if (snapshot && this.aiPrompt !== snapshot.prompt) { this.invalidate('Instruksi berubah. Buat respons baru untuk instruksi ini.'); }
            });
            this.$watch('shareDraft', () => {
                if (snapshot && this.shareDraft !== snapshot.consent) { this.invalidate('Persetujuan berbagi draf berubah. Buat respons baru untuk pilihan ini.'); }
            });
            this.onAiConfigurationUpdated = (event) => {
                if (typeof event.detail?.ai_revision !== 'string') { return; }
                config.revision = event.detail.ai_revision;
                config.destination = event.detail.ai_base_url || '';
                this.destination = config.destination;
                if (snapshot || this.aiLoading) { this.invalidate('Konfigurasi AI berubah. Buat draf baru untuk tujuan terbaru.'); }
            };
            this.onAiConfigurationStorage = (event) => {
                if (event.key !== 'lpmf.whatsapp.ai.configuration' || typeof event.newValue !== 'string') { return; }
                try {
                    const update = JSON.parse(event.newValue);
                    if (typeof update.revision !== 'string') { return; }
                    config.revision = update.revision;
                    config.destination = update.destination || '';
                    this.destination = config.destination;
                    this.invalidate('Konfigurasi AI berubah di tab lain. Hasil lama dibatalkan.');
                } catch { return; }
            };
            window.addEventListener('whatsapp-ai-configuration-updated', this.onAiConfigurationUpdated);
            window.addEventListener('storage', this.onAiConfigurationStorage);
        },
        destroy() {
            controller?.abort();
            if (this.onAiConfigurationUpdated) { window.removeEventListener('whatsapp-ai-configuration-updated', this.onAiConfigurationUpdated); }
            if (this.onAiConfigurationStorage) { window.removeEventListener('storage', this.onAiConfigurationStorage); }
        },
        invalidate(message = '') {
            sequence += 1; controller?.abort(); controller = null; snapshot = null;
            this.aiLoading = false; this.aiApplying = false; this.aiResult = ''; this.aiError = message; this.aiRequestId = '';
        },
        openAiModal() {
            this.invalidate(); this.shareDraft = false; this.aiOpen = true;
        },
        closeAiModal() { this.invalidate(); this.aiOpen = false; },
        insertVariable(variable) { this.replaceSelection(`{${variable}}`); },
        formatText(format) {
            const textarea = this.getTextarea();
            if (!textarea || textarea.selectionStart === textarea.selectionEnd) { return; }
            const wrappers = { bold: '*', italic: '_', strike: '~', mono: '```' };
            const wrapper = wrappers[format];
            if (!wrapper) { return; }
            const start = textarea.selectionStart;
            const formatted = wrapper + textarea.value.slice(start, textarea.selectionEnd) + wrapper;
            this.setModelValue(textarea.value.slice(0, start) + formatted + textarea.value.slice(textarea.selectionEnd));
            this.$nextTick(() => { textarea.focus(); textarea.setSelectionRange(start, start + formatted.length); });
        },
        replaceSelection(value) {
            const textarea = this.getTextarea();
            if (!textarea) { return; }
            const start = textarea.selectionStart;
            this.setModelValue(textarea.value.slice(0, start) + value + textarea.value.slice(textarea.selectionEnd));
            this.$nextTick(() => { textarea.focus(); textarea.setSelectionRange(start + value.length, start + value.length); });
        },
        async generateAi() {
            if (this.aiLoading || !this.aiPrompt.trim()) { return; }
            this.invalidate();
            const requestSequence = sequence;
            const requestSnapshot = { identity: this.identity(), draft: this.getModelValue(), prompt: this.aiPrompt, consent: this.shareDraft, revision: config.revision };
            snapshot = requestSnapshot;
            controller = new AbortController();
            this.aiLoading = true;
            this.$nextTick?.(() => this.$refs?.aiPrompt?.focus());
            try {
                const { data } = await window.axios.post(config.composeUrl, {
                    prompt: requestSnapshot.prompt, context_type: this.context(), share_draft: requestSnapshot.consent,
                    expected_revision: config.revision,
                    current_text: requestSnapshot.consent ? requestSnapshot.draft : null,
                }, { signal: controller.signal, timeout: 40000 });
                if (requestSequence !== sequence || !this.aiOpen) { return; }
                if (this.identity() !== requestSnapshot.identity || this.getModelValue() !== requestSnapshot.draft || this.aiPrompt !== requestSnapshot.prompt || this.shareDraft !== requestSnapshot.consent) {
                    this.invalidate('Editor atau instruksi berubah. Buat respons baru.'); return;
                }
                if (typeof data.result !== 'string' || !data.result.trim()) { throw new Error('empty'); }
                if (!this.preservesContextTokens(requestSnapshot.draft, data.result)) {
                    this.invalidate('Respons mengubah variabel template pada draf. Buat ulang dengan persetujuan berbagi draf atau pertahankan variabel secara manual.'); return;
                }
                this.aiResult = data.result;
            } catch (error) {
                if (requestSequence !== sequence || error.code === 'ERR_CANCELED') { return; }
                this.aiError = error.response?.data?.error || 'Respons AI gagal diterima. Periksa koneksi lalu coba kembali.';
                this.aiRequestId = error.response?.data?.request_id || '';
            } finally {
                if (requestSequence === sequence) { this.aiLoading = false; }
            }
        },
        async useAiResult() {
            if (!this.aiResult || !snapshot || this.aiLoading || this.aiApplying) { return; }
            if (this.identity() !== snapshot.identity || this.getModelValue() !== snapshot.draft || this.aiPrompt !== snapshot.prompt || this.shareDraft !== snapshot.consent || config.revision !== snapshot.revision) {
                this.invalidate('Draf, rekaman, atau instruksi berubah. Respons lama tidak dapat diterapkan.'); return;
            }
            if (!this.preservesContextTokens(snapshot.draft, this.aiResult)) {
                this.invalidate('Variabel template harus tetap dipertahankan. Buat ulang sebelum menerapkan.'); return;
            }
            if (!config.settingsUrl) {
                this.aiError = 'Konfigurasi AI tidak dapat diverifikasi. Muat ulang halaman sebelum menerapkan.'; return;
            }
            this.aiApplying = true;
            const applySequence = sequence;
            const applySnapshot = snapshot;
            try {
                const { data } = await window.axios.get(config.settingsUrl);
                if (applySequence !== sequence || !this.aiOpen) { return; }
                if (!data.ai_configured || data.ai_revision !== applySnapshot.revision) {
                    config.revision = data.ai_revision || '';
                    config.destination = data.ai_base_url || '';
                    this.destination = config.destination;
                    this.invalidate('Konfigurasi AI berubah. Buat pratinjau baru sebelum menerapkan.'); return;
                }
            } catch {
                if (applySequence === sequence) { this.aiError = 'Konfigurasi AI tidak dapat diverifikasi. Periksa koneksi lalu coba kembali.'; }
                return;
            } finally {
                if (applySequence === sequence) { this.aiApplying = false; }
            }
            if (this.identity() !== applySnapshot.identity || this.getModelValue() !== applySnapshot.draft || this.aiPrompt !== applySnapshot.prompt || this.shareDraft !== applySnapshot.consent || config.revision !== applySnapshot.revision) {
                this.invalidate('Draf, rekaman, atau konfigurasi berubah. Pratinjau lama tidak dapat diterapkan.'); return;
            }
            const result = this.aiResult;
            this.closeAiModal(); this.setModelValue(result); this.aiPrompt = '';
        },
    };
}

export function whatsappAiSettings(config) {
    let testController = null;
    let testSequence = 0;
    let saved = null;
    let committedProvider = 'openai';
    const fields = ['ai_provider', 'ai_base_url', 'ai_model', 'ai_api_key', 'ai_remove_key', 'ai_reset_overrides'];
    return {
        aiForm: { ai_provider: 'openai', ai_base_url: '', ai_model: '', ai_api_key: '', ai_api_key_configured: false, ai_configured: false, ai_revision: '', ai_remove_key: false, ai_reset_overrides: false, ai_confirm_destination: false },
        aiSaving: false, aiLoadingSettings: true, aiFeedback: '', sendingAiTest: false,
        aiTest: { prompt: 'Tulis salam singkat untuk uji koneksi, tanpa data perkara.', result: '', error: '' },
        fingerprint() { return JSON.stringify(fields.map(field => this.aiForm[field])); },
        get aiDirty() { return saved !== this.fingerprint(); },
        async init() {
            try {
                const { data } = await window.axios.get(config.settingsUrl);
                Object.assign(this.aiForm, data); saved = this.fingerprint(); committedProvider = this.aiForm.ai_provider;
            } catch { this.aiFeedback = 'Konfigurasi belum dapat dimuat. Muat ulang halaman sebelum menyimpan.'; }
            finally { this.aiLoadingSettings = false; }
            this.$watch(() => this.fingerprint(), () => this.cancelTest());
            this.$watch('aiTest.prompt', () => this.cancelTest());
        },
        destroy() { this.cancelTest(); },
        cancelTest() { testSequence += 1; testController?.abort(); this.sendingAiTest = false; this.aiTest.result = ''; this.aiTest.error = ''; },
        async saveAiSettings(action = '') {
            if (this.aiSaving || this.aiLoadingSettings || saved === null) { return; }
            if (action && !window.confirm(action === 'reset' ? 'Kembali ke default provider? URL dan model override akan dihapus; kunci tetap dipertahankan.' : 'Hapus kunci AI dan nonaktifkan fallback kunci server?')) { return; }
            this.cancelTest(); this.aiSaving = true; this.aiFeedback = '';
            const before = this.fingerprint();
            const submittedKey = this.aiForm.ai_api_key;
            const payload = { ...this.aiForm, expected_revision: this.aiForm.ai_revision, ai_reset_overrides: action === 'reset', ai_remove_key: action === 'remove' };
            if (action === 'reset') { payload.ai_base_url = null; payload.ai_model = null; payload.ai_api_key = null; }
            if (action === 'remove') { payload.ai_api_key = null; payload.ai_provider = committedProvider; }
            try {
                const { data } = await window.axios.post(config.saveUrl, payload);
                committedProvider = data.ai_provider || committedProvider;
                this.aiForm.ai_revision = data.ai_revision || this.aiForm.ai_revision;
                if (typeof window.dispatchEvent === 'function' && typeof window.CustomEvent === 'function') {
                    window.dispatchEvent(new window.CustomEvent('whatsapp-ai-configuration-updated', { detail: data }));
                }
                try {
                    window.localStorage?.setItem('lpmf.whatsapp.ai.configuration', JSON.stringify({ revision: data.ai_revision, destination: data.ai_base_url || '' }));
                } catch { /* Storage may be unavailable; same-tab synchronization still applies. */ }
                if (this.fingerprint() === before) {
                    const unsavedConfiguration = { ai_provider: this.aiForm.ai_provider, ai_base_url: this.aiForm.ai_base_url, ai_model: this.aiForm.ai_model };
                    Object.assign(this.aiForm, data, { ai_api_key: '', ai_confirm_destination: false, ai_remove_key: false, ai_reset_overrides: false });
                    saved = this.fingerprint();
                    if (action === 'reset' && submittedKey) { this.aiForm.ai_api_key = submittedKey; }
                    if (action === 'remove') { Object.assign(this.aiForm, unsavedConfiguration); }
                } else {
                    this.aiForm.ai_api_key_configured = data.ai_api_key_configured;
                    this.aiForm.ai_configured = data.ai_configured;
                    this.aiForm.ai_revision = data.ai_revision;
                    if (this.aiForm.ai_api_key === submittedKey) { this.aiForm.ai_api_key = ''; }
                }
                this.aiFeedback = 'Konfigurasi AI tersimpan. Perubahan baru yang belum dikirim tetap berada di formulir.';
            } catch (error) {
                this.aiFeedback = Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.error || 'Konfigurasi gagal disimpan. Masukan Anda tetap dipertahankan.';
            } finally { this.aiSaving = false; }
        },
        async sendAiTest() {
            if (this.sendingAiTest || this.aiSaving || this.aiDirty || !this.aiForm.ai_configured || !this.aiTest.prompt.trim()) {
                this.aiTest.error = 'Simpan konfigurasi lengkap dan isi instruksi sebelum menjalankan uji AI.'; return;
            }
            this.cancelTest();
            const sequence = testSequence;
            const fingerprint = this.fingerprint();
            const prompt = this.aiTest.prompt;
            testController = new AbortController(); this.sendingAiTest = true;
            try {
                const { data } = await window.axios.post(config.testUrl, { prompt, expected_revision: this.aiForm.ai_revision }, { signal: testController.signal, timeout: 40000 });
                if (sequence !== testSequence || fingerprint !== this.fingerprint() || prompt !== this.aiTest.prompt) { return; }
                this.aiTest.result = data.result;
            } catch (error) {
                if (sequence !== testSequence || error.code === 'ERR_CANCELED') { return; }
                this.aiTest.error = (error.response?.data?.error || 'Uji AI gagal. Periksa konfigurasi dan koneksi.') + (error.response?.data?.request_id ? ` Ref: ${error.response.data.request_id}` : '');
            } finally { if (sequence === testSequence) { this.sendingAiTest = false; } }
        },
    };
}
