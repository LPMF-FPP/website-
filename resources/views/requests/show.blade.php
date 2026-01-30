@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Permintaan Pengujian #' . ($request->receipt_number ?? $request->request_number)"
            :breadcrumbs="[[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Detail' ]]"
        />
    </x-slot>

    {{-- Main Content Wrapped in Alpine Component for Documents --}}
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6 pb-12" 
         x-data="requestDocuments({{ $request->id }})" 
         x-init="init()">
        
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-start">
                <svg class="w-5 h-5 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="font-semibold">{{ session('success') }}</p>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-start">
                <svg class="w-5 h-5 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="font-semibold">Error!</p>
                    <p class="text-sm mt-1">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        {{-- 1. Header Summary Card --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-gray-200">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-2xl font-bold text-gray-900">
                            {{ $request->receipt_number ?? $request->request_number }}
                        </h2>
                        <x-status-badge :status="$request->status" />
                    </div>
                    <div class="flex flex-wrap gap-y-2 gap-x-6 text-sm text-gray-600">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="font-medium text-gray-900 mr-1">{{ $request->investigator->name }}</span>
                            <span>({{ $request->investigator->jurisdiction }})</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                            <span class="font-medium text-gray-900 mr-1">{{ $request->samples->count() }}</span>
                            <span>Sampel</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $request->created_at->format('d M Y, H:i') }}</span>
                        </div>
                    </div>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('requests.edit', $request) }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Data
                    </a>
                    
                    {{-- Tombol Cetak BA Cepat --}}
                    <button type="button" 
                            onclick="generateBeritaAcara()"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Cetak BA
                    </button>
                    
                    <a href="{{ route('requests.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Kembali
                    </a>
                </div>
            </div>
        </div>

        {{-- Tanggal Verifikasi URMIN --}}
        <div class="bg-white shadow-sm sm:rounded-lg p-6 border border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Tanggal Verifikasi Urmin</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        @if($request->verified_at)
                            Terverifikasi pada: <span class="font-medium text-gray-900">{{ $request->verified_at->format('d F Y') }}</span>
                        @else
                            <span class="text-amber-600">⚠️ Belum diinput</span>
                        @endif
                    </p>
                </div>
                <div x-data="{ editing: false, date: '{{ $request->verified_at?->format('Y-m-d') ?? '' }}', saving: false }">
                    <template x-if="!editing">
                        <button @click="editing = true" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            {{ $request->verified_at ? 'Ubah' : 'Input Tanggal' }}
                        </button>
                    </template>
                    <template x-if="editing">
                        <form 
                            method="POST" 
                            action="{{ route('requests.update-verified-at', $request) }}"
                            class="flex items-center gap-2"
                            @submit="saving = true"
                        >
                            @csrf
                            @method('PATCH')
                            <input 
                                type="date" 
                                name="verified_at" 
                                x-model="date"
                                class="px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500"
                                required
                            >
                            <button 
                                type="submit" 
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-md hover:bg-primary-700 disabled:opacity-50"
                                :disabled="saving"
                            >
                                <span x-show="!saving">Simpan</span>
                                <span x-show="saving">Menyimpan...</span>
                            </button>
                            <button 
                                type="button" 
                                @click="editing = false" 
                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-800"
                                :disabled="saving"
                            >
                                Batal
                            </button>
                        </form>
                    </template>
                </div>
            </div>
        </div>

        {{-- 2. Reminder Card --}}
        <div x-data="{ showReminder: true }" 
             x-show="showReminder" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform -translate-y-2"
             class="bg-amber-50 border border-amber-200 rounded-lg p-5 relative">
            
            <button @click="showReminder = false" class="absolute top-4 right-4 text-amber-400 hover:text-amber-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
            
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <span class="text-3xl mr-4">💡</span>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-amber-900 mb-2">Petunjuk Kelengkapan Dokumen</h3>
                    <div class="text-amber-800 text-sm space-y-1">
                        <p>Mohon perhatikan langkah-langkah berikut untuk kelancaran proses administrasi:</p>
                        <ol class="list-decimal list-inside ml-1 font-medium space-y-1 mt-2">
                            <li>Cetak <strong>Berita Acara Penerimaan</strong> sebanyak <span class="bg-amber-100 px-1 rounded border border-amber-200">2 rangkap</span>.</li>
                            <li>Serahkan permohonan beserta Berita Acara ke bagian <strong>Administrasi</strong>.</li>
                        </ol>
                    </div>
                    <div class="mt-4">
                        <button onclick="generateBeritaAcara()" class="inline-flex items-center text-sm font-medium text-amber-700 hover:text-amber-900 underline">
                            Cetak BA Sekarang
                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. Daftar Sampel Section --}}
        <x-collapsible-section id="samples" title="Daftar Sampel" :count="$request->samples->count()" :open="true">
            @include('requests.partials.samples-table')
        </x-collapsible-section>

        {{-- 4. Dokumen Section --}}
        <x-collapsible-section id="documents" title="Dokumen" :open="true">
            @include('requests.partials.documents-grid')
            
            {{-- Integrated Berita Acara Status/Generation inside Document Section --}}
            <div class="mt-8 pt-6 border-t border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                         <h4 class="font-medium text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Berita Acara Penerimaan Sampel
                        </h4>
                        <p class="text-sm text-gray-500 mt-1">
                            Status: <span id="ba-status" class="font-medium">Checking...</span>
                        </p>
                    </div>
                    
                    <div>
                        <button
                            id="btn-generate-ba"
                            type="button"
                            onclick="generateBeritaAcara()"
                            class="hidden inline-flex items-center px-4 py-2 bg-blue-50 border border-blue-200 text-blue-700 text-sm font-medium rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                            <span id="btn-generate-text">Generate Dokumen</span>
                            <span id="btn-generate-loading" class="hidden flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Generating...
                            </span>
                        </button>
                        
                        <div id="ba-actions" class="hidden space-x-2">
                            <a
                                id="ba-view-link"
                                href="{{ route('requests.berita-acara.view', $request) }}"
                                target="_blank"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Lihat
                            </a>
                            <a
                                id="ba-download-link"
                                href="{{ route('requests.berita-acara.download', $request) }}"
                                class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </x-collapsible-section>

        {{-- 4.5 Label Barang Bukti Section --}}
        <x-collapsible-section id="labels" title="Label Barang Bukti" :count="$request->evidenceUnits->count()" :open="true">
            @include('partials.label-section')
        </x-collapsible-section>

        {{-- 5. Data Penyidik & Tersangka Section --}}
        <x-collapsible-section id="investigator" title="Data Penyidik & Tersangka" :open="false">
            @include('requests.partials.investigator-info')
        </x-collapsible-section>
        
        {{-- Notification Toast (Reused) --}}
        <div id="notification-toast" class="hidden fixed top-4 right-4 z-50 max-w-sm w-full">
            <div id="toast-content" class="rounded-lg shadow-lg p-4"></div>
        </div>

    </div>

    @push('scripts')
    <script>
        const requestId = {{ $request->id }};
        const csrfToken = '{{ csrf_token() }}';

        document.addEventListener('alpine:init', () => {
            Alpine.data('requestDocuments', (requestIdParam) => ({
                requestId: requestIdParam,
                csrf: csrfToken,
                documents: [],
                loading: true,
                error: '',
                deleting: {},
                selectedDocument: null,
                previewUrl: '',
                init() {
                    this.fetchDocuments();
                    // Also check BA status when init
                    // But checkBeritaAcaraStatus is global, we can call it here if we want or stick to DOMContentLoaded
                },
                async fetchDocuments() {
                    this.loading = true;
                    this.error = '';
                    try {
                        const response = await fetch(`/api/requests/${this.requestId}/documents`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(payload.message || 'Gagal memuat dokumen.');
                        }
                        const list = Array.isArray(payload?.documents)
                            ? payload.documents
                            : (Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []));
                        this.documents = list;
                    } catch (error) {
                        this.error = error.message || 'Gagal memuat dokumen.';
                    } finally {
                        this.loading = false;
                    }
                },
                // Helper methods
                documentType(doc) {
                    return doc?.type_label || doc?.type || (doc?.is_generated ? 'generated' : 'upload');
                },
                documentIsPdf(doc) {
                    if (!doc) return false;
                    const mime = (doc.mime_type || doc.mime || doc.content_type || '').toLowerCase();
                    const name = (doc.name || '').toLowerCase();
                    const ext = (doc.extension || '').toLowerCase();
                    return mime.includes('pdf') || name.endsWith('.pdf') || ext === 'pdf';
                },
                documentIsImage(doc) {
                    if (!doc) return false;
                    const mime = (doc.mime_type || doc.mime || doc.content_type || '').toLowerCase();
                    const name = (doc.name || '').toLowerCase();
                    const ext = (doc.extension || '').toLowerCase();
                    return mime.startsWith('image/') || ['.png', '.jpg', '.jpeg', '.gif'].some((suffix) => name.endsWith(suffix)) || ['png', 'jpg', 'jpeg', 'gif'].includes(ext);
                },
                openDocument(doc) {
                    const target = doc?.preview_url || doc?.url || doc?.download_url;
                    if (target) {
                        window.open(target, '_blank');
                    }
                },
                async deleteDocument(doc) {
                    if (!doc?.id) return;
                    
                    showConfirmDialog({
                        type: 'danger',
                        title: 'Hapus Dokumen',
                        message: `Apakah Anda yakin ingin menghapus dokumen <strong>${doc.name}</strong>?<br><br>Tindakan ini tidak dapat dibatalkan.`,
                        confirmButtonText: 'Ya, Hapus',
                        confirmButtonLoadingText: 'Menghapus...',
                        cancelButtonText: 'Batal',
                        onConfirm: async () => {
                            this.deleting = { ...this.deleting, [doc.id]: true };
                            this.error = '';
                            try {
                                const response = await fetch(`/api/documents/${doc.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': this.csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                    credentials: 'same-origin',
                                });
                                if (!response.ok) {
                                    const data = await response.json().catch(() => ({}));
                                    throw new Error(data.message || 'Gagal menghapus dokumen.');
                                }
                                this.documents = this.documents.filter((item) => item.id !== doc.id);
                                showNotification('success', 'Dokumen berhasil dihapus');
                                // Refresh BA status if we deleted BA
                                checkBeritaAcaraStatus();
                            } catch (error) {
                                showNotification('error', error.message || 'Gagal menghapus dokumen.');
                            } finally {
                                this.deleting = { ...this.deleting, [doc.id]: false };
                            }
                        }
                    });
                },
            }));
        });

        // Global functions for Notifications and BA (kept for compatibility and simplicity)
        function showNotification(type, message) {
            const toast = document.getElementById('notification-toast');
            const toastContent = document.getElementById('toast-content');

            const bgColor = type === 'success' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const textColor = type === 'success' ? 'text-green-800' : 'text-red-800';
            const iconPath = type === 'success'
                ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
                : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

            toastContent.innerHTML = `
                <div class="border ${bgColor} ${textColor} px-4 py-3 rounded flex items-start" role="alert" aria-live="polite">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${iconPath}"></path>
                    </svg>
                    <p class="text-sm font-medium flex-1">${message}</p>
                    <button onclick="hideNotification()" class="ml-2 flex-shrink-0" aria-label="Tutup notifikasi">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            `;

            toast.classList.remove('hidden');
            setTimeout(() => { hideNotification(); }, 5000);
        }

        function hideNotification() {
            document.getElementById('notification-toast').classList.add('hidden');
        }

        // Berita Acara Functions
        function checkBeritaAcaraStatus() {
            fetch(`/requests/{{ $request->id }}/berita-acara/check`)
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('ba-status');
                    const generateBtn = document.getElementById('btn-generate-ba');
                    const actionsDiv = document.getElementById('ba-actions');

                    if (data.exists) {
                        const timestamp = new Date().getTime();
                        const viewLink = document.getElementById('ba-view-link');
                        const downloadLink = document.getElementById('ba-download-link');

                        if (viewLink) {
                            const baseUrl = viewLink.getAttribute('href').split('?')[0];
                            viewLink.setAttribute('href', `${baseUrl}?v=${timestamp}`);
                        }
                        if (downloadLink) {
                            const baseUrl = downloadLink.getAttribute('href').split('?')[0];
                            downloadLink.setAttribute('href', `${baseUrl}?v=${timestamp}`);
                        }

                        statusEl.textContent = 'Dokumen tersedia';
                        statusEl.classList.remove('text-orange-600', 'text-red-600');
                        statusEl.classList.add('text-green-600');
                        generateBtn.classList.add('hidden');
                        actionsDiv.classList.remove('hidden');
                        actionsDiv.classList.add('flex');
                    } else {
                        statusEl.textContent = 'Belum di-generate';
                        statusEl.classList.remove('text-green-600', 'text-red-600');
                        statusEl.classList.add('text-orange-600');
                        generateBtn.classList.remove('hidden');
                        actionsDiv.classList.add('hidden');
                        actionsDiv.classList.remove('flex');
                    }
                })
                .catch(error => {
                    console.error('Error checking BA status:', error);
                    const statusEl = document.getElementById('ba-status');
                    statusEl.textContent = 'Error checking status.';
                    statusEl.classList.add('text-red-600');
                });
        }

        function generateBeritaAcara() {
            const generateBtn = document.getElementById('btn-generate-ba');
            const btnText = document.getElementById('btn-generate-text');
            const btnLoading = document.getElementById('btn-generate-loading');

            // Disable button and show loading
            if(generateBtn) {
                generateBtn.disabled = true;
                btnText.classList.add('hidden');
                btnLoading.classList.remove('hidden');
            }

            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/requests/{{ $request->id }}/berita-acara/generate`;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Check BA status on page load
        window.addEventListener('DOMContentLoaded', () => {
            checkBeritaAcaraStatus();
        });
    </script>
    @endpush
</x-app-layout>
