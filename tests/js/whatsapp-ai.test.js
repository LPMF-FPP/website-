import assert from 'node:assert/strict';
import test from 'node:test';
import { magicToolbar, whatsappAiSettings } from '../../resources/js/components/whatsapp-ai.js';

function deferred() {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}

function toolbar() {
    const data = { form: { id: 1, message: 'Draf netral' } };
    let session = 1;
    const component = magicToolbar({ target: 'form.message', textareaId: 'draft', composeUrl: '/compose', settingsUrl: '/settings', revision: 'saved', destination: 'https://api.openai.com/v1', recordIdentity: () => data.form.id, editorSession: () => session, contextType: () => 'broadcast' });
    component.$data = data;
    component.setModelValue = value => { data.form.message = value; };
    component.openAiModal(); component.aiPrompt = 'Instruksi netral';
    return { component, data, newSession: () => { session += 1; } };
}

test('compose snapshots instructions and omits draft by default; explicit action applies preview', async () => {
    const { component, data } = toolbar();
    let sent;
    global.window = { axios: {
        post: async (_url, payload) => { sent = payload; return { data: { result: 'Hasil netral' } }; },
        get: async () => ({ data: { ai_configured: true, ai_revision: 'saved', ai_base_url: 'https://api.openai.com/v1' } }),
    } };
    await component.generateAi();
    assert.equal(sent.current_text, null); assert.equal(sent.share_draft, false); assert.equal(sent.expected_revision, 'saved');
    assert.equal(data.form.message, 'Draf netral'); assert.equal(component.aiResult, 'Hasil netral');
    await component.useAiResult(); assert.equal(data.form.message, 'Hasil netral'); assert.equal(component.aiOpen, false);
});

test('explicit consent includes exact draft and current context', async () => {
    const { component } = toolbar(); component.shareDraft = true;
    global.window = { axios: { post: async (_url, payload) => {
        assert.equal(payload.current_text, 'Draf netral'); assert.equal(payload.context_type, 'broadcast');
        return { data: { result: 'Hasil netral' } };
    } } };
    await component.generateAi(); assert.equal(component.aiLoading, false);
});

for (const change of ['draft', 'record', 'session', 'prompt', 'consent']) {
    test(`late ${change} change rejects stale preview and protects draft`, async () => {
        const { component, data, newSession } = toolbar(); const pending = deferred();
        global.window = { axios: { post: () => pending.promise } };
        const request = component.generateAi();
        if (change === 'draft') { data.form.message = 'Draf terbaru'; }
        if (change === 'record') { data.form.id = 2; }
        if (change === 'session') { newSession(); }
        if (change === 'prompt') { component.aiPrompt = 'Instruksi terbaru'; }
        if (change === 'consent') { component.shareDraft = true; }
        pending.resolve({ data: { result: 'Hasil lama' } }); await request;
        assert.equal(component.aiResult, ''); assert.equal(component.aiLoading, false);
        await component.useAiResult(); assert.notEqual(data.form.message, 'Hasil lama');
    });
}

test('cancel/reopen aborts old generation without clearing new loading state or preview', async () => {
    const { component } = toolbar(); const old = deferred(), next = deferred(); let calls = 0, oldSignal;
    global.window = { axios: { post: (_url, _payload, options) => {
        calls += 1; if (calls === 1) { oldSignal = options.signal; return old.promise; } return next.promise;
    } } };
    const previous = component.generateAi(); component.closeAiModal(); assert.equal(oldSignal.aborted, true);
    component.openAiModal(); const current = component.generateAi();
    old.resolve({ data: { result: 'Lama' } }); await previous; assert.equal(component.aiLoading, true); assert.equal(component.aiResult, '');
    next.resolve({ data: { result: 'Baru' } }); await current; assert.equal(component.aiResult, 'Baru'); assert.equal(component.aiLoading, false);
});

test('apply rechecks draft even after valid preview', async () => {
    const { component, data } = toolbar();
    global.window = { axios: { post: async () => ({ data: { result: 'Hasil lama' } }) } };
    await component.generateAi(); data.form.message = 'Lebih baru'; await component.useAiResult();
    assert.equal(data.form.message, 'Lebih baru'); assert.equal(component.aiResult, '');
});

test('countdown tokens remain protected even when draft sharing is off', async () => {
    const component = magicToolbar({ target: 'form.message', contextType: () => 'iso_countdown', preserveTokens: ['event_name', 'motivation_message'] });
    component.$data = { form: { message: '{event_name} {motivation_message}' } };
    component.openAiModal(); component.aiPrompt = 'Uji';
    global.window = { axios: { post: async (_url, payload) => {
        assert.equal(payload.current_text, null); return { data: { result: 'Salam netral' } };
    } } };
    await component.generateAi(); assert.equal(component.aiResult, ''); assert.match(component.aiError, /variabel template/);
});

