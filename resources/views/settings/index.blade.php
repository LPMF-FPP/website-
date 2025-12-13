<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pengaturan LIMS"
            :breadcrumbs="[['label' => 'Settings']]"
            description="Kelola penomoran, template dokumen, branding, otomasi, dan keamanan."
        />
    </x-slot>

    @php
        $initialSettings = $settings ?? [];
        // Prefill numbering patterns from provided settings (mapping legacy keys)
        $prefillSample = data_get($initialSettings, 'numbering.sample.pattern');
        $prefillBA = data_get($initialSettings, 'numbering.berita_acara.pattern');
        $prefillLHU = data_get($initialSettings, 'numbering.lhu.pattern');

        // Map to the UI scopes used by Alpine component
        if ($prefillSample) {
            data_set($initialSettings, 'numbering.sample_code.pattern', $prefillSample);
        }
        if ($prefillBA) {
            data_set($initialSettings, 'numbering.ba.pattern', $prefillBA);
        }
        if ($prefillLHU) {
            data_set($initialSettings, 'numbering.lhu.pattern', $prefillLHU);
        }

        $initialRoles = data_get($initialSettings, 'security.roles', []);
        $initialManageRoles = data_get($initialRoles, 'can_manage_settings', []);
        $initialIssueRoles = data_get($initialRoles, 'can_issue_number', []);
        // Initial server-side preview for current time based on timezone and date_format
        $tz = data_get($initialSettings, 'locale.timezone', 'Asia/Jakarta');
        $fmtTok = data_get($initialSettings, 'locale.date_format', 'DD/MM/YYYY');
        $phpFmtMap = [
            'DD/MM/YYYY' => 'd/m/Y',
            'YYYY-MM-DD' => 'Y-m-d',
            'DD-MM-YYYY' => 'd-m-Y',
        ];
        $phpFmt = $phpFmtMap[$fmtTok] ?? 'd/m/Y';
        $initialNowPreview = \Carbon\Carbon::now($tz)->format($phpFmt . ' H:i:s');
    @endphp

    <div x-data="settingsPage" x-init="init()" class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <div class="p-6 border-b border-primary-100 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-primary-900">Penomoran Otomatis</h2>
                    <p class="text-sm text-primary-600">Atur pola penomoran untuk Sample, BA, dan LHU.</p>
                </div>
                <button type="button"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 text-white px-4 py-2 text-sm hover:bg-primary-700"
                        @click="testGenerate('sample_code')">
                    <span>Test Generate</span>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <template x-for="scope in ['sample_code','ba','lhu']" :key="scope">
                    <div class="bg-primary-25 border border-primary-100 rounded-xl p-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h3 class="text-base font-semibold text-primary-900" x-text="labels[scope]"></h3>
                                <p class="text-xs text-primary-500">Pattern: <span class="font-mono" x-text="form.numbering[scope].pattern"></span></p>
                            </div>
                            <div class="flex items-center gap-2 text-sm">
                                <label class="flex items-center gap-2">
                                    <span>Reset</span>
                                    <select x-model="form.numbering[scope].reset" class="input-select">
                                        <option value="never">Tidak Pernah</option>
                                        <option value="yearly">Tahunan</option>
                                        <option value="monthly">Bulanan</option>
                                        <option value="daily">Harian</option>
                                    </select>
                                </label>
                                <label class="flex items-center gap-2">
                                    <span>Mulai</span>
                                    <input type="number" min="1" class="input" x-model.number="form.numbering[scope].start_from">
                                </label>
                                <button type="button" class="btn-secondary" @click="preview(scope)">Preview</button>
                            </div>
                        </div>
                        <label class="block mt-3">
                            <span class="text-sm text-primary-600">Pattern</span>
                            <input class="input" x-model="form.numbering[scope].pattern">
                        </label>
                        <div class="mt-2 text-xs text-primary-500" x-show="previews[scope]">
                            Contoh hasil: <span class="font-mono text-primary-800" x-text="previews[scope]"></span>
                        </div>
                    </div>
                </template>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-primary-100 bg-primary-25/40 px-6 py-4">
                <div
                    class="text-sm font-medium"
                    :class="sectionFlashIntent.numbering === 'error' ? 'text-red-600' : 'text-primary-700'"
                    x-show="sectionFlash.numbering"
                    x-transition
                    x-text="sectionFlash.numbering"
                ></div>
                <button type="button" class="btn-primary" @click="save('numbering')">
                    Simpan Bagian Ini
                </button>
            </div>
        </div>

        <section class="bg-white shadow-sm sm:rounded-lg">
            <header class="p-6 border-b border-primary-100">
                <h2 class="text-lg font-semibold text-primary-900">Template Dokumen</h2>
                <p class="text-sm text-primary-600">Upload dan aktifkan template BA, LHU, dan dokumen pendukung.</p>
            </header>
            <div class="p-6 space-y-4">
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1 space-y-3">
                        <label class="block">
                            <span class="text-sm text-primary-600">Kode Template</span>
                            <input x-model="templateForm.code" class="input" placeholder="LHU_STD">
                        </label>
                        <label class="block">
                            <span class="text-sm text-primary-600">Nama Template</span>
                            <input x-model="templateForm.name" class="input" placeholder="Template LHU Standar">
                        </label>
                        <input type="file" @change="onTemplateFile($event)">
                        <button class="btn-primary" @click="uploadTemplate">Upload</button>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-primary-700 mb-2">Template Aktif</h3>
                        <template x-for="(code, type) in form.templates.active" :key="type">
                            <div class="flex items-center justify-between bg-primary-25 border border-primary-100 rounded-lg px-3 py-2 mb-2">
                                <div>
                                    <div class="text-sm font-medium text-primary-900" x-text="type"></div>
                                    <div class="text-xs text-primary-500" x-text="code || 'Belum dipilih'"></div>
                                </div>
                                <button class="btn-secondary" @click="openActivation(type)">Pilih Template</button>
                            </div>
                        </template>
                    </div>
                </div>
                <div class="border-t border-primary-100 pt-4">
                    <h3 class="text-sm font-semibold text-primary-700 mb-2">Daftar Template</h3>
                    <div class="grid md:grid-cols-2 gap-3">
                        <template x-for="tpl in templates" :key="tpl.id">
                            <div class="border border-primary-100 rounded-lg p-3">
                                <div class="text-sm font-medium text-primary-900" x-text="tpl.name"></div>
                                <div class="text-xs text-primary-500" x-text="tpl.code"></div>
                                <button class="btn-secondary mt-2" @click="activate(tpl.code)">Aktifkan</button>
                            </div>
                        </template>
                        <p x-show="!templates.length" class="text-sm text-primary-500">Belum ada template diunggah.</p>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-primary-100 bg-primary-25/40 px-6 py-4">
                <div
                    class="text-sm font-medium"
                    :class="sectionFlashIntent.templates === 'error' ? 'text-red-600' : 'text-primary-700'"
                    x-show="sectionFlash.templates"
                    x-transition
                    x-text="sectionFlash.templates"
                ></div>
                <button type="button" class="btn-primary" @click="save('templates')">
                    Simpan Bagian Ini
                </button>
            </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
            <header class="p-6 border-b border-primary-100">
                <h2 class="text-lg font-semibold text-primary-900">Branding &amp; PDF</h2>
                <p class="text-sm text-primary-600">Atur identitas, alamat, watermark, dan tanda tangan.</p>
            </header>
            <div class="p-6 grid md:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-sm text-primary-600">Kode Lab</span>
                    <input class="input" x-model="form.branding.lab_code">
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Nama Instansi</span>
                    <input class="input" x-model="form.branding.org_name">
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Alamat</span>
                    <input class="input" x-model="form.pdf.header.address">
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Kontak</span>
                    <input class="input" x-model="form.pdf.header.contact">
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Preset Watermark</span>
                    <select class="input-select" x-model="form.pdf.header.watermark">
                        <option value="none">Tidak ada</option>
                        <option value="diagonal">Diagonal</option>
                        <option value="center">Tengah</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Footer</span>
                    <input class="input" x-model="form.pdf.footer.text">
                </label>
                <label class="inline-flex items-center gap-2 text-sm text-primary-700">
                    <input type="checkbox" x-model="form.pdf.qr.enabled">
                    <span>Tampilkan QR pada PDF</span>
                </label>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-primary-100 bg-primary-25/40 px-6 py-4">
                <div
                    class="text-sm font-medium"
                    :class="sectionFlashIntent.branding === 'error' ? 'text-red-600' : 'text-primary-700'"
                    x-show="sectionFlash.branding"
                    x-transition
                    x-text="sectionFlash.branding"
                ></div>
                <button type="button" class="btn-primary" @click="save('branding')">
                    Simpan Bagian Ini
                </button>
            </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
            <header class="p-6 border-b border-primary-100">
                <h2 class="text-lg font-semibold text-primary-900">Lokalisasi &amp; Retensi</h2>
            </header>
            <div class="p-6 space-y-4">
                <div class="text-sm text-primary-700">
                    <span>Sekarang di </span>
                    <span class="font-medium" x-text="form.locale.timezone || 'Asia/Jakarta'"></span>
                    <span>: </span>
                    <span class="font-mono text-primary-900" x-text="nowPreview || '{{ $initialNowPreview }}'"></span>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                <label class="block">
                    <span class="text-sm text-primary-600">Zona Waktu</span>
                    <select class="input-select" x-model="form.locale.timezone">
                        <template x-for="tz in timezones" :key="tz">
                            <option :value="tz" x-text="tz"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Format Tanggal</span>
                    <select class="input-select" x-model="form.locale.date_format">
                        <template x-for="fmt in dateFormats" :key="fmt">
                            <option :value="fmt" x-text="fmt"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Format Angka</span>
                    <select class="input-select" x-model="form.locale.number_format">
                        <template x-for="fmt in numberFormats" :key="fmt.value">
                            <option :value="fmt.value" x-text="fmt.label"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Bahasa</span>
                    <select class="input-select" x-model="form.locale.language">
                        <template x-for="lang in languages" :key="lang.value">
                            <option :value="lang.value" x-text="lang.label"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Driver Penyimpanan</span>
                    <select class="input-select" x-model="form.retention.storage_driver">
                        <template x-for="drv in storageDrivers" :key="drv">
                            <option :value="drv" x-text="drv"></option>
                        </template>
                    </select>
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Base Path</span>
                    <input class="input" x-model="form.retention.base_path">
                </label>
                <label class="block">
                    <span class="text-sm text-primary-600">Purge Setelah (hari)</span>
                    <input type="number" min="30" class="input" x-model.number="form.retention.purge_after_days">
                </label>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-primary-100 bg-primary-25/40 px-6 py-4">
                <div
                    class="text-sm font-medium"
                    :class="sectionFlashIntent.localization === 'error' ? 'text-red-600' : 'text-primary-700'"
                    x-show="sectionFlash.localization"
                    x-transition
                    x-text="sectionFlash.localization"
                ></div>
                <button type="button" class="btn-primary" @click="save('localization')">
                    Simpan Bagian Ini
                </button>
            </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
            <header class="p-6 border-b border-primary-100">
                <h2 class="text-lg font-semibold text-primary-900">Otomasi &amp; Keamanan</h2>
            </header>
            <div class="p-6 grid md:grid-cols-2 gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-primary-700 mb-2">Notifikasi</h3>
                    <label class="inline-flex items-center gap-2 text-sm text-primary-700">
                        <input type="checkbox" x-model="form.automation.notify_on_issue.email">
                        <span>Email</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-primary-700">
                        <input type="checkbox" x-model="form.automation.notify_on_issue.whatsapp">
                        <span>WhatsApp</span>
                    </label>
                    <div class="mt-3" x-show="form.automation.notify_on_issue.whatsapp">
                        <label class="block text-xs text-primary-600 mb-1">Nomor WhatsApp Tujuan</label>
                        <input
                            type="text"
                            x-model="form.automation.whatsapp_recipient"
                            placeholder="628123456789"
                            class="w-full px-3 py-1.5 text-sm border border-primary-300 rounded focus:outline-none focus:border-primary-500">
                        <p class="mt-1 text-xs text-primary-500">Format: 628xxxxxxxxxx (tanpa +, spasi, atau tanda hubung)</p>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-primary-700 mb-2">Role Akses</h3>
                    <div class="text-xs text-primary-600 mb-1">Boleh Kelola Settings</div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="r in availableRoles" :key="'manage_'+r">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" :value="r" x-model="roles.manageArr">
                                <span x-text="roleLabels[r] || r"></span>
                            </label>
                        </template>
                    </div>
                    <div class="text-xs text-primary-600 mt-3 mb-1">Boleh Issue Number</div>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="r in availableRoles" :key="'issue_'+r">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" :value="r" x-model="roles.issueArr">
                                <span x-text="roleLabels[r] || r"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3 border-t border-primary-100 bg-primary-25/40 px-6 py-4">
                <div
                    class="text-sm font-medium"
                    :class="sectionFlashIntent.automation === 'error' ? 'text-red-600' : 'text-primary-700'"
                    x-show="sectionFlash.automation"
                    x-transition
                    x-text="sectionFlash.automation"
                ></div>
                <button type="button" class="btn-primary" @click="save('automation')">
                    Simpan Bagian Ini
                </button>
            </div>
        </section>

        <div class="flex items-center justify-end gap-3 pb-10">
            <button class="btn-secondary" @click="init">Reset</button>
            <button class="btn-primary" @click="save()">Simpan Pengaturan</button>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            const initialForm = @js($initialSettings);
                const rawTemplates = @js($templates ?? []);
                const rawManageRoles = @js($initialManageRoles ?? []);
                const rawIssueRoles = @js($initialIssueRoles ?? []);
                const initialTemplates = Array.isArray(rawTemplates) ? rawTemplates : [];
                const initialManageRoles = Array.isArray(rawManageRoles) ? rawManageRoles : [];
                const initialIssueRoles = Array.isArray(rawIssueRoles) ? rawIssueRoles : [];
                const optionValues = @js($options ?? []);
                const timezoneOptions = Array.isArray(optionValues.timezones) ? optionValues.timezones : [];
                const dateFormatOptions = Array.isArray(optionValues.date_formats) ? optionValues.date_formats : [];
                const numberFormatOptions = Array.isArray(optionValues.number_formats) ? optionValues.number_formats : [];
                const languageOptions = Array.isArray(optionValues.languages) ? optionValues.languages : [];
                const storageDriverOptions = Array.isArray(optionValues.storage_drivers) ? optionValues.storage_drivers : [];

            Alpine.data('settingsPage', () => ({
                    CSRF: document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    labels: {
                        sample_code: 'Kode Sample',
                        ba: 'Berita Acara',
                        lhu: 'Laporan Hasil Uji',
                    },
                    sectionLabels: {
                        numbering: 'Penomoran Otomatis',
                        templates: 'Template Dokumen',
                        branding: 'Branding & PDF',
                        localization: 'Lokalisasi & Retensi',
                        automation: 'Otomasi & Keamanan',
                        default: 'Pengaturan',
                    },
                    sectionFlash: {},
                    sectionFlashIntent: {},
                    sectionFlashTimers: {},
                    timezones: timezoneOptions.length ? timezoneOptions : ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'UTC'],
                    dateFormats: dateFormatOptions.length ? dateFormatOptions : ['d/m/Y', 'Y-m-d', 'd F Y', 'd-m-Y', 'm/d/Y'],
                    numberFormats: (numberFormatOptions.length ? numberFormatOptions : ['1.234,56', '1,234.56'])
                        .map((fmt) => ({ value: fmt, label: fmt })),
                    languages: (languageOptions.length ? languageOptions : ['id', 'en'])
                        .map((code) => ({
                            value: code,
                            label: code === 'id' ? 'Bahasa Indonesia' : (code === 'en' ? 'English' : code.toUpperCase()),
                        })),
                    storageDrivers: storageDriverOptions.length ? storageDriverOptions : ['local', 'public', 's3'],
                    availableRoles: ['admin', 'supervisor', 'analyst', 'lab_analyst', 'petugas_lab'],
                    roleLabels: {
                        admin: 'Admin',
                        supervisor: 'Supervisor',
                        analyst: 'Analis',
                        lab_analyst: 'Petugas Lab (Analis)',
                        petugas_lab: 'Petugas Lab',
                    },
                    form: {},
                    previews: {},
                    templateForm: { code: '', name: '', file: null },
                    templates: initialTemplates,
                    roles: {
                        manageArr: [...initialManageRoles],
                        issueArr: [...initialIssueRoles],
                    },
                    nowPreview: '',
                    init() {
                        this.form = this.mergeDefaults(this.clone(initialForm));
                        this.templates = Array.isArray(initialTemplates) ? initialTemplates : [];
                        this.roles.manageArr = [...initialManageRoles];
                        this.roles.issueArr = [...initialIssueRoles];
                        this.ensureLocaleDefaults();
                        this.resetSectionFlash();
                        this.updateNowPreview();
                        // Refresh every 5 seconds for liveliness
                        setInterval(() => this.updateNowPreview(), 5000);
                        // Watch timezone and date_format changes
                        this.$watch('form.locale.timezone', () => this.updateNowPreview());
                        this.$watch('form.locale.date_format', () => this.updateNowPreview());
                    },
                    ensureLocaleDefaults() {
                        this.form.locale ??= {};
                        if (!this.form.locale.timezone) this.form.locale.timezone = this.timezones[0] ?? 'Asia/Jakarta';
                        if (!this.form.locale.date_format) this.form.locale.date_format = this.dateFormats[0] ?? 'd/m/Y';
                        if (!this.form.locale.number_format) this.form.locale.number_format = this.numberFormats[0]?.value ?? '1.234,56';
                        if (!this.form.locale.language) this.form.locale.language = this.languages[0]?.value ?? 'id';
                        this.form.retention ??= {};
                        if (!this.form.retention.storage_driver) this.form.retention.storage_driver = this.storageDrivers[0] ?? 'local';
                    },
                    mergeDefaults(form) {
                        form.numbering ??= {};
                        ['sample_code', 'ba', 'lhu'].forEach((scope) => {
                            form.numbering[scope] ??= { pattern: '', reset: 'never', start_from: 1 };
                            form.numbering[scope].pattern ??= '';
                            form.numbering[scope].reset ??= 'never';
                            form.numbering[scope].start_from ??= 1;
                        });
                        form.branding ??= {};
                        form.pdf ??= { header: {}, footer: {}, qr: {} };
                        form.locale ??= {};
                        form.retention ??= {};
                        form.automation ??= { notify_on_issue: { templates: {} } };
                        form.templates ??= { active: {} };
                        form.security ??= { roles: { can_manage_settings: [], can_issue_number: [] } };
                        return form;
                    },
                    refreshFromServer() {
                        fetch('{{ route('settings.show') }}', {
                            headers: { 'Accept': 'application/json' },
                        })
                            .then(this.assertOk)
                            .then((response) => response.json())
                            .then((data) => this.applyServerData(data));
                    },
                    applyServerData(data) {
                        if (data && data.settings) {
                            this.form = this.mergeDefaults(this.clone(data.settings));
                            this.ensureLocaleDefaults();
                        }
                        if (data && data.templates) {
                            const templateList = Array.isArray(data.templates.list) ? data.templates.list : this.templates;
                            this.templates = this.clone(templateList);
                            this.form.templates ??= {};
                            if (data.templates.active) {
                                this.form.templates.active = this.clone(data.templates.active);
                            }
                        }
                        if (data && data.security) {
                            if (Array.isArray(data.security.can_manage_settings)) {
                                this.roles.manageArr = [...data.security.can_manage_settings];
                            }
                            if (Array.isArray(data.security.can_issue_number)) {
                                this.roles.issueArr = [...data.security.can_issue_number];
                            }
                        }
                    },
                    assertOk(response) {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response;
                    },
                    preview(scope) {
                        this.postJson('{{ route('settings.preview') }}', {
                            scope,
                            config: this.formForSubmit(),
                        }).then((data) => {
                            this.previews[scope] = data.example ?? '';
                        }).catch(() => {
                            this.notify('error', 'Gagal memuat preview.');
                        });
                    },
                    testGenerate(scope = null) {
                        this.postJson('{{ route('settings.test') }}', { scope })
                            .then(() => {
                                this.notify('success', 'Test generate (sandbox) dicatat.');
                            })
                            .catch(() => {
                                this.notify('error', 'Gagal menjalankan test generate.');
                            });
                    },
                    save(sectionKey = null) {
                        const payload = this.formForSubmit();
                        if (sectionKey) {
                            payload.__section = sectionKey;
                        }
                        this.postJson('{{ route('settings.update') }}', payload)
                            .then(() => {
                                const label = sectionKey ? (this.sectionLabels[sectionKey] || sectionKey) : null;
                                this.notify('success', label ? `Pengaturan ${label} disimpan.` : 'Pengaturan disimpan.');
                                this.refreshFromServer();
                                if (sectionKey) {
                                    this.setSectionFlash(sectionKey, `Berhasil menyimpan ${label || 'pengaturan'}.`);
                                }
                            })
                            .catch(() => {
                                const label = sectionKey ? (this.sectionLabels[sectionKey] || sectionKey) : null;
                                this.notify('error', label ? `Gagal menyimpan pengaturan ${label}.` : 'Gagal menyimpan pengaturan.');
                                if (sectionKey) {
                                    this.setSectionFlash(sectionKey, `Gagal menyimpan ${label || 'pengaturan'}.`, 'error');
                                }
                            });
                    },
                    setSectionFlash(key, message, type = 'success') {
                        if (!key) return;
                        this.sectionFlash[key] = message;
                        this.sectionFlashIntent[key] = type;
                        if (this.sectionFlashTimers[key]) {
                            clearTimeout(this.sectionFlashTimers[key]);
                        }
                        this.sectionFlashTimers[key] = setTimeout(() => {
                            this.sectionFlash[key] = '';
                            this.sectionFlashIntent[key] = 'success';
                        }, 3200);
                    },
                    resetSectionFlash() {
                        this.sectionFlash = {
                            numbering: '',
                            templates: '',
                            branding: '',
                            localization: '',
                            automation: '',
                        };
                        this.sectionFlashIntent = {
                            numbering: 'success',
                            templates: 'success',
                            branding: 'success',
                            localization: 'success',
                            automation: 'success',
                        };
                    },
                    formForSubmit() {
                        const payload = this.mergeDefaults(this.clone(this.form));
                        payload.security ??= {};
                        payload.security.roles = {
                            can_manage_settings: [...this.roles.manageArr],
                            can_issue_number: [...this.roles.issueArr],
                        };
                        if (payload.templates?.list) {
                            delete payload.templates.list;
                        }
                        return payload;
                    },
                    postJson(url, body) {
                        return fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.CSRF,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(body),
                        })
                            .then(this.assertOk)
                            .then((res) => res.json());
                    },
                    notify(type, message) {
                        window.dispatchEvent(new CustomEvent('notify', { detail: { type, message } }));
                    },
                    jsDateFormat(fmtToken) {
                        // Map 'DD/MM/YYYY' | 'YYYY-MM-DD' | 'DD-MM-YYYY' to corresponding options
                        switch (fmtToken) {
                            case 'YYYY-MM-DD':
                                return { year: 'numeric', month: '2-digit', day: '2-digit', sep: '-' };
                            case 'DD-MM-YYYY':
                                return { year: 'numeric', month: '2-digit', day: '2-digit', sep: '-' };
                            case 'DD/MM/YYYY':
                            default:
                                return { year: 'numeric', month: '2-digit', day: '2-digit', sep: '/' };
                        }
                    },
                    formatWithTokens(date, timezone, fmtToken) {
                        // We use Intl.DateTimeFormat for parts to control ordering
                        const map = this.jsDateFormat(fmtToken);
                        const dtf = new Intl.DateTimeFormat(undefined, {
                            timeZone: timezone || 'Asia/Jakarta',
                            year: map.year,
                            month: map.month,
                            day: map.day,
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                            hour12: false,
                        });
                        const parts = dtf.formatToParts(date);
                        const obj = Object.fromEntries(parts.map(p => [p.type, p.value]));
                        // Order date based on token
                        let dateStr;
                        if (fmtToken === 'YYYY-MM-DD') {
                            dateStr = `${obj.year}-${obj.month}-${obj.day}`;
                        } else if (fmtToken === 'DD-MM-YYYY') {
                            dateStr = `${obj.day}-${obj.month}-${obj.year}`;
                        } else { // DD/MM/YYYY
                            dateStr = `${obj.day}/${obj.month}/${obj.year}`;
                        }
                        const timeStr = `${obj.hour}:${obj.minute}:${obj.second}`;
                        return `${dateStr} ${timeStr}`;
                    },
                    updateNowPreview() {
                        try {
                            const tz = this.form?.locale?.timezone || 'Asia/Jakarta';
                            const fmt = this.form?.locale?.date_format || 'DD/MM/YYYY';
                            const now = new Date();
                            this.nowPreview = this.formatWithTokens(now, tz, fmt);
                        } catch (e) {
                            // Fallback: leave previous value
                        }
                    },
                    onTemplateFile(event) {
                        this.templateForm.file = event.target.files[0];
                    },
                    uploadTemplate() {
                        if (!this.templateForm.file) {
                            return;
                        }
                        const formData = new FormData();
                        formData.append('code', this.templateForm.code);
                        formData.append('name', this.templateForm.name);
                        formData.append('file', this.templateForm.file);
                        axios.post('{{ route('settings.templates.store') }}', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
                            .then(() => {
                                this.refreshFromServer();
                                this.templateForm = { code: '', name: '', file: null };
                            });
                    },
                    activate(code) {
                        axios.post('{{ route('settings.templates.activate') }}', { code, type: 'LHU' })
                            .then(({ data }) => {
                                this.form.templates.active = data;
                            });
                    },
                    openActivation(type) {
                        const code = prompt(`Masukkan kode template untuk ${type}`, this.form.templates.active[type] || '');
                        if (code !== null) {
                            axios.post('{{ route('settings.templates.activate') }}', { type, code })
                                .then(({ data }) => {
                                    this.form.templates.active = data;
                                });
                        }
                    },
                    clone(value) {
                        return JSON.parse(JSON.stringify(value ?? {}));
                    },
            }));
        });

        // Optional progressive enhancement: submit #settings-form as JSON by converting
        // bracket-notation keys into a nested object and pruning empty arrays/objects
        (function () {
            function parseKey(key) {
                // Converts a[b][c] and a[b][] to ["a","b","c"] or ["a","b",""]
                const segments = [];
                const first = key.split('[')[0];
                segments.push(first);
                const bracketRegex = /\[([^\]]*)\]/g;
                let match;
                while ((match = bracketRegex.exec(key)) !== null) {
                    segments.push(match[1]); // empty string for [] denotes push
                }
                return segments;
            }

            function setDeep(target, path, value) {
                let cur = target;
                for (let i = 0; i < path.length; i++) {
                    const seg = path[i];
                    const isLast = i === path.length - 1;
                    const nextSeg = path[i + 1];

                    if (isLast) {
                        if (seg === '') {
                            // push to current array
                            if (!Array.isArray(cur)) return; // invalid path
                            cur.push(value);
                        } else {
                            if (Array.isArray(cur)) {
                                const idx = isFinite(seg) ? Number(seg) : cur.length;
                                cur[idx] = value;
                            } else {
                                cur[seg] = value;
                            }
                        }
                        return;
                    }

                    // Non-last: prepare container for next segment
                    if (seg === '') {
                        // Ensure cur is array
                        if (!Array.isArray(cur)) {
                            // Convert current object into array if possible
                            // If cur is object without numeric keys, create array
                            const arr = [];
                            // Attach array only if parent holder is available (not applicable here)
                            // We cannot set parent reference easily here; assume invalid pattern
                            return; // skip invalid pattern
                        }
                        // Next container
                        if (cur.length === 0 || typeof cur[cur.length - 1] !== 'object') {
                            cur.push({});
                        }
                        cur = cur[cur.length - 1];
                        continue;
                    }

                    // Decide next container type based on nextSeg
                    const shouldBeArray = nextSeg === '' || (typeof nextSeg === 'string' && /\d+/.test(nextSeg));
                    if (cur[seg] === undefined) {
                        cur[seg] = shouldBeArray ? [] : {};
                    }
                    cur = cur[seg];
                }
            }

            function formDataToNested(fd) {
                const out = {};
                for (const [key, raw] of fd.entries()) {
                    // Skip empty strings to reduce noise; files are not handled here
                    if (raw === '' || raw === null || raw === undefined) continue;
                    const path = parseKey(key);
                    // Initialize root when necessary
                    if (path.length === 0) continue;
                    if (path[0] && out[path[0]] === undefined) {
                        out[path[0]] = {};
                    }
                    // Walk from root
                    let holder = out;
                    for (let i = 0; i < path.length - 1; i++) {
                        const seg = path[i];
                        const nextSeg = path[i + 1];
                        const shouldBeArray = nextSeg === '' || (typeof nextSeg === 'string' && /\d+/.test(nextSeg));
                        if (seg === '') {
                            // Unsupported mid-array push here; skip
                            continue;
                        }
                        if (holder[seg] === undefined) {
                            holder[seg] = shouldBeArray ? [] : {};
                        }
                        holder = holder[seg];
                    }
                    const last = path[path.length - 1];
                    if (last === '') {
                        if (!Array.isArray(holder)) {
                            // Convert to array if it's currently empty object
                            if (holder && typeof holder === 'object' && Object.keys(holder).length === 0) {
                                // Cannot reassign reference safely; skip
                            }
                            // Fallback: create array holder on parent step is not available; store as single value
                            holder = [String(raw)];
                        } else {
                            holder.push(String(raw));
                        }
                    } else {
                        if (Array.isArray(holder)) {
                            const idx = isFinite(last) ? Number(last) : holder.length;
                            holder[idx] = String(raw);
                        } else {
                            holder[last] = String(raw);
                        }
                    }
                }
                return out;
            }

            function pruneEmpty(value) {
                if (Array.isArray(value)) {
                    const pruned = value.map(pruneEmpty).filter(v => {
                        if (Array.isArray(v)) return v.length > 0;
                        if (v && typeof v === 'object') return Object.keys(v).length > 0;
                        return v !== '' && v !== null && v !== undefined;
                    });
                    return pruned;
                }
                if (value && typeof value === 'object') {
                    const out = {};
                    for (const [k, v] of Object.entries(value)) {
                        const pv = pruneEmpty(v);
                        if (Array.isArray(pv)) {
                            if (pv.length) out[k] = pv;
                        } else if (pv && typeof pv === 'object') {
                            if (Object.keys(pv).length) out[k] = pv;
                        } else if (pv !== '' && pv !== null && pv !== undefined) {
                            out[k] = pv;
                        }
                    }
                    return out;
                }
                return value;
            }

            document.addEventListener('DOMContentLoaded', () => {
                const form = document.getElementById('settings-form');
                if (!form) return;
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const fd = new FormData(form);
                    let payload = formDataToNested(fd);
                    payload = pruneEmpty(payload);

                    // Guard: do not submit empty payloads
                    if (Array.isArray(payload) ? payload.length === 0 : Object.keys(payload || {}).length === 0) {
                        window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'error', message: 'Tidak ada perubahan untuk disimpan.' } }));
                        return;
                    }

                    try {
                        const res = await fetch('{{ url('/settings/save') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify(payload),
                        });
                        if (!res.ok) throw new Error('Request failed');
                        // Optional: bubble a notification used by existing Alpine code
                        window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'success', message: 'Pengaturan disimpan.' } }));
                    } catch (err) {
                        window.dispatchEvent(new CustomEvent('notify', { detail: { type: 'error', message: 'Gagal menyimpan pengaturan.' } }));
                    }
                });
            });
        })();
    </script>
</x-app-layout>
