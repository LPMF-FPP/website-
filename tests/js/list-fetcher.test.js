import test from 'node:test';
import assert from 'node:assert/strict';

import { isListNavigationUrl } from '../../resources/js/utils/list-fetcher.js';

test('detects ready delivery sort links as list navigation', () => {
  const url = new URL('https://example.test/delivery?request_sort=receipt_number&request_direction=asc');

  assert.equal(isListNavigationUrl(url), true);
});

test('detects existing list navigation parameters', () => {
  assert.equal(isListNavigationUrl(new URL('https://example.test/delivery?sort=completed_at')), true);
  assert.equal(isListNavigationUrl(new URL('https://example.test/delivery?completed_page=2')), true);
  assert.equal(isListNavigationUrl(new URL('https://example.test/delivery?search=resi')), true);
});

test('ignores ordinary detail links', () => {
  const url = new URL('https://example.test/delivery/123');

  assert.equal(isListNavigationUrl(url), false);
});
