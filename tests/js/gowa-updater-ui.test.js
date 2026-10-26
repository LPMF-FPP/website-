import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const overview = fs.readFileSync(new URL('../../resources/views/whatsapp/partials/tab-overview.blade.php', import.meta.url), 'utf8');
const hub = fs.readFileSync(new URL('../../resources/views/whatsapp/index.blade.php', import.meta.url), 'utf8');

test('GOWA overview gates installation on verified preparation and mutation permission', () => {
    assert.match(overview, /gowaAvailabilityMessage\(overviewData\?\.gowa_update\?\.reason\)/);
    assert.match(overview, /preparation_ready/);
    assert.match(overview, /gowa_update\?\.can_request/);
    assert.match(overview, /preparation\?\.release_id === gowaUpdateCheck.approved_release_id/);
    assert.match(overview, /!overviewData\.gowa_update\.can_retry/);
});

test('Overview polls the operation detail endpoint by UUID with a bounded interval', () => {
    assert.match(hub, /pollGowaOperation\(operationId\)/);
    assert.match(hub, /updates\.detail/);
    assert.match(hub, /setTimeout\(poll, 3000\)/);
    assert.match(hub, /gowaOperationPollAttempts >= 20/);
    assert.match(hub, /operation\?\.id !== operationId/);
});

test('preparation and installation use separate confirmed CSRF protected actions', () => {
    assert.match(hub, /async checkGowaUpdate\(\)/);
    assert.match(hub, /updates\.check/);
    assert.match(hub, /async requestGowaUpdate\(\)/);
    assert.match(hub, /async prepareGowaRelease\(\)/);
    assert.match(hub, /window\.axios\.post\('\{\{ route\("whatsapp.updates.prepare"\)/);
    assert.match(hub, /window\.axios\.post\('\{\{ route\("whatsapp.updates.request"\)/);
    assert.match(hub, /action_uuid: this.gowaInstallAction.uuid/);
    assert.match(hub, /confirmation: true/);
});

test('preparation polling is bounded and never installs automatically', () => {
    const poll = hub.slice(hub.indexOf('pollGowaPreparation(version) {'), hub.indexOf('async requestGowaUpdate()'));
    assert.match(poll, /gowaPreparationAttempts >= 300/);
    assert.match(poll, /updates.status/);
    assert.match(poll, /gowaPreparationPreviousObservedAt/);
    assert.doesNotMatch(poll, /updates.request|requestGowaUpdate\(/);
});

test('Overview exposes a read-only upstream update check with explicit result states', () => {
    assert.match(overview, /@click="checkGowaUpdate\(\)"/);
    assert.match(hub, /async checkGowaUpdate\(\)/);
    assert.match(hub, /updates\.check/);
    assert.match(overview, /gowaUpdateChecking \? 'Memeriksa rilis/);
    assert.match(overview, /blocked_reason === 'runtime_stale'/);
    assert.match(overview, /blocked_reason === 'current_version_unknown'/);
    assert.match(overview, /blocked_reason === 'release_not_approved'/);
});

function interactiveHub({ confirm = true, post = async () => ({ data: { message: 'accepted', data: { id: 'operation' } } }), get } = {}) {
    const timers = [];
    const methods = hub.slice(hub.indexOf('gowaStatusLabel(status) {'), hub.indexOf('async retryGowaUpdate() {'))
        .replace(/\{\{ route\("whatsapp\.updates\.([a-z]+)"\) \}\}/g, '/updates/$1');
    const context = vm.createContext({
        window: { confirm: () => confirm, crypto: { randomUUID: () => 'fixed-action-uuid' }, axios: { post, get } },
        setTimeout: (fn) => { timers.push(fn); return timers.length; }, clearTimeout: () => {},
        Date, fetch: async () => ({ ok: true, json: async () => ({ data: { latest_version: 'v9.7.0' } }) }),
    });
    const screen = new vm.Script(`({${methods}})`).runInContext(context);
    Object.assign(screen, {
        overviewData: { gowa_update: { available: true, preparation_available: true, can_request: true } },
        gowaUpdateCheck: { latest_version: 'v9.7.0', approved_release_id: 'release-a' },
        gowaUpdatePreparing: false, gowaUpdateSubmitting: false,
        gowaPreparationTimer: null, gowaPreparationAttempts: 0,
        loadTabData: async () => {}, pollGowaOperation: () => {},
    });
    return { screen, timers };
}

test('cancelled confirmation performs no preparation or installation', async () => {
    let calls = 0;
    const { screen } = interactiveHub({ confirm: false, post: async () => { calls += 1; } });
    await screen.prepareGowaRelease();
    await screen.requestGowaUpdate();
    assert.equal(calls, 0);
});

test('preparation never installs, including when its signed result becomes ready', async () => {
    const calls = [];
    const { screen, timers } = interactiveHub({
        post: async (url, payload) => { calls.push({ url, payload }); return { data: { message: 'queued' } }; },
        get: async () => ({ data: { data: { available: true, preparation_ready: true,
            preparation: { version: 'v9.7.0', state: 'ready', observed_at: new Date().toISOString() } } } }),
    });
    await screen.prepareGowaRelease();
    assert.equal(screen.gowaUpdatePreparing, true);
    assert.equal(calls[0].url, '/updates/prepare');
    await timers[0]();
    assert.equal(screen.gowaUpdatePreparing, false);
    assert.equal(calls.length, 1);
    assert.match(screen.gowaUpdateMessage, /Konfirmasikan instalasi/);
});

test('installation reuses its action UUID after a lost response', async () => {
    const payloads = [];
    const { screen } = interactiveHub({ post: async (url, payload) => {
        assert.equal(url, '/updates/request');
        payloads.push(payload);
        throw new Error('network lost');
    } });
    await screen.requestGowaUpdate();
    await screen.requestGowaUpdate();
    assert.equal(payloads.length, 2);
    assert.equal(payloads[0].action_uuid, payloads[1].action_uuid);
    assert.equal(screen.gowaUpdateSubmitting, false);
});

test('unavailable workers and read-only users never submit mutations', async () => {
    let calls = 0;
    const { screen } = interactiveHub({ post: async () => { calls += 1; } });
    screen.overviewData.gowa_update.preparation_available = false;
    screen.overviewData.gowa_update.can_request = false;
    await screen.prepareGowaRelease();
    await screen.requestGowaUpdate();
    assert.equal(calls, 0);
});

test('polling ignores an earlier proof without relying on the browser clock', async () => {
    const earlier = '2020-01-01T00:00:00Z';
    const { screen, timers } = interactiveHub({ get: async () => ({ data: { data: {
        preparation: { version: 'v9.7.0', state: 'failed', observed_at: earlier },
    } } }) });
    screen.overviewData.gowa_update.preparation = { observed_at: earlier };
    await screen.prepareGowaRelease();
    await timers[0]();
    assert.equal(screen.gowaUpdatePreparing, true);
    assert.equal(timers.length, 2);
});
