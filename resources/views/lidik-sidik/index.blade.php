<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            🔍 Lidik Sidik - Pusat Operasional Pengujian
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $stats['total_requests'] }}</div>
                    <div class="text-xs text-gray-600">Total Permintaan</div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-orange-600">{{ $stats['pending_requests'] }}</div>
                    <div class="text-xs text-gray-600">Pending</div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600">{{ $stats['in_progress'] }}</div>
                    <div class="text-xs text-gray-600">In Progress</div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ $stats['completed'] }}</div>
                    <div class="text-xs text-gray-600">Completed</div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-primary-700">{{ $stats['samples_this_month'] }}</div>
                    <div class="text-xs text-gray-600">Sampel Bulan Ini</div>
                </div>
            </div>
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 text-center">
                    <div class="text-2xl font-bold text-red-600">{{ $stats['active_substances_found'] }}</div>
                    <div class="text-xs text-gray-600">Zat Aktif</div>
                    @if(($metrics['total_active_substances'] ?? 0) > 0)
                        <div class="mt-1 text-[11px] text-gray-500">
                            {{ number_format($metrics['total_active_substances']) }} total temuan
                            @if($metrics['active_substances_fallback'] ?? false)
                                <span class="block text-[10px] text-yellow-600">Data simulasi sementara</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>

                <!-- Action Sections dengan Tombol Seperti "Lacak Status" -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">

            <!-- Section 1: Buat Permintaan Baru -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">?? Buat Permintaan</h3>
                            <p class="text-sm text-gray-600">Ajukan pengujian baru</p>
                        </div>
                    </div>
                    <div class="bg-green-50 border border-green-100 rounded-lg p-4 text-sm text-green-900">
                        <strong>Langkah cepat:</strong>
                        <ul class="mt-2 space-y-1 list-disc list-inside">
                            <li>Isi detail penyidik &amp; perkara</li>
                            <li>Unggah surat permintaan resmi</li>
                            <li>Daftarkan sampel dan metode uji</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    <a href="{{ route('requests.create') }}"
                       class="inline-flex items-center justify-center w-full px-4 py-3 text-sm font-semibold rounded-lg shadow-sm transition-colors"
                       style="background-color:#16a34a;color:#ffffff;">
                        Buat Permintaan Baru <span class="ml-2"></span>
                    </a>
                </div>
            </div>

            <!-- Section 2: Pengujian Sampel -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-primary-100 rounded-lg">
                            <svg class="w-8 h-8 text-primary-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Pengujian Sampel</h3>
                            <p class="text-sm text-gray-600">Input hasil pengujian lab</p>
                        </div>
                    </div>
                    <div class="bg-primary-50 border border-primary-100 rounded-lg p-4 text-sm text-primary-900">
                        <strong>Fitur lab:</strong>
                        <ul class="mt-2 space-y-1 list-disc list-inside">
                            <li>Penugasan analis &amp; jadwal uji</li>
                            <li>Pencatatan identifikasi sampel</li>
                            <li>Metode pengujian multi-instrumen</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    <a href="{{ route('samples.test.create') }}"
                       class="inline-flex items-center justify-center w-full px-4 py-3 text-sm font-semibold rounded-lg shadow-sm transition-colors bg-primary-600 text-white hover:bg-primary-700">
                        Proses Pengujian <span class="ml-2"></span>
                    </a>
                </div>
            </div>

            <!-- Section 3: Penyerahan -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-yellow-100 rounded-lg">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Penyerahan</h3>
                            <p class="text-sm text-gray-600">Dokumen &amp; hasil pengujian</p>
                        </div>
                    </div>
                    <div class="bg-yellow-50 border border-yellow-100 rounded-lg p-4 text-sm text-yellow-900">
                        <strong>Dokumen tersedia:</strong>
                        <ul class="mt-2 space-y-1 list-disc list-inside">
                            <li>Laporan laboratorium per sampel</li>
                            <li>Surat pengantar &amp; tanda terima</li>
                            <li>Survei kepuasan terintegrasi</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    <a href="{{ route('delivery.index') }}"
                       class="inline-flex items-center justify-center w-full px-4 py-3 bg-yellow-500 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-yellow-600 transition-colors">
                        Kelola Penyerahan <span class="ml-2"></span>
                    </a>
                </div>
            </div>

            <!-- Section 4: Tracking -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Tracking</h3>
                            <p class="text-sm text-gray-600">Lacak status pengujian</p>
                        </div>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-900">
                        <strong>Real-time status:</strong>
                        <ul class="mt-2 space-y-1 list-disc list-inside">
                            <li>Timeline 8 tahapan</li>
                            <li>Progress bar visual</li>
                            <li>Auto-refresh system</li>
                            <li>Info kontak lengkap</li>
                        </ul>
                    </div>
                </div>
                <div class="border-t border-gray-100 px-6 py-4">
                    <a href="{{ route('tracking.index') }}"
                       class="inline-flex items-center justify-center w-full px-4 py-3 bg-blue-600 text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-blue-700 transition-colors">
                        Lacak Status <span class="ml-2"></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Requests Overview -->
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">📋 Permintaan Terbaru</h3>
                    <a href="{{ route('requests.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                        Lihat Semua →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No. Permintaan</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penyidik</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tersangka</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sampel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($recentRequests as $request)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $request->request_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ optional($request->investigator)->name ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $request->suspect_name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $request->samples_count }} sampel
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $statusBadges = [
                                                'submitted' => 'bg-blue-100 text-blue-800',
                                                'verified' => 'bg-green-100 text-green-800',
                                                'received' => 'bg-primary-100 text-primary-800',
                                                'in_testing' => 'bg-yellow-100 text-yellow-800',
                                                'analysis' => 'bg-orange-100 text-orange-800',
                                                'quality_check' => 'bg-blue-100 text-blue-800',
                                                'ready_for_delivery' => 'bg-teal-100 text-teal-800',
                                                'completed' => 'bg-green-100 text-green-800',
                                            ];

                                            $statusTexts = [
                                                'submitted' => 'Diterima',
                                                'verified' => 'Diverifikasi',
                                                'received' => 'Diterima Lab',
                                                'in_testing' => 'Sedang Diuji',
                                                'analysis' => 'Analisis',
                                                'quality_check' => 'Quality Check',
                                                'ready_for_delivery' => 'Siap Diserahkan',
                                                'completed' => 'Selesai',
                                            ];

                                            $badgeClass = $statusBadges[$request->status] ?? 'bg-gray-100 text-gray-800';
                                            $statusText = $statusTexts[$request->status] ?? 'Unknown';
                                        @endphp
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $badgeClass }}">
                                            {{ $statusText }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $request->created_at->format('d M Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('requests.show', $request->id) }}"
                                               class="text-blue-600 hover:text-blue-900 text-xs px-2 py-1 border border-blue-600 rounded">
                                                Detail
                                            </a>
                                                          <a href="{{ route('tracking.index') }}"
                                                              class="text-blue-600 hover:text-blue-900 text-xs px-2 py-1 border border-blue-600 rounded">
                                                Track
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Info Panel -->
    <div class="bg-gradient-to-r from-blue-500 to-primary-600 shadow-sm sm:rounded-lg text-white">
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold mb-2">🔬 Sistem Lidik Sidik Terintegrasi</h3>
                        <p class="text-blue-100">
                            Platform lengkap untuk mengelola seluruh proses pengujian laboratorium forensik,
                            dari penerimaan permintaan hingga penyerahan hasil dengan tracking real-time.
                        </p>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold">{{ date('H:i') }}</div>
                        <div class="text-blue-100">{{ date('d M Y') }}</div>
                        <div class="text-blue-100">{{ date('l') }}</div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-sm text-blue-100">Aktif Hari Ini</div>
                        <div class="text-xl font-bold">{{ $stats['in_progress'] }}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-sm text-blue-100">Selesai Minggu Ini</div>
                        <div class="text-xl font-bold">{{ $stats['completed_this_week'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-sm text-blue-100">Target Bulan Ini</div>
                        <div class="text-xl font-bold">{{ number_format($metrics['monthly_target']) }}</div>
                        <div class="text-xs text-blue-200">sampel</div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-sm text-blue-100">Rata-rata Waktu</div>
                        <div class="text-xl font-bold">
                            {{ $metrics['avg_processing_time'] !== null ? number_format($metrics['avg_processing_time'], 1) : 'N/A' }}
                        </div>
                        <div class="text-xs text-blue-200">
                            {{ $metrics['avg_processing_time'] !== null ? 'hari' : 'Belum ada data' }}
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-20 rounded-lg p-3">
                        <div class="text-sm text-blue-100">Kepuasan</div>
                        <div class="text-xl font-bold">
                            {{ $metrics['satisfaction_score'] !== null ? number_format($metrics['satisfaction_score'], 1) : 'N/A' }}
                        </div>
                        <div class="text-xs text-blue-200">
                            {{ $metrics['satisfaction_score'] !== null ? '/5.0' : 'Belum ada data' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enable all links
        document.querySelectorAll('a').forEach(function(link) {
            link.style.cursor = 'pointer';
        });
    });
    </script>
    @endpush
</x-app-layout>
