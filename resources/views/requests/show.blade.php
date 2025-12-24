@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header
            :title="'Permintaan Pengujian #' . $request->request_number"
            :breadcrumbs="[[ 'label' => 'Permintaan', 'href' => route('requests.index') ], [ 'label' => 'Detail' ]]"
        />
    </x-slot>

{{-- Receipt document references removed (sample_receipt, request_letter_receipt) --}}

    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                <p class="font-semibold">{{ session('success') }}</p>
                {{-- Removed: documents_generated notice for receipt PDFs --}}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                <p class="font-semibold">Error!</p>
                <p class="text-sm mt-1">{{ session('error') }}</p>
            </div>
        @endif

        {{-- Action Bar --}}
        <div class="flex items-center justify-between bg-white shadow-sm sm:rounded-lg p-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Detail Permintaan</h3>
                <p class="text-sm text-gray-600">Status: <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $request->status)) }}</span></p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('requests.edit', $request) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Data
                </a>
                <a href="{{ route('requests.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Data Penyidik</h3>
                    <dl class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Nama</dt>
                            <dd>{{ $request->investigator->name ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">NRP</dt>
                            <dd>{{ $request->investigator->nrp ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Pangkat</dt>
                            <dd>{{ $request->investigator->rank ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Satuan</dt>
                            <dd>{{ $request->investigator->jurisdiction ?? '-' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium text-gray-600">Kontak</dt>
                            <dd>{{ $request->investigator->phone ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Informasi Kasus</h3>
                    <dl class="space-y-2 text-sm text-gray-700">
                        <div>
                            <dt class="font-medium text-gray-600">Nama Tersangka</dt>
                            <dd>{{ $request->suspect_name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-600">Jenis Kelamin Tersangka</dt>
                            <dd>{{ $request->suspect_gender === 'male' ? 'Laki-laki' : ($request->suspect_gender === 'female' ? 'Perempuan' : '-') }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-600">Umur Tersangka</dt>
                            <dd>{{ $request->suspect_age !== null ? $request->suspect_age . ' tahun' : '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daftar Sampel</h3>

                @if ($request->samples->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Kode</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Nama</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Jenis Pengujian</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Zat Aktif</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 uppercase">Hasil Pengujian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach ($request->samples as $sample)
                                    @php
                                        $methodLabels = [
                                            'uv_vis' => 'Identifikasi Spektrofotometri UV-VIS',
                                            'gc_ms' => 'Identifikasi GC-MS',
                                            'lc_ms' => 'Identifikasi LC-MS',
                                        ];
                                        $methods = collect($sample->test_methods ?? [])->map(fn ($value) => $methodLabels[$value] ?? ucfirst(str_replace('_', ' ', $value)));
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-900">{{ $sample->sample_code }}</td>
                                        <td class="px-4 py-2 text-gray-700">{{ $sample->sample_name }}</td>
                                        <td class="px-4 py-2 text-gray-700">
                                            @if ($methods->isNotEmpty())
                                                <ul class="list-disc list-inside space-y-1 text-gray-700">
                                                    @foreach ($methods as $method)
                                                        <li>{{ $method }}</li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-gray-700">{{ $sample->active_substance ?? '-' }}</td>
                                        <td class="px-4 py-2 text-gray-700">
                                            @if ($sample->testResult)
                                                {{ $sample->testResult->summary ?? '-' }}
                                            @else
                                                <span class="text-gray-400 italic">Belum ada hasil</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Belum ada sampel terdaftar.</p>
                @endif

                {{-- Berita Acara Penerimaan --}}
                <h3 class="text-lg font-semibold text-gray-900 mb-4 mt-6">Berita Acara Penerimaan</h3>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h4 class="font-semibold text-blue-900">Berita Acara Penerimaan Sampel</h4>
                            </div>
                            <p class="text-sm text-blue-800">
                                Dokumen resmi penerimaan sampel dari penyidik.
                                <span id="ba-status" class="font-medium">Checking...</span>
                            </p>
                        </div>
                        <div class="flex flex-col space-y-2 ml-4">
                            <button
                                id="btn-generate-ba"
                                type="button"
                                onclick="generateBeritaAcara()"
                                class="hidden px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="btn-generate-text">Generate Dokumen</span>
                                <span id="btn-generate-loading" class="hidden">
                                    <svg class="animate-spin h-4 w-4 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                                    class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 text-center">
                                    Lihat
                                </a>
                                <a
                                    id="ba-download-link"
                                    href="{{ route('requests.berita-acara.download', $request) }}"
                                    class="px-4 py-2 border border-blue-600 text-blue-600 text-sm font-medium rounded-md hover:bg-blue-50 text-center">
                                    Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg" x-data="requestDocuments({{ $request->id }})" x-init="init()" id="request-documents">
            <div class="border-b border-primary-100 px-6 py-4">
        <div class="flex justify-end space-x-2">
            <a href="{{ route('requests.edit', $request) }}" class="inline-flex items-center px-4 py-2 border border-indigo-600 text-indigo-600 text-sm font-medium rounded-md hover:bg-indigo-50">
                Edit Permintaan
            </a>
            <a href="{{ route('requests.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50">
                Kembali ke Daftar
            </a>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notification-toast" class="hidden fixed top-4 right-4 z-50 max-w-sm w-full">
        <div id="toast-content" class="rounded-lg shadow-lg p-4"></div>
    </div>

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
                        if (this.selectedDocument) {
                            const updated = this.documents.find((doc) => doc.id === this.selectedDocument.id);
                            if (!updated) {
                                this.clearPreview();
                            } else {
                                this.selectDocument(updated);
                            }
                        }
                    } catch (error) {
                        this.error = error.message || 'Gagal memuat dokumen.';
                    } finally {
                        this.loading = false;
                    }
                },
                selectDocument(doc) {
                    this.selectedDocument = doc;
                    this.previewUrl = doc?.preview_url || doc?.url || doc?.download_url || '';
                },
                clearPreview() {
                    this.selectedDocument = null;
                    this.previewUrl = '';
                },
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
                async deleteDocument(doc) {
                    if (!doc?.id) return;
                    if (!confirm('Yakin hapus dokumen ini?')) return;
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
                        if (this.selectedDocument && this.selectedDocument.id === doc.id) {
                            this.clearPreview();
                        }
                    } catch (error) {
                        this.error = error.message || 'Gagal menghapus dokumen.';
                    } finally {
                        this.deleting = { ...this.deleting, [doc.id]: false };
                    }
                },
                isDeleting(id) {
                    return !!this.deleting[id];
                },
                openDocument(doc) {
                    const target = doc?.preview_url || doc?.url || doc?.download_url;
                    if (target) {
                        window.open(target, '_blank');
                    }
                },
            }));
        });

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

            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideNotification();
            }, 5000);
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
                        // Add cache-busting timestamp to URLs
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

                        statusEl.textContent = 'Dokumen sudah tersedia.';
                        statusEl.classList.add('text-green-600');
                        generateBtn.classList.add('hidden');
                        actionsDiv.classList.remove('hidden');
                        actionsDiv.classList.add('flex');
                    } else {
                        statusEl.textContent = 'Dokumen belum di-generate.';
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
            generateBtn.disabled = true;
            btnText.classList.add('hidden');
            btnLoading.classList.remove('hidden');

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
</x-app-layout>
