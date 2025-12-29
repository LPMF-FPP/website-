
                {{-- Label Sampel Section --}}
                <h3 class="text-lg font-semibold text-gray-900 mb-4 mt-6">Label Barang Bukti</h3>

                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-2 mb-2">
                                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <h4 class="font-semibold text-yellow-900">Label Barang Bukti</h4>
                            </div>
                            <div id="label-status-container">
                                @if($request->evidenceUnits->count() > 0)
                                    <p class="text-sm text-yellow-800">
                                        <span class="font-bold">{{ $request->evidenceUnits->count() }}</span> label telah dibuat.
                                    </p>
                                @else
                                    <p class="text-sm text-yellow-800">
                                        Label belum dibuat. Klik tombol generate untuk membuat label dari sampel yang ada.
                                    </p>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-col space-y-2 ml-4">
                            {{-- Generate Button --}}
                            <div id="btn-generate-label-wrapper" class="{{ $request->evidenceUnits->count() > 0 ? 'hidden' : '' }}">
                                <button
                                    id="btn-generate-label"
                                    type="button"
                                    onclick="generateLabels()"
                                    class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 disabled:opacity-50">
                                    <span id="label-btn-text">Generate Label</span>
                                    <span id="label-btn-loading" class="hidden">
                                        <svg class="animate-spin h-4 w-4 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing...
                                    </span>
                                </button>
                            </div>

                            {{-- Print Actions --}}
                            <div id="label-actions" class="{{ $request->evidenceUnits->count() > 0 ? 'flex' : 'hidden' }} space-x-2">
                                <a
                                    href="{{ route('labels.evidence.sheet', $request->id) }}"
                                    target="_blank"
                                    class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-md hover:bg-gray-900 text-center flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                    Cetak Sheet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Script for Label Generation --}}
                <script>
                    function generateLabels() {
                        if(!confirm('Apakah Anda yakin ingin membuat label untuk semua sampel dalam permintaan ini?')) return;

                        const btn = document.getElementById('btn-generate-label');
                        const btnText = document.getElementById('label-btn-text');
                        const btnLoading = document.getElementById('label-btn-loading');

                        btn.disabled = true;
                        btnText.classList.add('hidden');
                        btnLoading.classList.remove('hidden');

                        // Prepare sample IDs (all samples in request)
                        const sampleIds = @json($request->samples->pluck('id'));

                        fetch('/labels/evidence-units', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                request_id: {{ $request->id }},
                                sample_ids: sampleIds
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if(data.success) {
                                // Reload page to show buttons
                                window.location.reload();
                            } else {
                                alert('Error: ' + (data.message || 'Gagal membuat label'));
                                btn.disabled = false;
                                btnText.classList.remove('hidden');
                                btnLoading.classList.add('hidden');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Terjadi kesalahan jaringan');
                            btn.disabled = false;
                            btnText.classList.remove('hidden');
                            btnLoading.classList.add('hidden');
                        });
                    }
                </script>
