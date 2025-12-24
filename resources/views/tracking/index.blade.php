<x-app-layout>


    <x-slot name="header">
        <x-page-header
            title="🔍 Tracking Pengujian Sampel"
            :breadcrumbs="[[ 'label' => 'Pelacakan' ]]"
        />
    </x-slot>





    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">


        <div class="bg-white shadow-sm sm:rounded-lg">


            <div class="p-6 bg-white border-b border-gray-200">





                <!-- Header Info -->


                <div class="text-center mb-8">


                    <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-100 rounded-full mb-4">


                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">


                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>


                        </svg>


                    </div>


                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Lacak Status Pengujian</h3>


                    <p class="text-gray-600 max-w-2xl mx-auto">


                        Masukkan nomor permintaan (resi) pengujian Anda untuk melihat status terkini dan progress pengujian sampel di laboratorium Pusdokkes Polri.


                    </p>


                </div>





                <!-- Tracking Form -->


                <div class="max-w-md mx-auto">


                    @if ($errors->any())


                        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">


                            <div class="flex">


                                <svg class="w-5 h-5 text-red-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">


                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>


                                </svg>


                                <div>


                                    @foreach ($errors->all() as $error)


                                        <p class="text-sm">{{ $error }}</p>


                                    @endforeach


                                </div>


                            </div>


                        </div>


                    @endif





                    <form action="{{ route('public.track') }}" method="POST" class="space-y-4">


                        @csrf





                        <div>


                            <label for="tracking_number" class="block text-sm font-medium text-gray-700 mb-2">


                                Nomor Permintaan (Resi)


                            </label>


                            <div class="relative">


                                <input type="text"


                                       name="tracking_number"


                                       id="tracking_number"


                                       required


                                       value="{{ old('tracking_number') }}"


                                       placeholder="Contoh: LPMF0011225"


                                       class="w-full px-4 py-3 pl-12 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 text-lg">


                                <div class="absolute inset-y-0 left-0 flex items-center pl-3">


                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">


                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"></path>


                                    </svg>


                                </div>


                            </div>


                            <p class="mt-2 text-sm text-gray-500">


                                Nomor permintaan dapat ditemukan pada tanda terima yang diberikan saat penyerahan sampel.


                            </p>


                        </div>





                        <button type="submit"


                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 ease-in-out font-medium text-lg">


                            🔍 Lacak Sekarang


                        </button>


                    </form>


                </div>





                <!-- Sample Numbers Info -->


                <div class="mt-12 bg-gray-50 rounded-lg p-6">
                <div class="mt-12 bg-white rounded-lg border border-gray-200 p-6">
                    <h4 class="text-lg font-semibold text-gray-900 mb-3">🧭 Tahapan Proses Pengujian</h4>
                    <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                        <li><span class="font-medium">Penerimaan</span> &mdash; permintaan dan sampel dicatat oleh admin.</li>
                        <li><span class="font-medium">Preparasi Sampel</span> &mdash; lab melakukan persiapan awal sebelum analisa.</li>
                        <li><span class="font-medium">Pengujian pada Instrumen</span> &mdash; sampel dianalisis menggunakan peralatan laboratorium.</li>
                        <li><span class="font-medium">Hasil selesai menunggu TTD pimpinan</span> &mdash; laporan disahkan pimpinan.</li>
                        <li><span class="font-medium">Penyerahan</span> &mdash; hasil resmi diserahkan kepada penyidik.</li>
                    </ol>
                </div>



                    <h4 class="text-lg font-semibold text-gray-900 mb-4">📋 Contoh Nomor Permintaan</h4>


                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">


                        <div class="bg-white p-4 rounded-lg border">


                            <div class="font-medium text-gray-900 mb-1">LPMF0011225</div>


                            <div class="text-gray-500">Status: 🧪 Sedang Diuji</div>


                        </div>


                        <div class="bg-white p-4 rounded-lg border">


                            <div class="font-medium text-gray-900 mb-1">LPMF0021225</div>


                            <div class="text-gray-500">Status: 📋 Siap Diserahkan</div>


                        </div>


                        <div class="bg-white p-4 rounded-lg border">


                            <div class="font-medium text-gray-900 mb-1">LPMF0031225</div>


                            <div class="text-gray-500">Status: 🎉 Sudah Diserahkan</div>


                        </div>


                    </div>


                    <p class="text-xs text-gray-500 mt-4">


                        * Contoh di atas hanya untuk demonstrasi. Gunakan nomor permintaan yang sebenarnya untuk tracking.


                    </p>


                </div>


            </div>


        </div>


    </div>


</x-app-layout>


