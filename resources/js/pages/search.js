const FILTER_FIELDS = ['status', 'date_from', 'date_to', 'facility_id'];
const SORT_OPTIONS = ['relevance', 'latest', 'oldest'];
const DEFAULT_SORT = 'relevance';
const DEBOUNCE_DELAY = 300;

function safeJsonParse(value) {
	if (!value) return null;
	try {
		return JSON.parse(value);
	} catch (error) {
		return null;
	}
}

export function escapeHTML(value) {
	if (value === null || value === undefined) return '';
	return String(value)
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

export function safeUrl(url, baseOrigin) {
	if (url === null || url === undefined) return null;
	const raw = String(url).trim();
	if (!raw) return null;
	const base = baseOrigin || (typeof window !== 'undefined' && window.location ? window.location.origin : 'http://localhost');
	const lower = raw.toLowerCase();
	try {
		const parsed = new URL(raw, base);
		if (lower.startsWith('http://') || lower.startsWith('https://')) {
			return parsed.href;
		}
		const disallowed = ['javascript:', 'data:', 'mailto:', 'tel:'];
		if (disallowed.some((scheme) => lower.startsWith(scheme))) {
			return null;
		}
		// Treat any non absolute-http URL as relative and keep only the path portion
		return parsed.pathname + parsed.search + parsed.hash;
	} catch (error) {
		return null;
	}
}

export function normalizeState(state = {}, defaultPerPage = 15) {
	const normalized = {
		q: typeof state.q === 'string' ? state.q.trim() : String(state.q || '').trim(),
		type: typeof state.type === 'string' ? state.type.trim() : String(state.type || '').trim(),
		sort: SORT_OPTIONS.includes(state.sort) ? state.sort : DEFAULT_SORT,
		page: Math.max(1, Number.parseInt(state.page, 10) || 1),
		per_page: Math.max(1, Number.parseInt(state.per_page, 10) || defaultPerPage),
		filters: {}
	};
	const filters = state.filters && typeof state.filters === 'object' ? state.filters : {};
	FILTER_FIELDS.forEach((key) => {
		const value = filters[key];
		if (value !== undefined && value !== null) {
			const clean = String(value).trim();
			if (clean) {
				normalized.filters[key] = clean;
			}
		}
	});
	return normalized;
}

export function parseStateFromUrl(search = '', defaultPerPage = 15) {
	const params = new URLSearchParams(search);
	const filtersRaw = params.get('filters');
	const filterObj = safeJsonParse(filtersRaw) || {};
	return normalizeState(
		{
			q: params.get('q') || '',
			type: params.get('type') || '',
			sort: params.get('sort') || DEFAULT_SORT,
			page: Number.parseInt(params.get('page'), 10) || 1,
			per_page: Number.parseInt(params.get('per_page'), 10) || defaultPerPage,
			filters: filterObj
		},
		defaultPerPage
	);
}

export function buildApiUrl(apiEndpoint, state, defaultPerPage = 15) {
	const normalized = normalizeState(state, defaultPerPage);
	const params = new URLSearchParams();
	if (normalized.q) params.set('q', normalized.q);
	if (normalized.type) params.set('type', normalized.type);
	if (normalized.sort) params.set('sort', normalized.sort);
	params.set('page', String(normalized.page));
	params.set('per_page', String(normalized.per_page));
	if (Object.keys(normalized.filters).length > 0) {
		params.set('filters', JSON.stringify(normalized.filters));
	}
	return `${apiEndpoint}?${params.toString()}`;
}

export function syncUrl(state, { pushHistory = false } = {}, defaultPerPage = 15) {
	if (typeof window === 'undefined') return;
	const normalized = normalizeState(state, defaultPerPage);
	const params = new URLSearchParams();
	if (normalized.q) params.set('q', normalized.q);
	if (normalized.type) params.set('type', normalized.type);
	if (normalized.sort) params.set('sort', normalized.sort);
	params.set('page', String(normalized.page));
	params.set('per_page', String(normalized.per_page));
	if (Object.keys(normalized.filters).length > 0) {
		params.set('filters', JSON.stringify(normalized.filters));
	}
	const query = params.toString();
	const newUrl = query ? `${window.location.pathname}?${query}` : window.location.pathname;
	const method = pushHistory ? 'pushState' : 'replaceState';
	window.history[method]({}, '', newUrl);
}

export function isSearchReady(state = {}) {
	const q = typeof state.q === 'string' ? state.q.trim() : '';
	const hasQuery = q.length >= 2;
	const filters = state.filters && typeof state.filters === 'object' ? state.filters : {};
	const hasFilters = Object.keys(filters).length > 0;
	const hasType = typeof state.type === 'string' && state.type.trim().length > 0;
	return hasQuery || hasFilters || hasType;
}

export function readStateFromForm(els, defaultPerPage = 15) {
	if (!els) {
		return normalizeState({}, defaultPerPage);
	}
	const filters = {
		status: els.status ? els.status.value : '',
		date_from: els.dateFrom ? els.dateFrom.value : '',
		date_to: els.dateTo ? els.dateTo.value : '',
		facility_id: els.facilityId ? els.facilityId.value : ''
	};
	return normalizeState(
		{
			q: els.q ? els.q.value : '',
			type: els.type ? els.type.value : '',
			sort: els.sort ? els.sort.value : DEFAULT_SORT,
			per_page: els.perPage ? Number.parseInt(els.perPage.value, 10) || defaultPerPage : defaultPerPage,
			filters
		},
		defaultPerPage
	);
}

export function applyStateToForm(state, els) {
	if (!els) return;
	const normalized = normalizeState(state, state?.per_page || 15);
	if (els.q) els.q.value = normalized.q;
	if (els.type) els.type.value = normalized.type;
	if (els.sort) els.sort.value = normalized.sort;
	if (els.status) els.status.value = normalized.filters.status || '';
	if (els.dateFrom) els.dateFrom.value = normalized.filters.date_from || '';
	if (els.dateTo) els.dateTo.value = normalized.filters.date_to || '';
	if (els.facilityId) els.facilityId.value = normalized.filters.facility_id || '';
	if (els.perPage) els.perPage.value = String(normalized.per_page);
}

export function renderResultsHTML(items = []) {
	if (!Array.isArray(items) || items.length === 0) {
		return '';
	}
	return items
		.map((item) => {
			const safeTitle = escapeHTML(item?.title ?? 'Tanpa judul');
			const subtitle = escapeHTML(item?.subtitle ?? '');
			const metaParts = [];
			if (item?.type) metaParts.push(`<span>${escapeHTML(item.type)}</span>`);
			if (item?.created_at) metaParts.push(`<span>Dibuat: ${escapeHTML(item.created_at)}</span>`);
			const metaHtml = metaParts.length ? `<div class="result-card__meta">${metaParts.join('')}</div>` : '';
			const matchedFields = Array.isArray(item?.matched_fields) ? item.matched_fields.filter(Boolean) : [];
			const tagsHtml = matchedFields.length
				? `<div class="result-card__tags">${matchedFields
						.map((field) => `<span class="result-card__tag">${escapeHTML(field)}</span>`)
						.join('')}</div>`
				: '';
			const safeDetail = safeUrl(item?.links?.detail ?? null);
			const titleHtml = safeDetail ? `<a href="${safeDetail}">${safeTitle}</a>` : safeTitle;
			const idAttr = item?.id !== undefined ? ` data-result-id="${escapeHTML(item.id)}"` : '';
			return `<article class="result-card"${idAttr}><div class="result-card__title">${titleHtml}</div>${subtitle ? `<div class="result-card__subtitle">${subtitle}</div>` : ''}${metaHtml}${tagsHtml}</article>`;
		})
		.join('');
}

function debounce(fn, delay) {
	let timer = null;
	return function debounced(...args) {
		if (timer) clearTimeout(timer);
		timer = setTimeout(() => fn.apply(this, args), delay);
	};
}

function renderSkeleton(count = 3) {
	return Array.from({ length: count })
		.map(() => '<div class="result-card skeleton" style="height: 96px;"></div>')
		.join('');
}

export function initSearchPage(rootEl) {
	if (!rootEl) return;
	const apiEndpoint = rootEl.dataset.apiEndpoint;
	const defaultPerPage = Number.parseInt(rootEl.dataset.defaultPerPage, 10) || 15;
	if (!apiEndpoint) return;

	const typeOptions = safeJsonParse(rootEl.dataset.types) || [];
	const els = {
		form: rootEl.querySelector('[data-search-form]'),
		q: rootEl.querySelector('input[name="q"]'),
		type: rootEl.querySelector('select[name="type"]'),
		sort: rootEl.querySelector('select[name="sort"]'),
		status: rootEl.querySelector('select[name="status"]'),
		dateFrom: rootEl.querySelector('input[name="date_from"]'),
		dateTo: rootEl.querySelector('input[name="date_to"]'),
		facilityId: rootEl.querySelector('input[name="facility_id"]'),
		perPage: rootEl.querySelector('select[name="per_page"]'),
		reset: rootEl.querySelector('[data-reset-search]'),
		results: rootEl.querySelector('[data-search-results]'),
		emptyState: rootEl.querySelector('[data-empty-state]'),
		errorBox: rootEl.querySelector('[data-search-error]'),
		statusBar: rootEl.querySelector('[data-search-status]'),
		pagePrev: rootEl.querySelector('[data-page-prev]'),
		pageNext: rootEl.querySelector('[data-page-next]'),
		pageIndicator: rootEl.querySelector('[data-page-indicator]')
	};

	if (!els.form) return;

	if (els.type && Array.isArray(typeOptions) && typeOptions.length > 0) {
		els.type.innerHTML = '';
		typeOptions.forEach((opt) => {
			const option = document.createElement('option');
			option.value = opt?.value ?? '';
			option.textContent = opt?.label ?? opt?.value ?? '';
			els.type.appendChild(option);
		});
	}

	let state = normalizeState(parseStateFromUrl(window.location.search, defaultPerPage), defaultPerPage);
	let controller = null;
	let lastRequestId = 0;

	applyStateToForm(state, els);

	function updateStatus(message) {
		if (els.statusBar) {
			els.statusBar.textContent = message;
		}
	}

	function showError(message) {
		if (!els.errorBox) return;
		if (message) {
			els.errorBox.textContent = message;
			els.errorBox.setAttribute('aria-hidden', 'false');
		} else {
			els.errorBox.textContent = '';
			els.errorBox.setAttribute('aria-hidden', 'true');
		}
	}

	function toggleEmptyState(show) {
		if (!els.emptyState) return;
		els.emptyState.hidden = !show;
	}

	function setResults(html) {
		if (els.results) {
			els.results.innerHTML = html;
		}
	}

	function updatePagination(meta) {
		const total = Number(meta.total) || 0;
		const perPage = Number(meta.per_page) || defaultPerPage;
		const page = Number(meta.page) || 1;
		const pageCount = Math.max(1, Math.ceil(total / perPage) || 1);
		if (els.pageIndicator) {
			els.pageIndicator.textContent = `Halaman ${page} dari ${pageCount}`;
		}
		if (els.pagePrev) {
			els.pagePrev.disabled = page <= 1;
		}
		if (els.pageNext) {
			els.pageNext.disabled = page >= pageCount || pageCount === 0;
		}
	}

	function stopLoading(abort = false) {
		if (abort && controller) {
			controller.abort();
		}
		controller = null;
	}

	async function fetchResults() {
		if (!isSearchReady(state)) {
			stopLoading(true);
			state = normalizeState({ ...state, page: 1 }, defaultPerPage);
			setResults('');
			toggleEmptyState(false);
			showError(null);
			updateStatus('Siap mencari. Masukkan kata kunci minimal 2 karakter atau gunakan filter.');
			updatePagination({ page: state.page, per_page: state.per_page, total: 0 });
			return;
		}

		if (controller) {
			controller.abort();
		}
		controller = new AbortController();
		const requestId = ++lastRequestId;
		showError(null);
		updateStatus('Memuat hasil pencarian…');
		setResults(renderSkeleton());
		toggleEmptyState(false);

		try {
			const url = buildApiUrl(apiEndpoint, state, defaultPerPage);
			const response = await fetch(url, {
				signal: controller.signal,
				headers: { Accept: 'application/json' },
				credentials: 'same-origin'
			});

			if (!response.ok) {
				const errorPayload = await response.json().catch(() => null);
				if (response.status === 422) {
					const validationMessages = errorPayload?.errors
						? Object.values(errorPayload.errors)
							.flat()
							.join(' ')
						: '';
					const message = errorPayload?.message || 'Permintaan tidak valid.';
					throw new Error(`${message}${validationMessages ? ` — ${validationMessages}` : ''}`);
				}
				const generic = errorPayload?.message ? `: ${errorPayload.message}` : '';
				throw new Error(`Gagal memuat pencarian${generic}`);
			}

			const payload = await response.json();
			if (requestId !== lastRequestId) {
				return;
			}

			const items = Array.isArray(payload?.data) ? payload.data : [];
			const pagination = payload?.pagination || {};
			const total = Number(pagination.total) || 0;
			const perPage = Number(pagination.per_page) || state.per_page || defaultPerPage;
			const page = Number(pagination.page) || state.page;
			state = normalizeState({ ...state, page, per_page: perPage }, defaultPerPage);
			syncUrl(state, { pushHistory: false }, defaultPerPage);
			if (items.length === 0) {
				setResults('');
				toggleEmptyState(true);
				updateStatus('Tidak ada hasil yang cocok dengan pencarian ini.');
			} else {
				setResults(renderResultsHTML(items));
				toggleEmptyState(false);
				const start = (state.page - 1) * state.per_page + 1;
				const end = start + items.length - 1;
				updateStatus(`Menampilkan ${start}-${end} dari ${total} hasil.`);
			}
			updatePagination({ page: state.page, per_page: state.per_page, total });
		} catch (error) {
			if (error.name === 'AbortError') {
				return;
			}
			showError(error.message || 'Terjadi kesalahan saat memuat hasil.');
			setResults('');
			toggleEmptyState(false);
			updateStatus('Tidak dapat memuat hasil pencarian.');
		} finally {
			controller = null;
		}
	}

	const debouncedInput = debounce(() => {
		const formState = readStateFromForm(els, defaultPerPage);
		state = normalizeState({ ...state, ...formState, page: 1 }, defaultPerPage);
		syncUrl(state, { pushHistory: false }, defaultPerPage);
		fetchResults();
	}, DEBOUNCE_DELAY);

	els.q?.addEventListener('input', () => {
		debouncedInput();
	});

	els.form.addEventListener('change', (event) => {
		if (event.target && event.target.name === 'q') {
			return;
		}
		const formState = readStateFromForm(els, defaultPerPage);
		state = normalizeState({ ...state, ...formState, page: 1 }, defaultPerPage);
		syncUrl(state, { pushHistory: false }, defaultPerPage);
		fetchResults();
	});

	els.form.addEventListener('submit', (event) => {
		event.preventDefault();
		const formState = readStateFromForm(els, defaultPerPage);
		state = normalizeState({ ...state, ...formState, page: 1 }, defaultPerPage);
		syncUrl(state, { pushHistory: true }, defaultPerPage);
		fetchResults();
	});

	els.reset?.addEventListener('click', () => {
		state = normalizeState({ per_page: defaultPerPage, page: 1 }, defaultPerPage);
		applyStateToForm(state, els);
		syncUrl(state, { pushHistory: true }, defaultPerPage);
		fetchResults();
	});

	if (els.pagePrev) {
		els.pagePrev.addEventListener('click', () => {
			if (!isSearchReady(state)) return;
			if (state.page <= 1) return;
			state = normalizeState({ ...state, page: state.page - 1 }, defaultPerPage);
			syncUrl(state, { pushHistory: true }, defaultPerPage);
			fetchResults();
		});
	}

	if (els.pageNext) {
		els.pageNext.addEventListener('click', () => {
			if (!isSearchReady(state)) return;
			state = normalizeState({ ...state, page: state.page + 1 }, defaultPerPage);
			syncUrl(state, { pushHistory: true }, defaultPerPage);
			fetchResults();
		});
	}

	window.addEventListener('popstate', () => {
		state = normalizeState(parseStateFromUrl(window.location.search, defaultPerPage), defaultPerPage);
		applyStateToForm(state, els);
		fetchResults();
	});

	updatePagination({ page: state.page, per_page: state.per_page, total: 0 });
	if (isSearchReady(state)) {
		fetchResults();
	} else {
		setResults('');
		updateStatus('Siap mencari. Masukkan kata kunci minimal 2 karakter atau gunakan filter.');
	}
}
