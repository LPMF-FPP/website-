@props(['target' => 'form.message', 'textareaId' => 'message-textarea', 'showVariables' => true, 'showFormatting' => true, 'contextType' => 'general'])
@php
    $catalog = app(\App\Services\AI\AiPlaceholderCatalog::class);
    $variables = $contextType === 'reminder' ? array_merge($catalog->keys('countdown'), $catalog->keys('reminder')) : $catalog->keys($contextType);
    $aiStatus = app(\App\Services\AI\AiConfiguration::class)->status();
@endphp
<div x-data="magicToolbar({
    target: @js($target), textareaId: @js($textareaId), composeUrl: @js(route('whatsapp.ai.compose')), settingsUrl: @js(route('whatsapp.settings.index')), destination: @js($aiStatus['ai_base_url']), revision: @js($aiStatus['ai_revision']),
    preserveTokens: @js($variables),
    contextType: () => @js($contextType) === 'reminder' ? (['countdown', 'iso_countdown'].includes(form.type) ? form.type : 'reminder') : @js($contextType),
    recordIdentity: () => form.id, editorSession: () => aiEditorSession
})" class="flex flex-wrap items-center gap-2 mb-2" data-magic-toolbar>
    @if($showVariables)
        <div x-data="{ open: false }" class="relative" @keydown.escape.stop="open = false">
            <button type="button" @click="open = !open" :aria-expanded="open" class="min-h-[44px] px-3 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Variabel</button>
            <div x-show="open" x-cloak @click.outside="open = false" class="absolute z-50 bg-white dark:bg-gray-800 shadow-lg rounded w-64 max-h-64 overflow-y-auto">
                @foreach($variables as $variable)
                    <button type="button" @if($contextType === 'reminder') x-show="['countdown', 'iso_countdown'].includes(context()) === @js(in_array($variable, $catalog->keys('countdown'), true))" @endif @click="insertVariable(@js($variable)); open = false" class="block min-h-[44px] px-3 w-full text-left text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">{{ '{'.$variable.'}' }}</button>
                @endforeach
            </div>
        </div>
    @endif
    @if($showFormatting)
        @foreach(['bold' => 'Tebal', 'italic' => 'Miring', 'strike' => 'Coret', 'mono' => 'Kode'] as $format => $label)
            <button type="button" @click="formatText(@js($format))" class="min-h-[44px] px-2 text-sm rounded text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">{{ $label }}</button>
        @endforeach
    @endif
    <button type="button" @click="openAiModal()" class="min-h-[44px] px-3 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Susun dengan AI</button>
    <template x-teleport="body">
        <div id="{{ $textareaId }}-ai-dialog" x-show="aiOpen" x-cloak x-trap.inert.noscroll="aiOpen" @keydown.escape.stop="closeAiModal()" role="dialog" aria-modal="true" aria-label="Susun draf WhatsApp dengan AI" class="fixed inset-0 z-[9999] overflow-y-auto bg-black/50 p-4">
            <div @click.outside="closeAiModal()" class="relative mx-auto my-8 w-full max-w-lg rounded-lg bg-white dark:bg-gray-800 p-5 text-gray-900 dark:text-gray-100 space-y-4 shadow-xl">
                <h3 class="text-lg font-semibold">Susun draf dengan AI</h3>
                <p class="text-sm break-words">Instruksi dikirim ke <span x-text="destination"></span>. Jangan masukkan data perkara atau data pribadi. Hasil harus diperiksa; tidak ada pesan WhatsApp yang dikirim.</p>
                <label class="block text-sm font-medium">Instruksi
                    <textarea x-ref="aiPrompt" x-model="aiPrompt" maxlength="2000" rows="3" class="mt-1 block w-full rounded border-gray-500 dark:border-gray-400 dark:bg-gray-700 dark:text-white focus:ring-primary-700 dark:focus:ring-primary-300"></textarea>
                </label>
                <div class="flex flex-wrap gap-2" aria-label="Contoh instruksi">
                    <button type="button" @click="aiPrompt = 'Tulis pesan formal mengenai: '" class="min-h-[44px] rounded border border-gray-500 px-3 text-sm focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Bahasa formal</button>
                    <button type="button" @click="aiPrompt = 'Perbaiki ejaan teks berikut: '" class="min-h-[44px] rounded border border-gray-500 px-3 text-sm focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Perbaiki ejaan</button>
                    <button type="button" @click="aiPrompt = 'Terjemahkan teks berikut ke Bahasa Inggris: '" class="min-h-[44px] rounded border border-gray-500 px-3 text-sm focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Terjemahkan</button>
                </div>
                <label class="flex items-center gap-3 min-h-[44px] text-sm"><input type="checkbox" x-model="shareDraft" class="rounded border-gray-500 focus:ring-primary-700 dark:focus:ring-primary-300">Saya setuju mengirim draf saat ini ke tujuan AI tersebut.</label>
                <p x-show="aiLoading" role="status" aria-live="polite" class="text-sm">Menyusun pratinjau. Tunggu sebentar.</p>
                <p x-show="!aiResult && !aiLoading && !aiError" class="text-sm text-gray-600 dark:text-gray-300">Pratinjau muncul setelah Anda membuat draf.</p>
                <p x-show="aiResult" role="status" aria-live="polite" class="text-sm">Pratinjau siap. Periksa sebelum menerapkan ke draf.</p>
                <label x-show="aiResult" class="block text-sm font-medium">Pratinjau
                    <textarea :value="aiResult" readonly rows="5" class="mt-1 block w-full rounded border-gray-500 bg-gray-50 dark:bg-gray-900 dark:border-gray-400 dark:text-gray-100"></textarea>
                </label>
                <div x-show="aiError" role="alert" class="text-sm text-amber-900 dark:text-amber-200"><p x-text="aiError"></p><p x-show="aiRequestId">Ref: <span x-text="aiRequestId"></span></p></div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="generateAi()" :disabled="aiLoading || !aiPrompt.trim()" class="min-h-[44px] px-4 rounded bg-primary-700 text-white focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300 disabled:opacity-50"><span x-text="aiLoading ? 'Menyusun...' : 'Buat draf'"></span></button>
                    <button type="button" x-show="aiResult" @click="useAiResult()" :disabled="aiLoading || aiApplying" x-text="aiApplying ? 'Memeriksa konfigurasi…' : 'Terapkan ke draf'" class="min-h-[44px] px-4 rounded bg-green-700 text-white focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Terapkan ke draf</button>
                    <p x-show="aiApplying" role="status" aria-live="polite" class="text-sm">Memeriksa konfigurasi AI terbaru sebelum menerapkan pratinjau.</p>
                    <button type="button" @click="closeAiModal()" class="min-h-[44px] px-4 rounded border border-gray-500 dark:border-gray-400 focus-visible:ring-2 focus-visible:ring-primary-700 dark:focus-visible:ring-primary-300">Batal</button>
                </div>
            </div>
        </div>
    </template>
</div>
