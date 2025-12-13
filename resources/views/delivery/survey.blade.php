<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 Survei Kepuasan Pelanggan - {{ $request->request_number }}
            </h2>
            <a href="{{ route('delivery.index') }}"
               class="text-gray-600 hover:text-gray-900 px-4 py-2 border border-gray-300 rounded-md">
                ← Kembali
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Bagaimana pengalaman Anda dengan layanan kami?</h3>
                    <p class="text-gray-600">Masukan Anda sangat berharga untuk meningkatkan kualitas pelayanan Sub-Satker Farmapol Pusdokkes Polri.</p>
                </div>

                <form method="POST" action="{{ route('delivery.survey.submit', $request->id) }}" class="space-y-6">
                    @csrf

                    <!-- Rating Questions -->
                    <div class="space-y-6">
                        <!-- Overall Satisfaction -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Kepuasan Secara Keseluruhan <span class="text-red-500">*</span>
                            </label>
                            <div class="flex space-x-4">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" name="overall_satisfaction" value="{{ $i }}" required
                                               class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-sm">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Sangat Tidak Puas, 5 = Sangat Puas</p>
                        </div>

                        <!-- Service Quality -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Kualitas Pelayanan <span class="text-red-500">*</span>
                            </label>
                            <div class="flex space-x-4">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" name="service_quality" value="{{ $i }}" required
                                               class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-sm">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Sangat Buruk, 5 = Sangat Baik</p>
                        </div>

                        <!-- Timeliness -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Ketepatan Waktu <span class="text-red-500">*</span>
                            </label>
                            <div class="flex space-x-4">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" name="timeliness" value="{{ $i }}" required
                                               class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-sm">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Sangat Lambat, 5 = Sangat Cepat</p>
                        </div>

                        <!-- Staff Professionalism -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Profesionalisme Petugas <span class="text-red-500">*</span>
                            </label>
                            <div class="flex space-x-4">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" name="staff_professionalism" value="{{ $i }}" required
                                               class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="text-sm">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Tidak Profesional, 5 = Sangat Profesional</p>
                        </div>
                    </div>

                    <!-- Comments -->
                    <div>
                        <label for="comments" class="block text-sm font-medium text-gray-700 mb-2">
                            Komentar dan Masukan
                        </label>
                        <textarea name="comments" id="comments" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Bagikan pengalaman Anda dengan layanan kami..."></textarea>
                    </div>

                    <!-- Suggestions -->
                    <div>
                        <label for="suggestions" class="block text-sm font-medium text-gray-700 mb-2">
                            Saran Perbaikan
                        </label>
                        <textarea name="suggestions" id="suggestions" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Apa yang dapat kami lakukan untuk meningkatkan pelayanan?"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <a href="{{ route('delivery.show', $request->id) }}"
                           class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Batal
                        </a>

                        <button type="submit"
                                class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
                            📝 Kirim Survei
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