test('ordinary editor placeholders cannot be removed before applying a preview', async () => {
    const component = magicToolbar({ target: 'form.message', contextType: () => 'task', preserveTokens: ['nama', 'resi'] });
    component.$data = { form: { message: 'Untuk {nama}, resi {resi}' } };
    component.openAiModal(); component.aiPrompt = 'Uji netral';
    global.window = { axios: { post: async () => ({ data: { result: 'Untuk {nama}' } }) } };
    await component.generateAi();
    assert.equal(component.aiResult, '');
    assert.match(component.aiError, /variabel template/);
    await component.useAiResult();
    assert.equal(component.$data.form.message, 'Untuk {nama}, resi {resi}');
});

test('errors expose safe feedback and correlation reference and reset loading', async () => {
    const { component } = toolbar();
    global.window = { axios: { post: async () => { throw { response: { data: { error: 'Konfigurasi salah', request_id: 'reference' } } }; } } };
    await component.generateAi(); assert.equal(component.aiLoading, false); assert.equal(component.aiRequestId, 'reference'); assert.equal(component.aiError, 'Konfigurasi salah');
});

async function settings() {
    global.window = { axios: { get: async () => ({ data: { ai_provider: 'openai', ai_base_url: 'https://api.openai.com/v1', ai_model: 'gpt-6-luna', ai_api_key_configured: true, ai_configured: true, ai_revision: 'saved' } }) }, confirm: () => true };
    const component = whatsappAiSettings({ settingsUrl: '/settings', saveUrl: '/save', testUrl: '/test' });
    component.$watch = () => {};
    await component.init(); return component;
}

test('overlapping save is blocked and newer key input survives old success', async () => {
    const component = await settings(); const pending = deferred(); let calls = 0;
    window.axios.post = () => { calls += 1; return pending.promise; };
    component.aiForm.ai_api_key = 'submitted-key'; const request = component.saveAiSettings();
    await component.saveAiSettings(); assert.equal(calls, 1);
    component.aiForm.ai_api_key = 'new-unsaved-key';
    pending.resolve({ data: { ai_api_key_configured: true, ai_configured: true, ai_revision: 'new' } }); await request;
    assert.equal(component.aiForm.ai_api_key, 'new-unsaved-key'); assert.equal(component.aiDirty, true); assert.equal(component.aiSaving, false);
});

test('failed save preserves entered key and removal follows authoritative status', async () => {
    const component = await settings(); component.aiForm.ai_api_key = 'unsaved-key';
    window.axios.post = async () => { throw { response: { data: { errors: { key: ['Isian kunci salah'] } } } }; };
    await component.saveAiSettings(); assert.equal(component.aiForm.ai_api_key, 'unsaved-key'); assert.equal(component.aiFeedback, 'Isian kunci salah');
    window.axios.post = async (_url, payload) => {
        assert.equal(payload.ai_remove_key, true); assert.equal(payload.ai_api_key, null);
        return { data: { ai_api_key_configured: false, ai_configured: false, ai_revision: 'removed' } };
    };
    await component.saveAiSettings('remove'); assert.equal(component.aiForm.ai_api_key_configured, false); assert.equal(component.aiForm.ai_api_key, '');
});

test('reset is explicit and omits conflicting overrides', async () => {
    const component = await settings();
    window.axios.post = async (_url, payload) => {
        assert.equal(payload.ai_reset_overrides, true); assert.equal(payload.ai_base_url, null); assert.equal(payload.ai_model, null);
        return { data: { ai_base_url: 'https://api.openai.com/v1', ai_model: 'gpt-6-luna', ai_configured: true, ai_revision: 'reset' } };
    };
    await component.saveAiSettings('reset'); assert.equal(component.aiDirty, false);
});

test('reset does not transmit or discard an unsaved replacement key', async () => {
    const component = await settings(); component.aiForm.ai_api_key = 'unsaved-key';
    window.axios.post = async (_url, payload) => {
        assert.equal(payload.ai_api_key, null);
        return { data: { ai_base_url: 'https://api.openai.com/v1', ai_model: 'gpt-6-luna', ai_configured: true, ai_revision: 'reset' } };
    };
    await component.saveAiSettings('reset'); assert.equal(component.aiForm.ai_api_key, 'unsaved-key'); assert.equal(component.aiDirty, true);
});

test('AI Test blocks unsaved configuration and ignores obsolete results after save', async () => {
    const component = await settings(); component.aiForm.ai_model = 'unsaved'; let calls = 0;
    window.axios.post = async () => { calls += 1; return { data: { result: 'x' } }; };
    await component.sendAiTest(); assert.equal(calls, 0);
    component.aiForm.ai_model = 'gpt-6-luna'; const pending = deferred(); let signal;
    window.axios.post = (_url, _payload, options) => { signal = options.signal; return pending.promise; };
    const request = component.sendAiTest(); component.cancelTest();
    assert.equal(signal.aborted, true); pending.resolve({ data: { result: 'Obsolete' } }); await request;
    assert.equal(component.aiTest.result, ''); assert.equal(component.sendingAiTest, false);
});

