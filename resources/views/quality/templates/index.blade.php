<x-app-layout>
    <x-slot name="header">
        <div class="space-y-3">
            <x-page-header
                title="Template QMH"
                :breadcrumbs="[
                    ['label' => 'Dashboard QMH', 'route' => 'quality.index'],
                    ['label' => 'Template QMH'],
                ]"
            >
                <x-slot name="actions">
                    <a href="{{ route('quality.templates.index') }}"
                       class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                        Buat Template
                    </a>
                </x-slot>
            </x-page-header>

            <x-qmh-subnav active="templates" />
        </div>
    </x-slot>

    @php
        $initialSelectedCard = $dashboardCards->firstWhere('has_template', true);
        $initialSelectedCardKey = is_array($initialSelectedCard) ? ($initialSelectedCard['key'] ?? null) : null;
    @endphp

    <div
        class="container mx-auto px-4 py-6"
        x-data="{
            templateCards: @js($dashboardCards),
            selectedCardKey: @js($initialSelectedCardKey),
            selectedTemplate() {
                return this.templateCards.find((item) => item.key === this.selectedCardKey && item.has_template) || null;
            }
        }"
    >
        @if(session('success'))
            <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Editor Template QMH</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Tampilan dashboard template disederhanakan seperti editor blade: pilih format, lalu jalankan aksi cepat.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <template x-for="tpl in templateCards" :key="tpl.key">
                        <button
                            type="button"
                            @click="if (tpl.has_template) selectedCardKey = tpl.key"
                            :class="{
                                'ring-2 ring-blue-500': selectedCardKey === tpl.key,
                                'hover:border-blue-300': selectedCardKey !== tpl.key,
                                'opacity-60': !tpl.has_template
                            }"
                            class="relative rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-4 shadow-sm focus:outline-none transition-all"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1 text-left min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="tpl.label"></p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="tpl.description"></p>

                                    <template x-if="tpl.has_template">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span x-text="tpl.name"></span>
                                            • v<span x-text="tpl.version"></span>
                                        </p>
                                    </template>

                                    <template x-if="tpl.has_template && tpl.representative_status">
                                        <p class="mt-1 text-[11px] text-blue-600 dark:text-blue-300">
                                            Template referensi: <span x-text="tpl.representative_status"></span>
                                        </p>
                                    </template>

                                    <template x-if="tpl.has_template">
                                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span x-text="tpl.shell_label"></span>
                                            • <span x-text="tpl.footer_label"></span>
                                        </p>
                                    </template>

                                    <template x-if="!tpl.has_template">
                                        <p class="mt-1 text-xs text-gray-400">Belum ada template</p>
                                    </template>
                                </div>
                                <div x-show="selectedCardKey === tpl.key" class="ml-3">
                                    <svg class="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="mt-2" x-show="tpl.has_template && tpl.is_active">
                                <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-medium text-green-700">Active</span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg" x-show="selectedTemplate()">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex flex-col gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi Cepat</p>
                    <h2 class="text-lg font-semibold text-gray-900" x-text="selectedTemplate()?.name"></h2>
                    <p class="text-xs text-gray-600">
                        <span x-text="selectedTemplate()?.doc_type"></span>
                        • Klausul <span x-text="selectedTemplate()?.clause"></span>
                        • v<span x-text="selectedTemplate()?.version"></span>
                        • <span x-text="selectedTemplate()?.updated_at"></span>
                    </p>
                </div>

                <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <a
                        :href="selectedTemplate()?.edit_url"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Edit
                    </a>

                    <a
                        :href="selectedTemplate()?.preview_url"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center rounded-md border border-primary-300 bg-white px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-50"
                    >
                        Preview Template (HTML)
                    </a>

                    <form x-show="selectedTemplate()?.is_active" method="POST" :action="selectedTemplate()?.deactivate_url">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Nonaktifkan
                        </button>
                    </form>

                    <form x-show="selectedTemplate() && !selectedTemplate()?.is_active" method="POST" :action="selectedTemplate()?.activate_url">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="w-full rounded-md bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-700">
                            Aktifkan
                        </button>
                    </form>
                </div>

                <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <p class="font-semibold">Catatan Versioning</p>
                    <p class="mt-1">Perubahan template akan membuat versi baru agar riwayat audit tetap terjaga.</p>
                </div>
            </div>
        </div>

        <details id="upload-template" class="hidden" aria-hidden="true">
            <summary>Upload Template Legacy Anchor</summary>
            <div>Legacy anchor placeholder.</div>
        </details>
    </div>
</x-app-layout>
