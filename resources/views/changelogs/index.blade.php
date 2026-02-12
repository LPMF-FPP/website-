<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2">
            <x-breadcrumbs :items="[['label' => 'Beranda', 'href' => route('dashboard')], ['label' => 'Changelogs']]" />
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-primary-900 tracking-tight">System Changelogs</h1>
                    <p class="text-sm text-accent-600">Riwayat pembaruan dan evolusi sistem LPMF LIMS</p>
                </div>
                
                <!-- Search Box -->
                <div x-data class="relative w-full md:w-72">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input 
                        type="text" 
                        x-model="$store.changelog.search"
                        placeholder="Filter by version or keyword..." 
                        class="pl-10 w-full rounded-lg border-gray-300 focus:border-primary-500 focus:ring focus:ring-primary-200 focus:ring-opacity-50 text-sm shadow-sm"
                    >
                </div>
            </div>
        </div>
    </x-slot>

    <div 
        x-data="{ 
            versions: {{ Js::from($changelogs) }},
            activeVersion: null,
            init() {
                if(this.versions.length > 0) this.activeVersion = this.versions[0].id;
            },
            get filteredVersions() {
                if (!$store.changelog.search) return this.versions;
                const term = $store.changelog.search.toLowerCase();
                return this.versions.filter(v => 
                    v.version.toLowerCase().includes(term) || 
                    v.title.toLowerCase().includes(term) ||
                    v.content.some(c => c.toLowerCase().includes(term))
                );
            },
            scrollTo(id) {
                const el = document.getElementById(id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    this.activeVersion = id;
                }
            }
        }"
        class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8"
    >
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Sticky Sidebar (Desktop) -->
            <div class="hidden lg:block lg:col-span-3 sticky top-8 max-h-[calc(100vh-8rem)] overflow-y-auto pr-2 custom-scrollbar">
                <nav class="space-y-1" aria-label="Version navigation">
                    <template x-for="log in filteredVersions" :key="log.id">
                        <button 
                            @click="scrollTo(log.id)"
                            class="w-full group flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors border-l-2"
                            :class="{
                                'bg-primary-50 text-primary-700 border-primary-500': activeVersion === log.id,
                                'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border-transparent hover:border-gray-300': activeVersion !== log.id
                            }"
                        >
                            <span class="truncate flex-1 text-left" x-text="log.version"></span>
                            <span 
                                class="inline-block w-2 h-2 rounded-full ml-2"
                                :class="{
                                    'bg-blue-400': log.type === 'feature',
                                    'bg-green-400': log.type === 'update',
                                    'bg-red-400': log.type === 'fix' || log.type === 'security',
                                    'bg-purple-400': log.type === 'design',
                                }"
                            ></span>
                        </button>
                    </template>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-9 space-y-8">
                <template x-for="(log, index) in filteredVersions" :key="log.id">
                    <div :id="log.id" class="scroll-mt-24 group relative">
                        
                        <!-- Connector Line -->
                        <div class="absolute left-6 top-10 bottom-0 w-px bg-gray-200 group-last:hidden"></div>

                        <div class="flex items-start gap-4">
                            <!-- Icon/Badge -->
                            <div class="relative flex-shrink-0">
                                <div 
                                    class="h-12 w-12 rounded-xl flex items-center justify-center shadow-sm border border-white"
                                    :class="{
                                        'bg-blue-100 text-blue-600': log.type === 'feature',
                                        'bg-green-100 text-green-600': log.type === 'update',
                                        'bg-red-100 text-red-600': log.type === 'fix' || log.type === 'security',
                                        'bg-purple-100 text-purple-600': log.type === 'design',
                                        'bg-gray-100 text-gray-500': log.is_archived
                                    }"
                                >
                                    <template x-if="log.type === 'feature'"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg></template>
                                    <template x-if="log.type === 'fix'"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></template>
                                    <template x-if="log.type === 'security'"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></template>
                                    <template x-if="log.type === 'design'"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" /></svg></template>
                                    <template x-if="log.type === 'update'"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg></template>
                                </div>
                            </div>

                            <!-- Card Content -->
                            <div class="flex-1 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:border-primary-200 transition-colors">
                                <div class="px-6 py-5">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                                        <div class="flex items-center gap-3">
                                            <h2 class="text-xl font-bold text-gray-900 font-mono tracking-tight" x-text="log.version"></h2>
                                            <span 
                                                class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider bg-gray-100 text-gray-600"
                                                x-text="log.date"
                                            ></span>
                                        </div>
                                        <template x-if="index === 0 && !$store.changelog.search">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-bold bg-primary-50 text-primary-700 border border-primary-100">
                                                LATEST RELEASE
                                            </span>
                                        </template>
                                    </div>

                                    <h3 class="text-lg font-semibold text-gray-800 mb-4" x-text="log.title"></h3>

                                    <ul class="space-y-3">
                                        <template x-for="item in log.content">
                                            <li class="flex items-start gap-3 text-gray-600 text-[15px] leading-relaxed">
                                                <svg class="w-5 h-5 text-gray-400 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <div x-html="item"></div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <template x-if="log.is_archived">
                                    <div class="bg-gray-50 px-6 py-2 text-xs text-gray-500 border-t border-gray-100 text-center uppercase tracking-widest font-semibold">
                                        Archived
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="filteredVersions.length === 0" class="text-center py-12 border-2 border-dashed border-gray-200 rounded-xl">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 mb-4">
                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No versions found</h3>
                    <p class="text-gray-500 mt-1">Try adjusting your search filters.</p>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('changelog', {
                search: ''
            })
        })
        
        // Scroll Spy Logic
        document.addEventListener('scroll', () => {
            const versions = document.querySelectorAll('.scroll-mt-24');
            let activeId = null;
            
            versions.forEach(el => {
                const rect = el.getBoundingClientRect();
                if (rect.top <= 150) { // Threshold from top
                    activeId = el.id;
                }
            });
            
            if (activeId) {
                // Access Alpine component scope to update activeVersion
                const container = document.querySelector('[x-data]');
                if(container && container._x_dataStack) {
                    container._x_dataStack[0].activeVersion = activeId;
                }
            }
        }, { passive: true });
    </script>
    @endpush
    
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 20px;
        }
    </style>
</x-app-layout>