test('saved settings revision and destination reach existing compose tools without reload', async () => {
    const listeners = new Map();
    global.window = {
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        addEventListener(type, listener) { listeners.set(type, listener); },
        removeEventListener(type) { listeners.delete(type); },
        dispatchEvent(event) { listeners.get(event.type)?.(event); return true; },
        confirm: () => true,
        axios: {
            get: async () => ({ data: { ai_provider: 'openai', ai_base_url: 'https://api.openai.com/v1', ai_model: 'gpt-6-luna', ai_api_key_configured: true, ai_configured: true, ai_revision: 'old' } }),
            post: async (url, payload) => {
                if (url === '/save') {
                    assert.equal(payload.expected_revision, 'old');
                    return { data: { ai_provider: 'openrouter', ai_base_url: 'https://openrouter.ai/api/v1', ai_model: 'openrouter/auto', ai_api_key_configured: true, ai_configured: true, ai_revision: 'new' } };
                }
                assert.equal(url, '/compose');
                assert.equal(payload.expected_revision, 'new');
                return { data: { result: 'Hasil terbaru' } };
            },
        },
    };
    const form = { message: 'Draf netral' };
    const toolbarConfig = { target: 'form.message', textareaId: 'draft', composeUrl: '/compose', settingsUrl: '/settings', destination: 'https://api.openai.com/v1', revision: 'old', contextType: () => 'general' };
    const toolbar = magicToolbar(toolbarConfig);
    toolbar.$data = { form };
    toolbar.$watch = () => {};
    toolbar.setModelValue = value => { form.message = value; };
    toolbar.init();
    const component = whatsappAiSettings({ settingsUrl: '/settings', saveUrl: '/save', testUrl: '/test' });
    component.$watch = () => {};
    await component.init();
    component.aiForm.ai_provider = 'openrouter';
    component.aiForm.ai_base_url = 'https://openrouter.ai/api/v1';
    component.aiForm.ai_model = 'openrouter/auto';
    component.aiForm.ai_api_key = 'neutral-key';
    component.aiForm.ai_confirm_destination = true;
    await component.saveAiSettings();
    toolbar.openAiModal(); toolbar.aiPrompt = 'Instruksi netral';
    await toolbar.generateAi();
    assert.equal(toolbar.destination, 'https://openrouter.ai/api/v1');
    assert.equal(toolbar.aiResult, 'Hasil terbaru');
    window.dispatchEvent({ type: 'storage', key: 'lpmf.whatsapp.ai.configuration', newValue: JSON.stringify({ revision: 'other-tab', destination: 'https://api.deepseek.com/v1' }) });
    assert.equal(toolbar.aiResult, '');
    assert.match(toolbar.aiError, /tab lain/);
    await toolbar.useAiResult();
    assert.equal(form.message, 'Draf netral');
    toolbar.destroy();
});

test('authoritative revision check blocks applying a preview from old settings', async () => {
    const { component, data } = toolbar();
    global.window = { axios: {
        post: async () => ({ data: { result: 'Hasil lama' } }),
        get: async () => ({ data: { ai_configured: true, ai_revision: 'newer', ai_base_url: 'https://openrouter.ai/api/v1' } }),
    } };
    await component.generateAi();
    await component.useAiResult();
    assert.equal(data.form.message, 'Draf netral');
    assert.equal(component.aiResult, '');
    assert.match(component.aiError, /Konfigurasi AI berubah/);
    assert.equal(component.destination, 'https://openrouter.ai/api/v1');
});

test('prompt and consent edits abort pending compose requests immediately', async () => {
    for (const field of ['aiPrompt', 'shareDraft']) {
        const { component } = toolbar();
        const watchers = new Map();
        let signal;
        component.$watch = (name, callback) => watchers.set(name, callback);
        global.window = { addEventListener() {}, removeEventListener() {} };
        component.init();
        global.window.axios = { post: (_url, _payload, options) => {
            signal = options.signal;
            return new Promise((_resolve, reject) => options.signal.addEventListener('abort', () => reject({ code: 'ERR_CANCELED' })));
        } };
        const request = component.generateAi();
        component[field] = field === 'aiPrompt' ? 'Instruksi diperbarui' : true;
        watchers.get(field)();
        assert.equal(signal.aborted, true);
        assert.equal(component.aiLoading, false);
        assert.match(component.aiError, field === 'aiPrompt' ? /Instruksi berubah/ : /Persetujuan berbagi draf berubah/);
        component.closeAiModal();
        await request;
    }
});

test('text formatting keeps the formatted selection while variable insertion can place the caret', () => {
    const previousDocument = global.document;
    const textarea = {
        value: 'abc', selectionStart: 1, selectionEnd: 2, focus() {},
        setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
        dispatchEvent() {},
    };
    global.document = { getElementById: () => textarea };
    const component = magicToolbar({ target: 'form.message', textareaId: 'draft' });
    component.$data = { form: { message: 'abc' } };
    component.$nextTick = callback => callback();
    component.setModelValue = value => { component.$data.form.message = value; textarea.value = value; };
    component.formatText('bold');
    assert.equal(component.$data.form.message, 'a*b*c');
    assert.deepEqual([textarea.selectionStart, textarea.selectionEnd], [1, 4]);
    global.document = previousDocument;
});
