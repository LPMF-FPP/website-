<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-900 dark:text-slate-100">
                    Survei Kepuasan Pengguna
                </h2>
                <p class="mt-1 break-words text-sm text-gray-700 dark:text-slate-300">
                    Permintaan {{ $request->request_number }}
                </p>
            </div>
            <a href="{{ route('delivery.show', $request->id) }}"
               class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-md border border-gray-400 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-500 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-900">
                Kembali ke detail penyerahan
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
        $investigator = $request->investigator;

        $defaultName = $survey->respondent_name ?? $investigator->name ?? '';
        $defaultInstitution = $survey->respondent_institution ?? $investigator->jurisdiction ?? $investigator->institution ?? '';

        $defaultJobCategory = $survey->respondent_job_category ?? '';
        if (empty($defaultJobCategory) && ($investigator->is_polri ?? false)) {
            $defaultJobCategory = 'Polri';
        }

        $defaultRequestType = $survey->request_type ?? '';
        if (empty($defaultRequestType) && $request->samples->isNotEmpty()) {
            $firstSampleType = $request->samples->first()->test_type;
            if ($firstSampleType && in_array($firstSampleType, $requestTypes)) {
                $defaultRequestType = $firstSampleType;
            }
        }

        $fieldClasses = 'min-h-11 w-full rounded-md border-gray-500 bg-white text-gray-900 shadow-sm focus:border-primary-700 focus:ring-primary-700 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-400 dark:focus:border-primary-400 dark:focus:ring-primary-400';
        $labelClasses = 'mb-2 block max-w-full break-words text-sm font-medium text-gray-800 dark:text-slate-200';
        $sectionHeadingClasses = 'text-base font-semibold text-gray-900 dark:text-slate-100';
        $requiredMarkClasses = 'text-red-700 dark:text-red-300';
    @endphp

    <div class="mx-auto max-w-5xl">
        <section class="overflow-hidden rounded-lg border border-gray-300 bg-white dark:border-slate-700 dark:bg-slate-800" aria-labelledby="survey-introduction">
            <div class="space-y-8 p-4 sm:p-6 lg:p-8">
                <div class="max-w-3xl">
                    <h3 id="survey-introduction" class="text-lg font-semibold text-gray-900 dark:text-slate-100">Masukan Anda membantu meningkatkan layanan</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-slate-300">
                        Berikan penilaian sesuai pengalaman Anda pada layanan pengujian Sub-Satker Farmapol Pusdokkes Polri.
                        @unless ($isReadOnly)
                            Isian bertanda <span class="font-semibold text-red-700 dark:text-red-300">*</span> wajib dilengkapi.
                        @endunless
                    </p>
                </div>

                @if (session('error'))
                    <div class="rounded-md border border-red-700 bg-red-50 p-4 text-sm text-red-900 dark:border-red-300 dark:bg-red-950 dark:text-red-100" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-md border border-red-700 bg-red-50 p-4 text-sm text-red-900 dark:border-red-300 dark:bg-red-950 dark:text-red-100" role="alert" aria-labelledby="survey-errors-heading" tabindex="-1">
                        <h3 id="survey-errors-heading" class="font-semibold">Periksa kembali isian survei</h3>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($isReadOnly)
                    @if ($survey)
                        <div class="rounded-md border border-emerald-800 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-300 dark:bg-emerald-950 dark:text-emerald-100" role="status">
                            Survei telah tersimpan{{ $survey->submitted_at ? ' pada '.$survey->submitted_at->format('d/m/Y H:i') : '' }}. Jawaban tidak dapat diubah.
                        </div>

                        <section class="space-y-4" aria-labelledby="respondent-summary-heading">
                            <h3 id="respondent-summary-heading" class="{{ $sectionHeadingClasses }}">Identitas responden</h3>
                            <dl class="grid grid-cols-1 gap-x-8 gap-y-5 break-words sm:grid-cols-2">
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Nama</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->respondent_name ?: 'Tidak diisi' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Instansi</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->respondent_institution ?: 'Tidak diisi' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Kategori pekerjaan</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->respondent_job_category ?: 'Tidak diisi' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Jenis permintaan</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->request_type ?: 'Tidak diisi' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Rata-rata skor</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->score_avg ?? 'Tidak tersedia' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Pernyataan pengisian</dt>
                                    <dd class="mt-1 font-medium text-gray-900 dark:text-slate-100">{{ $survey->voluntary_statement ? 'Disetujui' : 'Tidak tersedia' }}</dd>
                                </div>
                            </dl>
                        </section>

                        <section class="space-y-4" aria-labelledby="rating-summary-heading">
                            <h3 id="rating-summary-heading" class="{{ $sectionHeadingClasses }}">Penilaian layanan</h3>
                            <dl class="divide-y divide-gray-300 break-words dark:divide-slate-600">
                                @foreach ($questions as $key => $question)
                                    @php
                                        $answer = $surveyAnswers[$key] ?? null;
                                        $answerLabel = is_numeric($answer) ? ($question['scale'][(int) $answer - 1] ?? null) : null;
                                    @endphp
                                    <div class="grid grid-cols-1 gap-1 py-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.7fr)] sm:gap-6">
                                        <dt class="text-sm font-medium text-gray-900 dark:text-slate-100">{{ $question['label'] }}</dt>
                                        <dd class="text-sm text-gray-800 dark:text-slate-200">
                                            {{ $answerLabel ? $answerLabel.' ('.$answer.')' : 'Tidak diisi' }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </section>

                        <section class="space-y-5" aria-labelledby="feedback-summary-heading">
                            <h3 id="feedback-summary-heading" class="{{ $sectionHeadingClasses }}">Masukan</h3>
                            <dl class="grid grid-cols-1 gap-5 break-words">
                                @if (filled($survey->complaint))
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Keluhan/masukan</dt>
                                    <dd class="mt-1 whitespace-pre-line text-gray-900 dark:text-slate-100">{{ $survey->complaint ?: 'Tidak diisi' }}</dd>
                                </div>
                                @endif
                                @if (filled($survey->follow_up))
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Tindak lanjut</dt>
                                    <dd class="mt-1 whitespace-pre-line text-gray-900 dark:text-slate-100">{{ $survey->follow_up ?: 'Tidak diisi' }}</dd>
                                </div>
                                @endif
                                <div>
                                    <dt class="text-sm text-gray-700 dark:text-slate-300">Saran atau keluhan tentang layanan</dt>
                                    <dd class="mt-1 whitespace-pre-line text-gray-900 dark:text-slate-100">{{ $survey->suggestion ?: 'Tidak diisi' }}</dd>
                                </div>
                            </dl>
                        </section>
                    @else
                        <div class="rounded-md border border-amber-700 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-300 dark:bg-amber-950 dark:text-amber-100" role="status">
                            Survei belum tersedia untuk permintaan ini.
                        </div>
                    @endif

                    <div class="border-t border-gray-300 pt-5 dark:border-slate-600">
                        <a href="{{ route('delivery.show', $request->id) }}"
                           class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-400 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-500 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-800">
                            Kembali ke detail penyerahan
                        </a>
                    </div>
                @else
                    <form method="POST" action="{{ route('delivery.survey.submit', $request->id) }}" class="space-y-9"
                          x-data="{ submitting: false, unavailable: {{ count($questions) === 0 ? 'true' : 'false' }} }"
                          @submit="if (submitting || unavailable) { $event.preventDefault(); } else { submitting = true; }"
                          @pageshow.window="submitting = false">
                        @csrf

                        <section class="space-y-5" aria-labelledby="respondent-heading">
                            <h3 id="respondent-heading" class="{{ $sectionHeadingClasses }}">Identitas responden</h3>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="respondent_name" class="{{ $labelClasses }}">
                                        Nama <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                    </label>
                                    <input type="text" name="respondent_name" id="respondent_name"
                                           value="{{ old('respondent_name', $defaultName) }}"
                                           class="{{ $fieldClasses }} @error('respondent_name') border-red-700 dark:border-red-300 @enderror"
                                           @error('respondent_name') aria-invalid="true" aria-describedby="respondent_name-error" @enderror
                                           autocomplete="name" maxlength="255" required>
                                    @error('respondent_name')
                                        <p id="respondent_name-error" class="mt-2 text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label for="respondent_institution" class="{{ $labelClasses }}">
                                        Instansi <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                    </label>
                                    <input type="text" name="respondent_institution" id="respondent_institution"
                                           value="{{ old('respondent_institution', $defaultInstitution) }}"
                                           class="{{ $fieldClasses }} @error('respondent_institution') border-red-700 dark:border-red-300 @enderror"
                                           @error('respondent_institution') aria-invalid="true" aria-describedby="respondent_institution-error" @enderror
                                           autocomplete="organization" maxlength="255" required>
                                    @error('respondent_institution')
                                        <p id="respondent_institution-error" class="mt-2 text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <fieldset class="min-w-0 space-y-2">
                                <legend class="{{ $labelClasses }}">
                                    Kategori pekerjaan <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                </legend>
                                <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach ($jobCategories as $category)
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-within:ring-primary-400">
                                            <input type="radio" name="respondent_job_category" value="{{ $category }}"
                                                   class="h-5 w-5 shrink-0 border-gray-500 text-primary-700 focus:ring-primary-700 dark:border-slate-400 dark:bg-slate-900 dark:text-primary-400 dark:focus:ring-primary-400"
                                                   @checked(old('respondent_job_category', $defaultJobCategory) === $category)
                                                   @error('respondent_job_category') aria-invalid="true" aria-describedby="respondent_job_category-error" @enderror
                                                   required>
                                            <span>{{ $category }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('respondent_job_category')
                                    <p id="respondent_job_category-error" class="text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        </section>

                        <section class="space-y-6 border-t border-gray-300 pt-7 dark:border-slate-600" aria-labelledby="ratings-heading">
                            <div>
                                <h3 id="ratings-heading" class="{{ $sectionHeadingClasses }}">Penilaian layanan</h3>
                                <p class="mt-1 text-sm text-gray-700 dark:text-slate-300">Pilih satu jawaban untuk setiap pertanyaan.</p>
                            </div>

                            @if (count($questions) === 0)
                                <p class="rounded-md border border-amber-700 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-300 dark:bg-amber-950 dark:text-amber-100" role="status">
                                    Survei belum dapat dikirim karena belum ada pertanyaan aktif. Silakan hubungi pengelola layanan.
                                </p>
                            @endif

                            <fieldset class="min-w-0 space-y-2">
                                <legend class="{{ $labelClasses }}">
                                    Jenis permintaan pengujian <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                </legend>
                                <div class="grid gap-1 sm:grid-cols-2">
                                    @foreach ($requestTypes as $type)
                                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-within:ring-primary-400">
                                            <input type="radio" name="request_type" value="{{ $type }}"
                                                   class="h-5 w-5 shrink-0 border-gray-500 text-primary-700 focus:ring-primary-700 dark:border-slate-400 dark:bg-slate-900 dark:text-primary-400 dark:focus:ring-primary-400"
                                                   @checked(old('request_type', $defaultRequestType) === $type)
                                                   @error('request_type') aria-invalid="true" aria-describedby="request_type-error" @enderror
                                                   required>
                                            <span>{{ $type }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('request_type')
                                    <p id="request_type-error" class="text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <div class="divide-y divide-gray-300 dark:divide-slate-600">
                                @foreach ($questions as $key => $question)
                                    <fieldset id="answers-{{ $key }}" class="min-w-0 space-y-2 py-5 first:pt-0 last:pb-0">
                                        <legend class="{{ $labelClasses }}">
                                            {{ $question['label'] }} <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                        </legend>
                                        <div class="grid gap-1 sm:grid-cols-2 lg:grid-cols-4">
                                            @foreach ($question['scale'] as $index => $label)
                                                @php $value = $index + 1; @endphp
                                                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm leading-5 text-gray-800 hover:bg-gray-100 focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-700 dark:text-slate-200 dark:hover:bg-slate-700 dark:focus-within:ring-primary-400">
                                                    <input type="radio" name="answers[{{ $key }}]" value="{{ $value }}"
                                                           class="h-5 w-5 shrink-0 border-gray-500 text-primary-700 focus:ring-primary-700 dark:border-slate-400 dark:bg-slate-900 dark:text-primary-400 dark:focus:ring-primary-400"
                                                           @checked(($surveyAnswers[$key] ?? null) == $value)
                                                           @error("answers.$key") aria-invalid="true" aria-describedby="answers-{{ $key }}-error" @enderror
                                                           required>
                                                    <span class="min-w-0 break-words">{{ $label }} ({{ $value }})</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @error("answers.$key")
                                            <p id="answers-{{ $key }}-error" class="text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                        @enderror
                                    </fieldset>
                                @endforeach
                            </div>
                        </section>

                        <section class="space-y-5 border-t border-gray-300 pt-7 dark:border-slate-600" aria-labelledby="feedback-heading">
                            <div>
                                <h3 id="feedback-heading" class="{{ $sectionHeadingClasses }}">Masukan</h3>
                            </div>

                            <div>
                                <label for="suggestion" class="{{ $labelClasses }}">
                                    Saran atau keluhan tentang layanan <span class="{{ $requiredMarkClasses }}" aria-label="wajib diisi">*</span>
                                </label>
                                <p id="suggestion-help" class="mb-3 text-sm text-gray-700 dark:text-slate-300">Ceritakan hal yang perlu diperbaiki. Jika ada kendala, jelaskan kendala dan penyelesaian yang Anda harapkan.</p>
                                <textarea name="suggestion" id="suggestion" rows="4" required
                                          class="{{ $fieldClasses }} @error('suggestion') border-red-700 dark:border-red-300 @enderror"
                                          aria-describedby="suggestion-help{{ $errors->has('suggestion') ? ' suggestion-error' : '' }}"
                                          @error('suggestion') aria-invalid="true" @enderror>{{ old('suggestion', $survey->suggestion ?? '') }}</textarea>
                                @error('suggestion')
                                    <p id="suggestion-error" class="mt-2 text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                                @enderror
                            </div>
                        </section>

                        <div class="border-t border-gray-300 pt-6 dark:border-slate-600">
                            <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-md py-2 text-sm leading-6 text-gray-800 focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-700 dark:text-slate-200 dark:focus-within:ring-primary-400">
                                <input type="checkbox" name="voluntary_statement" value="1"
                                       class="mt-1 h-5 w-5 shrink-0 rounded border-gray-500 text-primary-700 focus:ring-primary-700 dark:border-slate-400 dark:bg-slate-900 dark:text-primary-400 dark:focus:ring-primary-400"
                                       @checked(old('voluntary_statement', $survey->voluntary_statement ?? false))
                                       @error('voluntary_statement') aria-invalid="true" aria-describedby="voluntary_statement-error" @enderror
                                       required>
                                <span>Saya mengisi survei ini dengan sebenar-benarnya, tanpa tekanan dan paksaan. <span class="{{ $requiredMarkClasses }}" aria-label="wajib disetujui">*</span></span>
                            </label>
                            @error('voluntary_statement')
                                <p id="voluntary_statement-error" class="mt-1 text-sm font-medium text-red-800 dark:text-red-200">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex flex-col gap-3 border-t border-gray-300 pt-6 dark:border-slate-600 sm:flex-row sm:justify-end">
                            <a href="{{ route('delivery.show', $request->id) }}"
                               class="inline-flex min-h-11 items-center justify-center rounded-md border border-gray-400 px-5 py-2 text-sm font-medium text-gray-800 hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-700 focus-visible:ring-offset-2 dark:border-slate-500 dark:text-slate-100 dark:hover:bg-slate-700 dark:focus-visible:ring-primary-400 dark:focus-visible:ring-offset-slate-800">
                                Kembali ke detail
                            </a>
                            <button type="submit"
                                    class="inline-flex min-h-11 items-center justify-center rounded-md bg-green-700 px-5 py-2 text-sm font-semibold text-white hover:bg-green-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-75 dark:bg-green-500 dark:text-slate-950 dark:hover:bg-green-400 dark:focus-visible:ring-green-300 dark:focus-visible:ring-offset-slate-800"
                                    x-bind:disabled="submitting || unavailable"
                                    @disabled(count($questions) === 0)>
                                <span x-show="!submitting">Kirim survei</span>
                                <span x-cloak x-show="submitting" aria-live="polite">Mengirim survei…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </section>
    </div>
</x-app-layout>
