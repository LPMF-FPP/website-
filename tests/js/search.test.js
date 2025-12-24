import test from 'node:test';
import assert from 'node:assert/strict';
import {
	escapeHTML,
	normalizeState,
	parseStateFromUrl,
	isSearchReady,
	renderResultsHTML
} from '../../resources/js/pages/search.js';

test('escapeHTML sanitizes potentially dangerous strings', () => {
	const input = '<script>alert("x")</script>&"\'';
	const escaped = escapeHTML(input);
	assert.equal(escaped, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&amp;&quot;&#39;');
});

test('normalizeState trims values and removes empty filters', () => {
	const state = normalizeState(
		{
			q: '  halo  ',
			type: ' document ',
			sort: 'unknown',
			page: -1,
			per_page: 0,
			filters: {
				status: ' ',
				date_from: ' 2024-01-01 ',
				custom: 'noop',
				facility_id: '  1001  '
			}
		},
		10
	);
	assert.equal(state.q, 'halo');
	assert.equal(state.type, 'document');
	assert.equal(state.sort, 'relevance');
	assert.equal(state.page, 1);
	assert.equal(state.per_page, 10);
	assert.deepEqual(state.filters, { date_from: '2024-01-01', facility_id: '1001' });
});

test('parseStateFromUrl interprets filters JSON and pagination params', () => {
	const state = parseStateFromUrl(
		'?q=test&type=doc&sort=latest&page=2&per_page=30&filters=%7B%22status%22%3A%22done%22%7D',
		15
	);
	assert.equal(state.q, 'test');
	assert.equal(state.type, 'doc');
	assert.equal(state.sort, 'latest');
	assert.equal(state.page, 2);
	assert.equal(state.per_page, 30);
	assert.deepEqual(state.filters, { status: 'done' });
});

test('isSearchReady validates query length and filter presence', () => {
	assert.equal(isSearchReady({ q: 'a', filters: {} }), false);
	assert.equal(isSearchReady({ q: 'ab', filters: {} }), true);
	assert.equal(isSearchReady({ q: 'a', filters: { status: 'done' } }), true);
	assert.equal(isSearchReady({ q: '', filters: {}, type: 'doc' }), true);
});

test('renderResultsHTML escapes hostile API payloads', () => {
	const html = renderResultsHTML([
		{
			id: '<img src=x onerror=alert(1)>',
			title: '<script>alert(1)</script>',
			subtitle: '<b>bold</b>',
			matched_fields: ['<em>match</em>'],
			created_at: '2024-01-01',
			links: { detail: 'javascript:alert(1)' }
		}
	]);
	assert.ok(!html.includes('<script>alert(1)</script>'));
	assert.ok(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));
	assert.ok(!html.includes('javascript:alert'));
	assert.ok(html.includes('&lt;em&gt;match&lt;/em&gt;'));
});
