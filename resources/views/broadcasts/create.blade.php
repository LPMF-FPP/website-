<x-app-layout>
    <x-slot name="header">
        <x-page-header
            title="Buat Broadcast"
            :breadcrumbs="[
                ['label' => 'Pengaturan', 'url' => route('settings.index')],
                ['label' => 'Broadcast', 'url' => route('broadcasts.index')],
                ['label' => 'Buat']
            ]"
        />
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 py-6"
         x-data="broadcastForm()"
         x-init="init()">

        <form @submit.prevent="submitForm()">
            <!-- Basic Info -->
            <div class="surface-sem rounded-lg border border-sem mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-pd-text mb-4">Informasi Broadcast</h3>

                    <div class="space-y-4">
                        <!-- Title -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-pd-text mb-1">
                                Judul <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="title"
                                   x-model="form.title"
                                   required
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500"
                                   placeholder="Contoh: Pemberitahuan Jadwal Libur">
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-pd-text mb-1">
                                Pesan <span class="text-red-500">*</span>
                            </label>
                            <textarea id="message"
                                      x-model="form.message"
                                      required
                                      rows="6"
                                      class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500 font-mono text-sm"
                                      placeholder="Ketik pesan broadcast..."></textarea>
                            <p class="mt-1 text-xs text-pd-text-muted">
                                <span x-text="form.message.length"></span>/2000 karakter
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Selection -->
            <div class="surface-sem rounded-lg border border-sem mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-pd-text mb-4">Target Penerima</h3>

                    <div class="space-y-4">
                        <!-- Target Type -->
                        <div>
                            <label class="block text-sm font-medium text-pd-text mb-2">
                                Tipe Penerima <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach($targetTypes as $key => $label)
                                    <label class="relative flex items-center p-4 rounded-lg border cursor-pointer transition-colors"
                                           :class="form.target_type === '{{ $key }}'
                                               ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                               : 'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'">
                                        <input type="radio"
                                               name="target_type"
                                               value="{{ $key }}"
                                               x-model="form.target_type"
                                               @change="onTargetTypeChange()"
                                               class="sr-only">
                                        <div>
                                            <span class="block text-sm font-medium text-pd-text">{{ $label }}</span>
                                            <span class="block text-xs text-pd-text-muted mt-0.5">
                                                @if($key === 'investigators')
                                                    Penyidik yang terdaftar
                                                @elseif($key === 'users')
                                                    Staff laboratorium
                                                @else
                                                    Pilih penerima manual
                                                @endif
                                            </span>
                                        </div>
                                        <div x-show="form.target_type === '{{ $key }}'"
                                             class="absolute top-2 right-2">
                                            <svg class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Filters for Investigators -->
                        <div x-show="form.target_type === 'investigators'" x-cloak class="space-y-4">
                            <div>
                                <label for="jurisdiction" class="block text-sm font-medium text-pd-text mb-1">
                                    Filter Jurisdiksi (Opsional)
                                </label>
                                <select id="jurisdiction"
                                        x-model="form.target_filters.jurisdiction"
                                        @change="previewRecipients()"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Semua Jurisdiksi</option>
                                    @foreach($jurisdictions as $jurisdiction)
                                        <option value="{{ $jurisdiction }}">{{ $jurisdiction }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Filters for Users -->
                        <div x-show="form.target_type === 'users'" x-cloak class="space-y-4">
                            <div>
                                <label for="role" class="block text-sm font-medium text-pd-text mb-1">
                                    Filter Role (Opsional)
                                </label>
                                <select id="role"
                                        x-model="form.target_filters.role"
                                        @change="previewRecipients()"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:ring-primary-500 focus:border-primary-500">
                                    <option value="">Semua Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="analis">Analis</option>
                                    <option value="penyelia">Penyelia</option>
                                    <option value="manajer">Manajer</option>
                                </select>
                            </div>
                        </div>

                        <!-- Custom recipient selection placeholder -->
                        <div x-show="form.target_type === 'custom'" x-cloak>
                            <p class="text-sm text-pd-text-muted">
                                Fitur pemilihan penerima kustom akan segera tersedia.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipients Preview -->
            <div class="surface-sem rounded-lg border border-sem mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-pd-text">Preview Penerima</h3>
                        <button type="button"
                                @click="previewRecipients()"
                                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300">
                            Refresh
                        </button>
                    </div>

                    <div x-show="previewLoading" class="py-8 text-center">
                        <svg class="animate-spin h-8 w-8 mx-auto text-primary-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div x-show="!previewLoading && recipientCount === 0" class="py-8 text-center text-pd-text-muted">
                        <p>Tidak ada penerima yang ditemukan</p>
                    </div>

                    <div x-show="!previewLoading && recipientCount > 0">
                        <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-sm text-blue-700 dark:text-blue-300">
                                <span class="font-semibold" x-text="recipientCount"></span> penerima akan menerima broadcast ini
                            </p>
                        </div>

                        <div class="max-h-48 overflow-y-auto">
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template x-for="recipient in previewList" :key="recipient.phone">
                                    <li class="py-2 flex items-center justify-between">
                                        <span class="text-sm text-pd-text" x-text="recipient.name"></span>
                                        <span class="text-xs text-pd-text-muted" x-text="recipient.phone"></span>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="recipientCount > 20" class="mt-2 text-xs text-pd-text-muted text-center">
                                ... dan <span x-text="recipientCount - 20"></span> penerima lainnya
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('broadcasts.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                    Batal
                </a>
                <button type="submit"
                        :disabled="loading || recipientCount === 0"
                        class="px-6 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Simpan sebagai Draft
                </button>
                <button type="button"
                        @click="submitAndSend()"
                        :disabled="loading || recipientCount === 0"
                        class="px-6 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed inline-flex items-center gap-2">
                    <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Simpan & Kirim
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function broadcastForm() {
            return {
                loading: false,
                previewLoading: false,
                recipientCount: 0,
                previewList: [],
                sendAfterSave: false,
                form: {
                    title: '',
                    message: '',
                    target_type: 'investigators',
                    target_filters: {
                        jurisdiction: '',
                        role: ''
                    },
                    recipient_ids: []
                },

                init() {
                    this.previewRecipients();
                },

                onTargetTypeChange() {
                    this.form.target_filters = {
                        jurisdiction: '',
                        role: ''
                    };
                    this.form.recipient_ids = [];
                    this.previewRecipients();
                },

                async previewRecipients() {
                    this.previewLoading = true;

                    try {
                        const response = await fetch('{{ route("broadcasts.preview-recipients") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                target_type: this.form.target_type,
                                target_filters: this.form.target_filters,
                                recipient_ids: this.form.recipient_ids
                            })
                        });

                        const data = await response.json();

                        if (response.ok) {
                            this.recipientCount = data.count;
                            this.previewList = data.recipients;
                        }
                    } catch (error) {
                        console.error('Error:', error);
                    } finally {
                        this.previewLoading = false;
                    }
                },

                async submitForm() {
                    this.loading = true;

                    try {
                        const response = await fetch('{{ route("broadcasts.store") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await response.json();

                        if (response.ok) {
                            if (this.sendAfterSave && data.broadcast) {
                                await this.sendBroadcast(data.broadcast.id);
                            } else {
                                window.location.href = '{{ route("broadcasts.index") }}';
                            }
                        } else {
                            alert(data.message || 'Gagal menyimpan broadcast');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan');
                    } finally {
                        this.loading = false;
                    }
                },

                async submitAndSend() {
                    if (!confirm('Simpan dan kirim broadcast ini ke semua penerima?')) return;
                    this.sendAfterSave = true;
                    await this.submitForm();
                },

                async sendBroadcast(id) {
                    try {
                        const response = await fetch(`/broadcasts/${id}/send`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });

                        if (response.ok) {
                            window.location.href = '{{ route("broadcasts.index") }}';
                        } else {
                            const data = await response.json();
                            alert(data.message || 'Gagal mengirim broadcast');
                            window.location.href = '{{ route("broadcasts.index") }}';
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        window.location.href = '{{ route("broadcasts.index") }}';
                    }
                }
            }
        }
    </script>
    @endpush
</x-app-layout>
