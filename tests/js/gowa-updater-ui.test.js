import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';

const overview = fs.readFileSync(new URL('../../resources/views/whatsapp/partials/tab-overview.blade.php', import.meta.url), 'utf8');
const hub = fs.readFileSync(new URL('../../resources/views/whatsapp/index.blade.php', import.meta.url), 'utf8');

test('GOWA overview explains operational blockers and does not offer install before preparation is ready', () => {
    assert.match(overview, /gowaAvailabilityMessage\(overviewData\.gowa_update\.reason\)/);
    assert.match(overview, /Persiapan paket otomatis belum tersedia/);
    assert.doesNotMatch(overview, /Instal pembaruan/);
    assert.match(overview, /!overviewData\.gowa_update\.can_retry/);
});

test('Overview polls the operation detail endpoint by UUID with a bounded interval', () => {
    assert.match(hub, /pollGowaOperation\(operationId\)/);
    assert.match(hub, /updates\.detail/);
    assert.match(hub, /setTimeout\(poll, 3000\)/);
    assert.match(hub, /gowaOperationPollAttempts >= 20/);
    assert.match(hub, /operation\?\.id !== operationId/);
});

test('browser only performs a read-only check until preparation is available', () => {
    assert.match(hub, /async checkGowaUpdate\(\)/);
    assert.match(hub, /updates\.check/);
    assert.doesNotMatch(hub, /async requestGowaUpdate\(\)/);
    assert.doesNotMatch(hub, /updates\.request/);
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
