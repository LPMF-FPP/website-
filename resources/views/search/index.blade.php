@php
    use Illuminate\Support\Str;

    $docTypes = config('search.doc_types', ['all']);
    $docTypeLabels = config('search.doc_type_labels', []);
    $docTypeOptions = collect($docTypes)
        ->filter()
        ->map(fn ($type) => [
            'value' => $type,
            'label' => $docTypeLabels[$type]
                ?? ($type === 'all' ? 'Semua Dokumen' : Str::of($type)->replace('_', ' ')->title()),
        ])
        ->values()
        ->all();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <x-breadcrumbs :items="[['label' => 'Beranda', 'url' => url('/')], ['label' => 'Pencarian']]" />
            <div>
                <h1 class="text-2xl font-semibold text-primary-900">Pencarian Dokumen</h1>
                <p class="text-sm text-accent-600">Telusuri tersangka/penyidik dan berita acara dalam satu tampilan terpadu.</p>
            </div>
        </div>
    </x-slot>

    <section
        class="search-shell space-y-6"
        data-search-root
        data-api-endpoint="{{ url('/search/data') }}"
    >
        <style>
            .search-shell { color: #0f172a; }
            .search-shell .search-card { background: #fff; border-radius: 1.25rem; padding: 2rem; box-shadow: 0 20px 45px rgba(15,23,42,0.08); border: 1px solid rgba(15,23,42,0.05); }
            .search-shell .search-toolbar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: stretch; }
            .search-shell .search-input-group { flex: 1; display: flex; border: 1px solid #d0d5dd; border-radius: 999px; overflow: hidden; background: #f8fafc; }
            .search-shell .search-input-group input { flex: 1; border: none; padding: 0.9rem 1.4rem; font-size: 1rem; background: transparent; outline: none; }
            .search-shell .search-input-group button { border: none; background: transparent; color: #475467; padding: 0 1rem; font-size: 1.1rem; cursor: pointer; transition: color .2s ease; }
            .search-shell .search-input-group button:hover { color: #1d2939; }
            .search-shell .btn-primary { background: linear-gradient(120deg, #2563eb, #1d4ed8); border: none; color: #fff; border-radius: 999px; padding: 0.9rem 1.8rem; font-weight: 600; box-shadow: 0 15px 35px rgba(37,99,235,0.35); transition: transform .2s ease, box-shadow .2s ease; }
            .search-shell .btn-primary:disabled { opacity: .6; cursor: not-allowed; box-shadow: none; }
            .search-shell .btn-primary:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 18px 40px rgba(37,99,235,0.45); }
            .search-shell .filter-group { min-width: 220px; }
            .search-shell label { font-size: .85rem; font-weight: 600; text-transform: uppercase; letter-spacing: .08em; color: #475467; display: block; margin-bottom: .45rem; }
            .search-shell select { width: 100%; border: 1px solid #d0d5dd; border-radius: 0.85rem; padding: 0.75rem 1rem; font-size: .95rem; background: #fff url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3e%3cpath d='M6 8l4 4 4-4' stroke='%2394a3b8' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3e%3c/svg%3e") no-repeat calc(100% - 1rem) center / 1rem; appearance: none; }
            .search-shell .result-header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1rem; padding: 1.25rem 1.5rem; border: 1px solid #e4e7ec; border-radius: 1rem; background: #f8fafc; align-items: center; }
            .search-shell .result-title { font-size: 1.15rem; font-weight: 600; color: #0f172a; }
            .search-shell .result-summary { color: #475467; font-size: .95rem; display: flex; flex-wrap: wrap; gap: .65rem; align-items: center; }
            .search-shell .result-summary span strong { color: #1d4ed8; }
            .search-shell .status-note { font-size: .92rem; color: #475467; margin-top: .35rem; }
            .search-shell .results-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
            .search-shell .result-panel { border: 1px solid #e4e7ec; border-radius: 1.25rem; background: #fff; box-shadow: 0 25px 60px rgba(15,23,42,0.07); display: flex; flex-direction: column; min-height: 420px; }
            .search-shell .panel-heading { padding: 1.25rem 1.75rem 0.75rem; border-bottom: 1px solid #f2f4f7; }
            .search-shell .panel-heading h2 { font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: .25rem; }
            .search-shell .panel-heading p { margin: 0; font-size: .9rem; color: #475467; }
            .search-shell .panel-body { flex: 1; padding: 1.25rem 1.75rem 1rem; display: flex; flex-direction: column; gap: 1rem; }
            .search-shell .panel-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 1rem; }
            .search-shell .panel-card { border: 1px solid #f2f4f7; border-radius: 1rem; padding: 1rem; display: flex; gap: 1rem; transition: border-color .2s ease, transform .2s ease; }
            .search-shell .panel-card:hover { border-color: #c7d7fe; transform: translateY(-2px); }
            .search-shell .panel-card img { width: 72px; height: 72px; border-radius: 18px; object-fit: cover; background: #f1f5f9; }
            .search-shell .panel-card h3 { font-size: 1rem; margin: 0; }
            .search-shell .panel-card h3 a { color: inherit; text-decoration: none; }
            .search-shell .panel-card h3 a:hover { color: #1d4ed8; }
            .search-shell .role-pill { display: inline-flex; align-items: center; gap: .35rem; background: #eef2ff; color: #4338ca; border-radius: 999px; padding: .1rem .65rem; font-size: .8rem; font-weight: 600; }
            .search-shell .meta { font-size: .88rem; color: #475467; margin-top: .35rem; }
            .search-shell .case-list { margin-top: .65rem; padding-left: 1rem; color: #475467; font-size: .9rem; }
            .search-shell .doc-metadata { display: flex; flex-direction: column; gap: .2rem; font-size: .9rem; color: #475467; }
            .search-shell .doc-number { font-weight: 700; color: #1f2937; }
            .search-shell .doc-actions { margin-top: .7rem; display: flex; gap: .6rem; flex-wrap: wrap; }
            .search-shell .download-btn { border-radius: 0.85rem; border: 1px solid #c7d7fe; background: #e0edff; color: #1d4ed8; padding: 0.45rem 0.9rem; font-weight: 600; font-size: .9rem; text-decoration: none; transition: background .2s ease, color .2s ease; }
            .search-shell .download-btn:hover { background: #c7d7fe; color: #102a74; }
            .search-shell .skeleton-list { display: flex; flex-direction: column; gap: 1rem; }
            .search-shell .skeleton-card { border-radius: 1rem; background: #f8fafc; height: 96px; position: relative; overflow: hidden; }
            .search-shell .skeleton-card::after { content: ""; position: absolute; inset: 0; background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(226,232,240,0.9) 50%, rgba(255,255,255,0) 100%); animation: shimmer 1.4s infinite; }
            .search-shell .empty-state { text-align: center; color: #94a3b8; padding: 1.5rem 1rem; border: 1px dashed #cbd5f5; border-radius: 1rem; background: #f8fafc; }
            .search-shell .panel-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid #f2f4f7; padding: 0.9rem 1.75rem; }
            .search-shell .panel-pagination button { border-radius: 999px; border: 1px solid #cbd5f5; background: #eef2ff; color: #1d4ed8; font-weight: 600; padding: 0.45rem 1.3rem; transition: transform .2s ease; }
            .search-shell .panel-pagination button[disabled] { opacity: .5; cursor: not-allowed; transform: none; }
            .search-shell .panel-pagination button:not([disabled]):hover { transform: translateY(-1px); }
            .search-shell .alert { border-radius: 0.85rem; padding: 0.75rem 1rem; font-size: .9rem; border: 1px solid transparent; }
            .search-shell .alert-danger { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
            @media (max-width: 640px) {
                .search-shell .search-card { padding: 1.5rem; }
                .search-shell .search-input-group { flex-direction: column; border-radius: 1rem; }
                .search-shell .search-input-group button.clear-btn { align-self: flex-end; }
                .search-shell .btn-primary { width: 100%; }
            }
            @keyframes shimmer { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        </style>

        <div class="search-card">
            <form data-search-form class="search-toolbar" autocomplete="off">
                <div class="search-input-group" data-search-input>
                    <input
                        type="search"
                        name="q"
                        id="search-query"
                        placeholder="Cari nama, nomor LP, atau judul dokumen (min 2 karakter)"
                        maxlength="80"
                        aria-label="Kata kunci pencarian"
                    />
                    <button type="button" class="clear-btn" data-action="clear" aria-label="Hapus pencarian">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="filter-group">
                    <label for="doc-type-select">Tipe Dokumen</label>
                    <select name="doc_type" id="doc-type-select">
                        @foreach ($docTypeOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary" data-action="submit">
                    Cari
                </button>
            </form>
        </div>

        <div class="alert alert-danger" data-search-error role="alert" hidden></div>

        <div class="result-header">
            <div>
                <div class="result-title">
                    Hasil Pencarian untuk:
                    <span data-query-label>—</span>
                </div>
                <div class="status-note" data-status-note>
                    Ketik minimal 2 karakter untuk mulai mencari.
                </div>
            </div>
            <div class="result-summary">
                <span><strong data-people-count>0</strong> Tersangka/Penyidik ditemukan</span>
                <span>|</span>
                <span><strong data-doc-count>0</strong> Berita Acara ditemukan</span>
            </div>
        </div>

        <div class="results-grid">
            <div class="result-panel" data-panel="people">
                <div class="panel-heading">
                    <h2>Tersangka/Penyidik Terkait</h2>
                    <p data-people-caption>Menunggu pencarian...</p>
                </div>
                <div class="panel-body">
                    <div class="skeleton-list" data-people-skeleton hidden>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                    <ul class="panel-list" data-people-results></ul>
                    <div class="empty-state" data-people-empty hidden>
                        Tidak ada tersangka atau penyidik yang cocok dengan kata kunci ini.
                    </div>
                </div>
                <div class="panel-pagination" data-people-pagination hidden>
                    <button type="button" data-people-prev>&lsaquo; Sebelumnya</button>
                    <div data-people-page>Halaman 1</div>
                    <button type="button" data-people-next>Selanjutnya &rsaquo;</button>
                </div>
            </div>

            <div class="result-panel" data-panel="documents">
                <div class="panel-heading">
                    <h2>Berita Acara Terkait</h2>
                    <p data-doc-caption>Menunggu pencarian...</p>
                </div>
                <div class="panel-body">
                    <div class="skeleton-list" data-doc-skeleton hidden>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                        <div class="skeleton-card"></div>
                    </div>
                    <ul class="panel-list" data-doc-results></ul>
                    <div class="empty-state" data-doc-empty hidden>
                        Tidak ada berita acara yang cocok dengan pencarian ini.
                    </div>
                </div>
                <div class="panel-pagination" data-doc-pagination hidden>
                    <button type="button" data-doc-prev>&lsaquo; Sebelumnya</button>
                    <div data-doc-page>Halaman 1</div>
                    <button type="button" data-doc-next>Selanjutnya &rsaquo;</button>
                </div>
            </div>
        </div>
    </section>

    @push('scripts')
        <script>
            (() => {
                const root = document.querySelector('[data-search-root]');
                if (!root) return;

                const state = {
                    q: '',
                    doc_type: 'all',
                    page_people: 1,
                    page_docs: 1,
                };

                const perPage = {
                    people: 6,
                    documents: 6,
                };

                const selectors = {
                    form: '[data-search-form]',
                    queryInput: '#search-query',
                    docType: '#doc-type-select',
                    clearButton: '[data-action="clear"]',
                    submitButton: '[data-action="submit"]',
                    statusNote: '[data-status-note]',
                    queryLabel: '[data-query-label]',
                    errorBox: '[data-search-error]',
                    peopleSkeleton: '[data-people-skeleton]',
                    docSkeleton: '[data-doc-skeleton]',
                    peopleResults: '[data-people-results]',
                    docResults: '[data-doc-results]',
                    peopleEmpty: '[data-people-empty]',
                    docEmpty: '[data-doc-empty]',
                    peopleCaption: '[data-people-caption]',
                    docCaption: '[data-doc-caption]',
                    peoplePagination: '[data-people-pagination]',
                    docPagination: '[data-doc-pagination]',
                    peoplePrev: '[data-people-prev]',
                    peopleNext: '[data-people-next]',
                    docPrev: '[data-doc-prev]',
                    docNext: '[data-doc-next]',
                    peoplePageLabel: '[data-people-page]',
                    docPageLabel: '[data-doc-page]',
                    peopleCount: '[data-people-count]',
                    docCount: '[data-doc-count]',
                };

                const elements = Object.fromEntries(
                    Object.entries(selectors).map(([key, selector]) => [key, root.querySelector(selector)])
                );

                const apiEndpoint = root.dataset.apiEndpoint || '/database/search';
                let activeController = null;

                const formatNumber = (value) =>
                    new Intl.NumberFormat('id-ID').format(Number(value || 0));

                const formatDate = (dateString) => {
                    if (!dateString) return '-';
                    try {
                        return new Intl.DateTimeFormat('id-ID', {
                            dateStyle: 'long',
                        }).format(new Date(dateString));
                    } catch (error) {
                        return dateString;
                    }
                };

                const setText = (el, text) => {
                    if (el) el.textContent = text;
                };

                const toggleHidden = (el, hidden) => {
                    if (!el) return;
                    el.hidden = hidden;
                };

                const showError = (message) => {
                    if (!elements.errorBox) return;
                    const hasMessage = Boolean(message);
                    elements.errorBox.textContent = message ?? '';
                    elements.errorBox.hidden = !hasMessage;
                };

                const updateQueryString = () => {
                    const params = new URLSearchParams();
                    const trimmed = state.q.trim();
                    if (trimmed) params.set('q', trimmed);
                    if (state.doc_type && state.doc_type !== 'all') {
                        params.set('doc_type', state.doc_type);
                    }
                    if (state.page_people > 1) {
                        params.set('page_people', String(state.page_people));
                    }
                    if (state.page_docs > 1) {
                        params.set('page_docs', String(state.page_docs));
                    }
                    const qs = params.toString();
                    const newUrl = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
                    window.history.replaceState({}, '', newUrl);
                };

                const readQueryString = () => {
                    const params = new URLSearchParams(window.location.search);
                    state.q = params.get('q') ?? '';
                    state.doc_type = params.get('doc_type') ?? 'all';
                    state.page_people = Number(params.get('page_people') ?? 1);
                    state.page_docs = Number(params.get('page_docs') ?? 1);
                    if (Number.isNaN(state.page_people) || state.page_people < 1) state.page_people = 1;
                    if (Number.isNaN(state.page_docs) || state.page_docs < 1) state.page_docs = 1;

                    if (elements.queryInput) elements.queryInput.value = state.q;
                    if (elements.docType) elements.docType.value = state.doc_type;
                    setText(elements.queryLabel, state.q ? `"${state.q}"` : '—');
                };

                const setStatusNote = (text) => {
                    setText(elements.statusNote, text);
                };

                const toggleLoading = (panel, isLoading) => {
                    const skeletonEl = panel === 'people' ? elements.peopleSkeleton : elements.docSkeleton;
                    toggleHidden(skeletonEl, !isLoading);
                };

                const resetPanel = (panel) => {
                    const listEl = panel === 'people' ? elements.peopleResults : elements.docResults;
                    const emptyEl = panel === 'people' ? elements.peopleEmpty : elements.docEmpty;
                    toggleHidden(emptyEl, true);
                    if (listEl) listEl.innerHTML = '';
                };

                const updateCounts = (summary) => {
                    setText(elements.peopleCount, formatNumber(summary?.people_total ?? 0));
                    setText(elements.docCount, formatNumber(summary?.documents_total ?? 0));
                };

                const setPagination = (panel, pagination = null) => {
                    const paginationEl = panel === 'people' ? elements.peoplePagination : elements.docPagination;
                    const prevBtn = panel === 'people' ? elements.peoplePrev : elements.docPrev;
                    const nextBtn = panel === 'people' ? elements.peopleNext : elements.docNext;
                    const label = panel === 'people' ? elements.peoplePageLabel : elements.docPageLabel;
                    const stateKey = panel === 'people' ? 'page_people' : 'page_docs';

                    if (!paginationEl || !pagination) {
                        toggleHidden(paginationEl, true);
                        return;
                    }

                    toggleHidden(paginationEl, false);
                    if (label) {
                        setText(label, `Halaman ${pagination.page} dari ${pagination.last_page}`);
                    }
                    if (prevBtn) prevBtn.disabled = pagination.page <= 1;
                    if (nextBtn) nextBtn.disabled = pagination.page >= pagination.last_page;
                    state[stateKey] = pagination.page;
                };

                const renderPeople = (peoplePayload) => {
                    resetPanel('people');
                    const listEl = elements.peopleResults;
                    if (!listEl) return;

                    const rows = Array.isArray(peoplePayload?.data) ? peoplePayload.data : [];
                    if (!rows.length) {
                        toggleHidden(elements.peopleEmpty, false);
                        setText(
                            elements.peopleCaption,
                            'Tidak ditemukan penyidik atau permintaan pengujian untuk kata kunci ini.'
                        );
                    } else {
                        setText(
                            elements.peopleCaption,
                            `${formatNumber(peoplePayload.pagination?.total ?? rows.length)} orang terkait`
                        );
                        rows.forEach((person) => {
                            const item = document.createElement('li');
                            item.className = 'panel-card';

                            const content = document.createElement('div');
                            content.style.flex = '1';

                            const title = document.createElement('h3');
                            if (person.detail_url) {
                                const link = document.createElement('a');
                                link.href = person.detail_url;
                                link.textContent = person.name || 'Tanpa nama';
                                link.rel = 'noopener noreferrer';
                                title.appendChild(link);
                            } else {
                                title.textContent = person.name || 'Tanpa nama';
                            }
                            content.appendChild(title);

                            if (person.role_label) {
                                const role = document.createElement('span');
                                role.className = 'role-pill';
                                role.textContent = person.role_label;
                                content.appendChild(role);
                            }

                            if (person.subtitle) {
                                const subtitle = document.createElement('div');
                                subtitle.className = 'meta';
                                subtitle.textContent = person.subtitle;
                                content.appendChild(subtitle);
                            }

                            if (person.type === 'investigator') {
                                if (Array.isArray(person.test_requests) && person.test_requests.length) {
                                    const list = document.createElement('ul');
                                    list.className = 'case-list';
                                    person.test_requests.forEach((request) => {
                                        const li = document.createElement('li');
                                        const number = request.request_number
                                            ? `Permintaan #${request.request_number}`
                                            : 'Permintaan';
                                        const suspect = request.suspect_name
                                            ? ` — ${request.suspect_name}`
                                            : '';
                                        li.textContent = `${number}${suspect}`;
                                        list.appendChild(li);
                                    });
                                    content.appendChild(list);
                                } else {
                                    const empty = document.createElement('div');
                                    empty.className = 'meta';
                                    empty.textContent = 'Belum ada permintaan yang tercatat.';
                                    content.appendChild(empty);
                                }
                            } else {
                                const details = document.createElement('div');
                                details.className = 'doc-metadata';
                                if (person.request_number) {
                                    const numberRow = document.createElement('div');
                                    numberRow.textContent = `Nomor Permintaan: ${person.request_number}`;
                                    details.appendChild(numberRow);
                                }
                                if (person.investigator) {
                                    const invRow = document.createElement('div');
                                    invRow.textContent = `Penyidik: ${person.investigator}`;
                                    details.appendChild(invRow);
                                }
                                if (details.childElementCount) {
                                    content.appendChild(details);
                                }
                            }

                            if (person.created_at) {
                                const timeRow = document.createElement('div');
                                timeRow.className = 'meta';
                                timeRow.textContent = `Diperbarui: ${formatDate(person.created_at)}`;
                                content.appendChild(timeRow);
                            }

                            item.appendChild(content);
                            listEl.appendChild(item);
                        });
                    }

                    setPagination('people', peoplePayload?.pagination);
                };

                const renderDocuments = (documentsPayload) => {
                    resetPanel('documents');
                    const listEl = elements.docResults;
                    if (!listEl) return;

                    const rows = Array.isArray(documentsPayload?.data) ? documentsPayload.data : [];
                    if (!rows.length) {
                        toggleHidden(elements.docEmpty, false);
                        setText(
                            elements.docCaption,
                            'Tidak ada dokumen untuk kata kunci ini.'
                        );
                    } else {
                        setText(
                            elements.docCaption,
                            `${formatNumber(documentsPayload.pagination?.total ?? rows.length)} dokumen`
                        );
                        rows.forEach((doc) => {
                            const item = document.createElement('li');
                            item.className = 'panel-card';

                            const content = document.createElement('div');
                            content.style.flex = '1';

                            const title = document.createElement('h3');
                            title.textContent = doc.document_type_label || doc.name || 'Dokumen';
                            content.appendChild(title);

                            const meta = document.createElement('div');
                            meta.className = 'doc-metadata';
                            if (doc.name) {
                                const nameRow = document.createElement('div');
                                nameRow.textContent = doc.name;
                                meta.appendChild(nameRow);
                            }
                            if (doc.request_number) {
                                const requestRow = document.createElement('div');
                                requestRow.textContent = `Nomor Permintaan: ${doc.request_number}`;
                                meta.appendChild(requestRow);
                            }
                            if (doc.suspect_name) {
                                const suspectRow = document.createElement('div');
                                suspectRow.textContent = `Tersangka: ${doc.suspect_name}`;
                                meta.appendChild(suspectRow);
                            }
                            if (doc.investigator_name) {
                                const investigatorRow = document.createElement('div');
                                investigatorRow.textContent = `Penyidik: ${doc.investigator_name}`;
                                meta.appendChild(investigatorRow);
                            }
                            if (doc.created_at) {
                                const dateRow = document.createElement('div');
                                dateRow.textContent = `Dibuat: ${formatDate(doc.created_at)}`;
                                meta.appendChild(dateRow);
                            }
                            content.appendChild(meta);

                            const actions = document.createElement('div');
                            actions.className = 'doc-actions';
                            if (doc.preview_url) {
                                const preview = document.createElement('a');
                                preview.className = 'download-btn';
                                preview.href = doc.preview_url;
                                preview.textContent = 'Lihat Dokumen';
                                preview.target = '_blank';
                                preview.rel = 'noopener noreferrer';
                                actions.appendChild(preview);
                            }
                            if (doc.download_url) {
                                const download = document.createElement('a');
                                download.className = 'download-btn';
                                download.href = doc.download_url;
                                download.textContent = 'Unduh Dokumen';
                                download.rel = 'noopener noreferrer';
                                actions.appendChild(download);
                            }
                            content.appendChild(actions);

                            item.appendChild(content);
                            listEl.appendChild(item);
                        });
                    }

                    setPagination('documents', documentsPayload?.pagination);
                };

                const errorCopy = (status) => {
                    const mapping = {
                        401: 'Sesi berakhir. Silakan masuk kembali untuk melanjutkan pencarian.',
                        403: 'Anda tidak memiliki hak akses untuk melihat hasil pencarian ini.',
                        429: 'Terlalu banyak permintaan. Coba ulang beberapa saat lagi.',
                        500: 'Terjadi kesalahan pada server. Tim kami sudah diberitahu.',
                    };
                    return mapping[status] ?? 'Gagal memuat data pencarian. Coba ulang beberapa saat lagi.';
                };

                const fetchResults = async () => {
                    const trimmed = state.q.trim();
                    setText(elements.queryLabel, trimmed ? `"${trimmed}"` : '—');
                    updateQueryString();

                    if (trimmed.length < 2) {
                        showError('');
                        resetPanel('people');
                        resetPanel('documents');
                        toggleHidden(elements.peopleEmpty, true);
                        toggleHidden(elements.docEmpty, true);
                        setStatusNote('Ketik minimal 2 karakter untuk mulai mencari.');
                        setText(elements.peopleCaption, 'Menunggu pencarian...');
                        setText(elements.docCaption, 'Menunggu pencarian...');
                        updateCounts({ people_total: 0, documents_total: 0 });
                        toggleLoading('people', false);
                        toggleLoading('documents', false);
                        return;
                    }

                    showError('');
                    setStatusNote(`Sedang mencari data untuk "${trimmed}"...`);
                    toggleLoading('people', true);
                    toggleLoading('documents', true);
                    elements.submitButton?.setAttribute('disabled', 'disabled');

                    if (activeController) {
                        activeController.abort();
                    }
                    activeController = new AbortController();

                    const params = new URLSearchParams({
                        q: trimmed,
                        doc_type: state.doc_type || 'all',
                        page_people: String(state.page_people),
                        per_page_people: String(perPage.people),
                        page_docs: String(state.page_docs),
                        per_page_docs: String(perPage.documents),
                    });

                    try {
                        const response = await fetch(`${apiEndpoint}?${params.toString()}`, {
                            headers: {
                                Accept: 'application/json',
                            },
                            credentials: 'same-origin',
                            signal: activeController.signal,
                        });

                        if (!response.ok) {
                            let message = errorCopy(response.status);
                            try {
                                const data = await response.json();
                                if (data?.message) {
                                    message = data.message;
                                }
                            } catch (error) {
                                // ignore json error
                            }
                            throw new Error(message);
                        }

                        const payload = await response.json();
                        updateCounts(payload.summary);
                        renderPeople(payload.people);
                        renderDocuments(payload.documents);
                        setStatusNote(`Menampilkan hasil untuk "${payload.query ?? trimmed}".`);
                        showError('');
                    } catch (error) {
                        if (error.name === 'AbortError') return;
                        showError(error.message || 'Terjadi kesalahan saat memuat data.');
                        resetPanel('people');
                        resetPanel('documents');
                        toggleHidden(elements.peopleEmpty, false);
                        toggleHidden(elements.docEmpty, false);
                        setStatusNote('Tidak dapat memuat data. Coba ulang beberapa saat lagi.');
                    } finally {
                        toggleLoading('people', false);
                        toggleLoading('documents', false);
                        elements.submitButton?.removeAttribute('disabled');
                    }
                };

                const debounce = (fn, delay = 400) => {
                    let timer;
                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => fn.apply(null, args), delay);
                    };
                };

                const debouncedFetch = debounce(() => {
                    state.page_people = 1;
                    state.page_docs = 1;
                    fetchResults();
                }, 400);

                const attachEvents = () => {
                    elements.form?.addEventListener('submit', (event) => {
                        event.preventDefault();
                        state.page_people = 1;
                        state.page_docs = 1;
                        fetchResults();
                    });

                    elements.queryInput?.addEventListener('input', (event) => {
                        state.q = event.target.value;
                        debouncedFetch();
                    });

                    elements.docType?.addEventListener('change', (event) => {
                        state.doc_type = event.target.value || 'all';
                        state.page_people = 1;
                        state.page_docs = 1;
                        fetchResults();
                    });

                    elements.clearButton?.addEventListener('click', () => {
                        if (elements.queryInput) {
                            elements.queryInput.value = '';
                            state.q = '';
                            elements.queryInput.focus();
                        }
                        state.page_people = 1;
                        state.page_docs = 1;
                        fetchResults();
                    });

                    elements.peoplePrev?.addEventListener('click', () => {
                        if (state.page_people > 1) {
                            state.page_people -= 1;
                            fetchResults();
                        }
                    });

                    elements.peopleNext?.addEventListener('click', () => {
                        state.page_people += 1;
                        fetchResults();
                    });

                    elements.docPrev?.addEventListener('click', () => {
                        if (state.page_docs > 1) {
                            state.page_docs -= 1;
                            fetchResults();
                        }
                    });

                    elements.docNext?.addEventListener('click', () => {
                        state.page_docs += 1;
                        fetchResults();
                    });

                    window.addEventListener('popstate', () => {
                        readQueryString();
                        fetchResults();
                    });
                };

                readQueryString();
                attachEvents();
                if (state.q.trim().length >= 2) {
                    fetchResults();
                } else {
                    setStatusNote('Ketik minimal 2 karakter untuk mulai mencari.');
                }
            })();
        </script>
    @endpush
</x-app-layout>
