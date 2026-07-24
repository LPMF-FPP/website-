<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📝 Survei Kepuasan Pengguna - {{ $request->request_number }}
            </h2>
            <a href="{{ route('delivery.index') }}"
               class="text-gray-600 hover:text-gray-900 px-4 py-2 border border-gray-300 rounded-md">
                ← Kembali
            </a>
        </div>
    </x-slot>

    @php
        $jobCategories = ['TNI', 'Polri', 'ASN', 'Swasta', 'Wirausaha', 'Mahasiswa', 'Siswa'];
        $requestTypes = ['Kimia - Fisika', 'Mikrobiologi'];
        
        $surveyService = app(\App\Services\SurveyQuestionService::class);
        $questionsList = $surveyService->getQuestions();
        $questions = [];
        foreach ($questionsList as $q) {
            $questions[$q['key']] = $q;
        }

        $surveyAnswers = old('answers', $survey->answers ?? []);
        $surveyAnswers = is_array($surveyAnswers) ? $surveyAnswers : [];

        // Pre-fill identity data from investigator if survey doesn't exist
        $investigator = $request->investigator;
        
        $defaultName = $survey->respondent_name ?? $investigator->name ?? '';
        $defaultJobTitle = $survey->respondent_job_title ?? $investigator->rank ?? $investigator->occupation ?? '';
        $defaultInstitution = $survey->respondent_institution ?? $investigator->jurisdiction ?? $investigator->institution ?? '';
        
        $defaultJobCategory = $survey->respondent_job_category ?? '';
        if (empty($defaultJobCategory) && ($investigator->is_polri ?? false)) {
            $defaultJobCategory = 'Polri';
        }

        // Attempt to infer request type from first sample if not set
        $defaultRequestType = $survey->request_type ?? '';
        if (empty($defaultRequestType) && $request->samples->isNotEmpty()) {
            $firstSampleType = $request->samples->first()->test_type;
            if ($firstSampleType && in_array($firstSampleType, $requestTypes)) {
                $defaultRequestType = $firstSampleType;
            }
        }
    @endphp

    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200 space-y-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Survei Kepuasan Pengguna</h3>
                    <p class="text-gray-600">Masukan Anda sangat berharga untuk meningkatkan kualitas pelayanan Sub-Satker Farmapol Pusdokkes Polri.</p>
                </div>

                @if(session('error'))
                    <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($isReadOnly)
                    @if($survey)
                        <div class="rounded border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                            Survei telah tersimpan{{ $survey->submitted_at ? ' pada ' . $survey->submitted_at->format('d/m/Y H:i') : '' }}.
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Nama</div>
                                <div class="mt-1 font-semibold text-gray-900">{{ $survey->respondent_name }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Instansi</div>
                                <div class="mt-1 font-semibold text-gray-900">{{ $survey->respondent_institution }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Kategori Pekerjaan</div>
                                <div class="mt-1 font-semibold text-gray-900">{{ $survey->respondent_job_category }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Jenis Permintaan</div>
                                <div class="mt-1 font-semibold text-gray-900">{{ $survey->request_type }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Rata-rata Skor</div>
                                <div class="mt-1 font-semibold text-gray-900">{{ $survey->score_avg ?? '-' }}</div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-sm font-semibold text-gray-800">Survei Kepuasan</h4>
                            @foreach($questions as $key => $question)
                                <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                    <div class="text-sm font-medium text-gray-700">{{ $question['label'] }}</div>
                                    <div class="mt-1 text-sm text-gray-600">Skor: {{ $surveyAnswers[$key] ?? '-' }}</div>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid gap-6">
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Keluhan/masukan</div>
                                <div class="mt-1 text-gray-900">{{ $survey->complaint ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Tindak lanjut</div>
                                <div class="mt-1 text-gray-900">{{ $survey->follow_up ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs uppercase tracking-wide text-gray-500">Saran/pesan/masukan</div>
                                <div class="mt-1 text-gray-900">{{ $survey->suggestion }}</div>
                            </div>
                        </div>
                    @else
                        <div class="rounded border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                            Survei belum tersedia untuk permintaan ini.
                        </div>
                    @endif

                    <div class="flex justify-end pt-4">
                        <a href="{{ route('delivery.show', $request->id) }}"
                           class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                            Kembali
                        </a>
                    </div>
                @else
                    <form method="POST" action="{{ route('delivery.survey.submit', $request->id) }}" class="space-y-8">
                        @csrf

                        <div class="space-y-4">
                            <h4 class="text-sm font-semibold text-gray-800">Identitas Responden</h4>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="respondent_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Nama <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="respondent_name" id="respondent_name"
                                           value="{{ old('respondent_name', $defaultName) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('respondent_name') border-red-500 @enderror"
                                           required>
                                    @error('respondent_name')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="respondent_institution" class="block text-sm font-medium text-gray-700 mb-2">
                                        Instansi <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="respondent_institution" id="respondent_institution"
                                           value="{{ old('respondent_institution', $defaultInstitution) }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('respondent_institution') border-red-500 @enderror"
                                           required>
                                    @error('respondent_institution')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Kategori pekerjaan <span class="text-red-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-4">
                                    @foreach($jobCategories as $category)
                                        <label class="flex items-center text-sm text-gray-700">
                                            <input type="radio" name="respondent_job_category" value="{{ $category }}"
                                                   class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                                   @checked(old('respondent_job_category', $defaultJobCategory) === $category)
                                                   required>
                                            <span>{{ $category }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('respondent_job_category')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4 class="text-sm font-semibold text-gray-800">Survei Kepuasan</h4>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Jenis permintaan pengujian <span class="text-red-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-4">
                                    @foreach($requestTypes as $type)
                                        <label class="flex items-center text-sm text-gray-700">
                                            <input type="radio" name="request_type" value="{{ $type }}"
                                                   class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                                   @checked(old('request_type', $defaultRequestType) === $type)
                                                   required>
                                            <span>{{ $type }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('request_type')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="flex items-start text-sm text-gray-700">
                                    <input type="checkbox" name="voluntary_statement" value="1"
                                           class="mt-1 mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           @checked(old('voluntary_statement', $survey->voluntary_statement ?? false))
                                           required>
                                    <span>Survei ini saya isi dengan sebenar-benarnya tanpa tekanan dan paksaan</span>
                                </label>
                                @error('voluntary_statement')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="space-y-5">
                                @foreach($questions as $key => $question)
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            {{ $question['label'] }} <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex flex-wrap gap-4">
                                            @foreach($question['scale'] as $index => $label)
                                                @php $value = $index + 1; @endphp
                                                <label class="flex items-center text-sm text-gray-700">
                                                    <input type="radio" name="answers[{{ $key }}]" value="{{ $value }}"
                                                           class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                                           @checked(($surveyAnswers[$key] ?? null) == $value)
                                                           required>
                                                    <span>{{ $label }} ({{ $value }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error("answers.$key")
                                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="complaint" class="block text-sm font-medium text-gray-700 mb-2">
                                    Keluhan/masukan
                                </label>
                                <textarea name="complaint" id="complaint" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('complaint') border-red-500 @enderror"
                                          placeholder="Tuliskan keluhan atau masukan jika ada...">{{ old('complaint', $survey->complaint ?? '') }}</textarea>
                                @error('complaint')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="follow_up" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tindak lanjut
                                </label>
                                <textarea name="follow_up" id="follow_up" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('follow_up') border-red-500 @enderror"
                                          placeholder="Tindak lanjut yang diharapkan...">{{ old('follow_up', $survey->follow_up ?? '') }}</textarea>
                                @error('follow_up')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="suggestion" class="block text-sm font-medium text-gray-700 mb-2">
                                    Saran/pesan/masukan <span class="text-red-500">*</span>
                                </label>
                                <textarea name="suggestion" id="suggestion" rows="4" required
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('suggestion') border-red-500 @enderror"
                                          placeholder="Tuliskan saran atau pesan...">{{ old('suggestion', $survey->suggestion ?? '') }}</textarea>
                                @error('suggestion')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4 pt-4">
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
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
