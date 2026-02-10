{{-- Partial for Survey Questions Configuration --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 space-y-6">
    <div>
        <h2 class="text-lg font-semibold text-gray-900 text-balance">Pertanyaan Survei Kepuasan</h2>
        <p class="text-sm text-gray-500 mt-1 text-pretty">
            Kelola pertanyaan yang ditampilkan pada form survei kepuasan pelanggan.
        </p>
    </div>

    {{-- Status/Error Messages --}}
    <div
        x-show="client.state.sectionStatus?.survey_questions?.message"
        x-cloak
        :role="client.state.sectionStatus?.survey_questions?.intentClass?.includes('red') ? 'alert' : 'status'"
    >
        <div class="flex items-center gap-2 p-3 rounded-lg"
             :class="client.state.sectionStatus?.survey_questions?.intentClass?.includes('green') ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <span class="text-sm" :class="client.state.sectionStatus?.survey_questions?.intentClass" 
                  x-text="client.state.sectionStatus?.survey_questions?.message"></span>
        </div>
    </div>

    {{-- Questions List --}}
    <div class="space-y-4">
        <template x-for="(question, index) in client.state.form.survey_questions" :key="question.key">
            <div class="border border-gray-200 rounded-lg p-4 space-y-3" 
                 :class="{ 'opacity-50 bg-gray-50': !question.enabled }">
                <div class="flex items-start justify-between gap-4">
                    {{-- Question Label --}}
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Pertanyaan <span x-text="index + 1"></span>
                        </label>
                        <input type="text" x-model="question.label"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Label pertanyaan">
                    </div>
                    
                    {{-- Actions --}}
                    <div class="flex items-center gap-2 pt-6">
                        {{-- Enable/Disable Toggle --}}
                        <label class="relative inline-flex items-center cursor-pointer" title="Aktif/Nonaktif">
                            <input type="checkbox" x-model="question.enabled" class="sr-only peer">
                            <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                        
                        {{-- Move Up --}}
                        <button type="button" @click="moveSurveyQuestion(index, -1)" 
                                :disabled="index === 0"
                                aria-label="Pindahkan pertanyaan ke atas"
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                        </button>
                        
                        {{-- Move Down --}}
                        <button type="button" @click="moveSurveyQuestion(index, 1)"
                                :disabled="index === client.state.form.survey_questions.length - 1"
                                aria-label="Pindahkan pertanyaan ke bawah"
                                class="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        {{-- Delete --}}
                        <button type="button" @click="removeSurveyQuestion(index)"
                                aria-label="Hapus pertanyaan"
                                class="p-1 text-red-400 hover:text-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                {{-- Scale Options --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">
                        Skala Jawaban (nilai 1 sampai <span x-text="question.scale?.length || 4"></span>)
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(scaleLabel, scaleIndex) in question.scale" :key="scaleIndex">
                            <div class="flex items-center gap-1">
                                <span class="text-xs text-gray-400" x-text="scaleIndex + 1"></span>
                                <input type="text" x-model="question.scale[scaleIndex]"
                                       class="w-32 px-2 py-1 text-sm border border-gray-200 rounded focus:ring-1 focus:ring-blue-500"
                                       :placeholder="'Nilai ' + (scaleIndex + 1)">
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Add Question --}}
    <div class="flex items-center gap-3 pt-2">
        <button type="button" @click="addSurveyQuestion()"
                class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg border border-blue-200">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Tambah Pertanyaan
        </button>
    </div>

    {{-- Save Button --}}
    <div class="flex justify-end pt-4 border-t border-gray-100">
        <button type="button" @click="saveSurveyQuestions()"
                :disabled="client.state.loadingSections?.survey_questions"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 disabled:opacity-50 transition">
            <svg x-show="client.state.loadingSections?.survey_questions" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span x-text="client.state.loadingSections?.survey_questions ? 'Menyimpan...' : 'Simpan Pertanyaan'"></span>
        </button>
    </div>
</div>
