import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const overview = fs.readFileSync(new URL('../../resources/views/whatsapp/partials/tab-overview.blade.php', import.meta.url), 'utf8');
const hub = fs.readFileSync(new URL('../../resources/views/whatsapp/index.blade.php', import.meta.url), 'utf8');

test('GOWA update controls require explicit confirmation and server capability flags', () => {
    assert.match(overview, /:disabled="!selectedGowaRelease \|\| !gowaUpdateConfirmed/);
    assert.match(overview, /!overviewData\.gowa_update\.can_request/);
    assert.match(overview, /!overviewData\.gowa_update\.can_retry/);
});

test('Overview polls the operation detail endpoint by UUID with a bounded interval', () => {
    assert.match(hub, /pollGowaOperation\(operationId\)/);
    assert.match(hub, /updates\.detail/);
    assert.match(hub, /setTimeout\(poll, 3000\)/);
    assert.match(hub, /gowaOperationPollAttempts >= 20/);
    assert.match(hub, /operation\?\.id !== operationId/);
});

test('browser update payload contains no credentials or shell inputs', () => {
    const requestBlock = hub.slice(hub.indexOf('async requestGowaUpdate()'), hub.indexOf('async retryGowaUpdate()'));
    assert.match(requestBlock, /release_id: this\.selectedGowaRelease/);
    assert.match(requestBlock, /action_uuid: crypto\.randomUUID\(\)/);
    assert.doesNotMatch(requestBlock, /password|secret|docker|command|Authorization/i);
});

test('Overview exposes a read-only upstream update check with explicit result states', () => {
    assert.match(overview, /@click="checkGowaUpdate\(\)"/);
    assert.match(hub, /async checkGowaUpdate\(\)/);
    assert.match(hub, /updates\.check/);
    assert.match(overview, /gowaUpdateChecking \? 'Memeriksa GitHub/);
    assert.match(overview, /catalog_version_match/);
    assert.match(overview, /comparison_status === 'runtime_stale'/);
    assert.match(overview, /comparison_status === 'current_version_unknown'/);
});
