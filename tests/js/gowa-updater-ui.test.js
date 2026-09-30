import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const overview = fs.readFileSync(new URL('../../resources/views/whatsapp/partials/tab-overview.blade.php', import.meta.url), 'utf8');
const hub = fs.readFileSync(new URL('../../resources/views/whatsapp/index.blade.php', import.meta.url), 'utf8');

test('GOWA installation is only offered after a prepared release and explicit confirmation', () => {
    assert.match(overview, /gowaPreparation\?\.ready/);
    assert.match(overview, /!gowaUpdateConfirmed/);
    assert.match(overview, /Instal pembaruan/);
    assert.match(overview, /!overviewData\?\.gowa_update\?\.can_install/);
    assert.match(overview, /!overviewData\?\.gowa_update\?\.can_request/);
    assert.match(overview, /!overviewData\.gowa_update\.can_retry/);
});

test('Overview polls the operation detail endpoint by UUID with a bounded interval', () => {
    assert.match(hub, /pollGowaOperation\(operationId\)/);
    assert.match(hub, /updates\.detail/);
    assert.match(hub, /setTimeout\(poll, 3000\)/);
    assert.match(hub, /gowaOperationPollAttempts >= 20/);
    assert.match(hub, /operation\?\.id !== operationId/);
});

test('browser installation payload uses only the server-issued preparation ID', () => {
    const requestBlock = hub.slice(hub.indexOf('async requestGowaUpdate()'), hub.indexOf('async retryGowaUpdate()'));
    assert.match(requestBlock, /preparation_id: this\.gowaPreparation\.id/);
    assert.match(requestBlock, /action_uuid: crypto\.randomUUID\(\)/);
    assert.match(requestBlock, /confirmation: this\.gowaUpdateConfirmed/);
    assert.doesNotMatch(requestBlock, /release_id:/);
    assert.doesNotMatch(requestBlock, /password|secret|docker|command|Authorization/i);
});

test('Overview exposes a read-only upstream update check with explicit result states', () => {
    assert.match(overview, /@click="checkGowaUpdate\(\)"/);
    assert.match(hub, /async checkGowaUpdate\(\)/);
    assert.match(hub, /updates\.check/);
    assert.match(hub, /updates\.prepare/);
    assert.match(hub, /void this\.prepareGowaRelease\(\)/);
    assert.match(hub, /pollGowaPreparation\(payload\.data\?\.id\)/);
    assert.match(overview, /blocked_reason === 'runtime_stale'/);
    assert.match(overview, /blocked_reason === 'current_version_unknown'/);
    assert.match(overview, /GOWA tetap berjalan/);
    assert.match(hub, /class="-mb-px flex space-x-8 overflow-x-auto"/);
});
