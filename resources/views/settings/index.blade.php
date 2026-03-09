@php
    $initialSettings = $settings ?? [];
    $initialRoles = data_get($initialSettings, 'security.roles', []);
    $initialManageRoles = data_get($initialRoles, 'can_manage_settings', []);
    $initialIssueRoles = data_get($initialRoles, 'can_issue_number', []);
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

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Pengaturan LIMS"
            :breadcrumbs="[['label' => 'Settings']]"
            description="Kelola pengaturan sistem LIMS per bagian."
        />
    </x-slot>

    <div
        x-data="settingsPageAlpine"
        x-init="init()"
        class="max-w-7xl mx-auto sm:px-6 lg:px-8"
    >

        {{-- Load Error State --}}
        <div
            x-show="client.state.loadError && !client.state.pageLoading"
            x-cloak
            class="py-16"
        >
            <div class="max-w-lg mx-auto text-center">
                <svg class="h-12 w-12 text-red-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Gagal Memuat Pengaturan</h3>
                <p class="text-sm text-gray-500 mb-4" x-text="client.state.loadError"></p>
                <button
                    @click="client.loadAll()"
                    type="button"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Coba Lagi
                </button>
            </div>
        </div>

        {{-- Main Content: Sidebar + Content Area --}}
        <div class="flex gap-6" x-show="!client.state.pageLoading && !client.state.loadError" x-cloak>
            
            {{-- Sidebar Navigation --}}
            <div class="w-80 flex-shrink-0">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3 px-3">Bagian</h3>
                    <nav class="space-y-2" role="tablist" aria-label="Bagian Pengaturan">
                        <button
                            type="button"
                            id="tab-numbering"
                            role="tab"
                            :aria-selected="activeSection === 'numbering'"
                            aria-controls="panel-numbering"
                            @click="activeSection = 'numbering'"
                            :class="activeSection === 'numbering' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Penomoran Otomatis
                        </button>

                        <button
                            type="button"
                            id="tab-localization"
                            role="tab"
                            :aria-selected="activeSection === 'localization'"
                            aria-controls="panel-localization"
                            @click="activeSection = 'localization'"
                            :class="activeSection === 'localization' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Lokalisasi & Retensi
                        </button>

                        <button
                            type="button"
                            id="tab-branding"
                            role="tab"
                            :aria-selected="activeSection === 'branding'"
                            aria-controls="panel-branding"
                            @click="activeSection = 'branding'"
                            :class="activeSection === 'branding' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Branding & PDF
                        </button>
                        
                        <button
                            type="button"
                            id="tab-documents"
                            role="tab"
                            :aria-selected="activeSection === 'documents'"
                            aria-controls="panel-documents"
                            @click="activeSection = 'documents'"
                            :class="activeSection === 'documents' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Manajemen Dokumen
                        </button>

                        <button
                            type="button"
                            id="tab-iku"
                            role="tab"
                            :aria-selected="activeSection === 'iku'"
                            aria-controls="panel-iku"
                            @click="activeSection = 'iku'"
                            :class="activeSection === 'iku' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Perhitungan IKU
                        </button>

                        <button
                            type="button"
                            id="tab-survey-questions"
                            role="tab"
                            :aria-selected="activeSection === 'survey_questions'"
                            aria-controls="panel-survey-questions"
                            @click="activeSection = 'survey_questions'"
                            :class="activeSection === 'survey_questions' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Pertanyaan Survey
                        </button>

                        <button
                            type="button"
                            id="tab-monitoring-logging"
                            role="tab"
                            :aria-selected="activeSection === 'monitoring_logging'"
                            aria-controls="panel-monitoring-logging"
                            @click="activeSection = 'monitoring_logging'"
                            :class="activeSection === 'monitoring_logging' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Monitoring & Logging
                        </button>

                        <button
                            type="button"
                            id="tab-backup"
                            role="tab"
                            :aria-selected="activeSection === 'backup'"
                            aria-controls="panel-backup"
                            @click="activeSection = 'backup'"
                            :class="activeSection === 'backup' ? 'bg-gray-200 text-gray-900 font-medium' : 'bg-white text-gray-700 hover:bg-gray-50'"
                            class="w-full text-left px-4 py-3 rounded-lg transition-colors text-sm"
                        >
                            Backup & Maintenance
                        </button>
                    </nav>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <h4 class="text-xs font-semibold text-gray-600 px-3 mb-2">Halaman Lainnya</h4>
                        <a
                            href="{{ route('settings.blade-templates') }}"
                            class="flex items-center justify-between w-full px-4 py-3 rounded-lg transition-colors text-sm bg-white text-gray-700 hover:bg-gray-50"
                        >
                            <span>Template Dokumen</span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 3h7v7m0-7L10 14m-4 0h4v4H6a2 2 0 01-2-2V6a2 2 0 012-2z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Content Area --}}
            <div class="flex-1 min-w-0">
                <div
                    x-show="activeSection === 'numbering'"
                    id="panel-numbering"
                    role="tabpanel"
                    aria-labelledby="tab-numbering"
                    tabindex="-1"
                >
                    @include('settings.partials.numbering')
                </div>

                <div
                    x-show="activeSection === 'localization'"
                    id="panel-localization"
                    role="tabpanel"
                    aria-labelledby="tab-localization"
                    tabindex="-1"
                >
                    @include('settings.partials.localization-retention')
                </div>

                <div
                    x-show="activeSection === 'branding'"
                    id="panel-branding"
                    role="tabpanel"
                    aria-labelledby="tab-branding"
                    tabindex="-1"
                >
                    @include('settings.partials.branding')
                </div>

                <div
                    x-show="activeSection === 'documents'"
                    id="panel-documents"
                    role="tabpanel"
                    aria-labelledby="tab-documents"
                    tabindex="-1"
                >
                    @include('settings.partials.documents')
                </div>

                <div
                    x-show="activeSection === 'iku'"
                    id="panel-iku"
                    role="tabpanel"
                    aria-labelledby="tab-iku"
                    tabindex="-1"
                >
                    @include('settings.partials.iku')
                </div>

                <div
                    x-show="activeSection === 'survey_questions'"
                    id="panel-survey-questions"
                    role="tabpanel"
                    aria-labelledby="tab-survey-questions"
                    tabindex="-1"
                >
                    @include('settings.partials.survey-questions')
                </div>

                <div
                    x-show="activeSection === 'monitoring_logging'"
                    id="panel-monitoring-logging"
                    role="tabpanel"
                    aria-labelledby="tab-monitoring-logging"
                    tabindex="-1"
                >
                    @include('settings.partials.monitoring-logging')
                </div>

                <div
                    x-show="activeSection === 'backup'"
                    id="panel-backup"
                    role="tabpanel"
                    aria-labelledby="tab-backup"
                    tabindex="-1"
                >
                    @include('settings.partials.backup-maintenance')
                </div>
            </div>

        </div>
    </div>

    {{-- Inject initial data for Alpine component (loaded via Vite in app.js) --}}
    <script>
        window.__SETTINGS_INITIAL_DATA__ = {
            initialForm: @json($initialSettings),
            initialTemplates: @json($templates ?? []),
            optionValues: @json($options ?? []),
            initialManageRoles: @json($initialManageRoles ?? []),
            initialIssueRoles: @json($initialIssueRoles ?? []),
            initialNowPreview: @json($initialNowPreview),
        };
    </script>

    @vite(['resources/js/pages/settings/index.js'])
</x-app-layout>
