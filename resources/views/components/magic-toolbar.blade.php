{{--
Magic Toolbar Component for WhatsApp Message Editors
Provides variable insertion, text formatting (bold/italic), and staff mentions.

Usage:
<x-magic-toolbar 
    target="form.description"        // x-model binding name
    textarea-id="description-field"  // ID of the textarea element
    :show-variables="true"           // Show variable dropdown (default: true)
    :show-formatting="true"          // Show bold/italic buttons (default: true)
/>
--}}

@props([
    'target' => 'form.message',
    'textareaId' => 'message-textarea',
    'showVariables' => true,
    'showFormatting' => true,
])

@php
    $variables = app(\App\Services\WhatsApp\TemplateService::class)->getMagicVariables();
@endphp

<div 
    x-data="magicToolbar({ 
        target: '{{ $target }}',
        textareaId: '{{ $textareaId }}'
    })"
    class="flex items-center gap-1 mb-2 flex-wrap"
    data-magic-toolbar
>
    @if($showVariables)
    {{-- Variables Dropdown --}}
    <div class="relative" x-data="{ open: false }">
        <button 
            type="button"
            @click="open = !open"
            @click.outside="open = false"
            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition-colors"
            aria-haspopup="true"
            :aria-expanded="open"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <span>Variabel</span>
            <svg class="w-3 h-3" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        
        <div 
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute left-0 z-50 mt-1 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 max-h-64 overflow-y-auto"
            style="display: none;"
        >
            @foreach($variables as $group => $items)
            <div class="py-1">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                    {{ $group }}
                </div>
                @foreach($items as $variable)
                <button
                    type="button"
                    @click="insertVariable('{{ $variable }}'); open = false"
                    class="w-full text-left px-3 py-1.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-primary-50 dark:hover:bg-primary-900/20 hover:text-primary-700 dark:hover:text-primary-300"
                >
                    <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">{!! '{' . $variable . '}' !!}</code>
                </button>
                @endforeach
            </div>
            @if(!$loop->last)
            <div class="border-t border-gray-100 dark:border-gray-700"></div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- AI Magic Button --}}
    <button 
        type="button"
        @click="aiOpen = true"
        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition-colors ml-1"
    >
        <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
        </svg>
        <span>AI Magic</span>
    </button>

    @if($showFormatting)
    {{-- Format Buttons --}}
    <div class="flex items-center gap-0.5 border-l border-gray-200 dark:border-gray-600 pl-2 ml-1">
        {{-- Bold --}}
        <button 
            type="button"
            @click="formatText('bold')"
            class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
            title="Bold (*text*)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 4h8a4 4 0 014 4 4 4 0 01-4 4H6z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 12h9a4 4 0 014 4 4 4 0 01-4 4H6z" />
            </svg>
        </button>
        
        {{-- Italic --}}
        <button 
            type="button"
            @click="formatText('italic')"
            class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
            title="Italic (_text_)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 4h4m-2 0l-2 16m-2 0h4" />
            </svg>
        </button>
        
        {{-- Strikethrough --}}
        <button 
            type="button"
            @click="formatText('strike')"
            class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
            title="Strikethrough (~text~)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.5 12h-11M6 7.5c0-1.5 1.5-3 4.5-3 2 0 3.5 1 4 2M7 16.5c0 1.5 1.5 3 4.5 3 2.5 0 4-1.5 4.5-3" />
            </svg>
        </button>
        
        {{-- Monospace --}}
        <button 
            type="button"
            @click="formatText('mono')"
            class="p-1.5 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors"
            title="Monospace (```text```)"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
            </svg>
        </button>
    </div>
    @endif
    
    {{-- Help hint --}}
    <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
        Klik variabel untuk insert di posisi kursor
    </span>

    {{-- AI Modal --}}
    <template x-teleport="body">
        <div
            x-show="aiOpen"
            style="display: none;"
            class="fixed inset-0 z-[100] overflow-y-auto"
            aria-labelledby="modal-title"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div 
                    x-show="aiOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                    aria-hidden="true"
                    @click="aiOpen = false"
                ></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div 
                    x-show="aiOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full"
                >
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2" id="modal-title">
                                    <span>✨</span> AI Magic Compose
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prompt</label>
                                        <textarea 
                                            x-model="aiPrompt"
                                            rows="3" 
                                            class="shadow-sm focus:ring-primary-500 focus:border-primary-500 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md" 
                                            placeholder="Apa yang ingin Anda tulis?"
                                        ></textarea>
                                        
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button @click="aiPrompt = 'Buatkan pesan formal untuk: '" type="button" class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded text-gray-600 dark:text-gray-300">Formalize</button>
                                            <button @click="aiPrompt = 'Perbaiki ejaan dan tata bahasa: '" type="button" class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded text-gray-600 dark:text-gray-300">Fix Spelling</button>
                                            <button @click="aiPrompt = 'Terjemahkan ke Bahasa Inggris: '" type="button" class="text-xs px-2 py-1 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 rounded text-gray-600 dark:text-gray-300">Translate to English</button>
                                        </div>
                                    </div>

                                    <div x-show="aiResult">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Preview</label>
                                        <textarea 
                                            x-model="aiResult"
                                            readonly
                                            rows="4" 
                                            class="shadow-sm bg-gray-50 dark:bg-gray-900 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-md"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button 
                            type="button" 
                            @click="useAiResult()" 
                            x-show="aiResult"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Use This
                        </button>
                        <button 
                            type="button" 
                            @click="generateAi()" 
                            :disabled="aiLoading || !aiPrompt"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span x-show="!aiLoading">Generate</span>
                            <span x-show="aiLoading" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </button>
                        <button 
                            type="button" 
                            @click="aiOpen = false" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

                                </div>

                                <div x-show="aiResult">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Preview</label>
                                    <textarea 
                                        x-model="aiResult"
                                        readonly
                                        rows="4" 
                                        class="shadow-sm bg-gray-50 dark:bg-gray-900 block w-full sm:text-sm border-gray-300 dark:border-gray-600 dark:text-gray-300 rounded-md"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button 
                        type="button" 
                        @click="useAiResult()" 
                        x-show="aiResult"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm"
                    >
                        Use This
                    </button>
                    <button 
                        type="button" 
                        @click="generateAi()" 
                        :disabled="aiLoading || !aiPrompt"
                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span x-show="!aiLoading">Generate</span>
                        <span x-show="aiLoading" class="flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processing...
                        </span>
                    </button>
                    <button 
                        type="button" 
                        @click="aiOpen = false" 
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>
</div>

@once
@push('scripts')
<script>
function magicToolbar(config) {
    return {
        target: config.target,
        textareaId: config.textareaId,
        
        // AI State
        aiOpen: false,
        aiPrompt: '',
        aiResult: '',
        aiLoading: false,

        getTextarea() {
            return document.getElementById(this.textareaId);
        },
        
        getModelValue() {
            // Navigate the Alpine data to get the model value
            const parts = this.target.split('.');
            let value = this.$data;
            for (const part of parts) {
                if (value && typeof value === 'object' && part in value) {
                    value = value[part];
                } else {
                    return '';
                }
            }
            return value || '';
        },
        
        setModelValue(newValue) {
            // Navigate the Alpine data to set the model value
            const parts = this.target.split('.');
            let obj = this.$data;
            for (let i = 0; i < parts.length - 1; i++) {
                if (obj && typeof obj === 'object' && parts[i] in obj) {
                    obj = obj[parts[i]];
                } else {
                    return;
                }
            }
            if (obj && typeof obj === 'object') {
                obj[parts[parts.length - 1]] = newValue;
            }
        },
        
        insertVariable(variable) {
            const textarea = this.getTextarea();
            if (!textarea) return;
            
            const placeholder = '{' + variable + '}';
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = this.getModelValue();
            const before = text.substring(0, start);
            const after = text.substring(end);
            
            this.setModelValue(before + placeholder + after);
            
            this.$nextTick(() => {
                textarea.focus();
                const newPos = start + placeholder.length;
                textarea.setSelectionRange(newPos, newPos);
            });
        },
        
        formatText(format) {
            const textarea = this.getTextarea();
            if (!textarea) return;
            
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = this.getModelValue();
            const selectedText = text.substring(start, end);
            
            if (start === end) {
                // No selection, insert placeholder
                return;
            }
            
            let wrapper;
            switch (format) {
                case 'bold':
                    wrapper = '*';
                    break;
                case 'italic':
                    wrapper = '_';
                    break;
                case 'strike':
                    wrapper = '~';
                    break;
                case 'mono':
                    wrapper = '```';
                    break;
                default:
                    return;
            }
            
            const before = text.substring(0, start);
            const after = text.substring(end);
            const formatted = wrapper + selectedText + wrapper;
            
            this.setModelValue(before + formatted + after);
            
            this.$nextTick(() => {
                textarea.focus();
                const newEnd = start + formatted.length;
                textarea.setSelectionRange(start, newEnd);
            });
        },

        async generateAi() {
            this.aiLoading = true;
            this.aiResult = '';
            
            try {
                const response = await fetch("{{ route('whatsapp.ai.compose') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        prompt: this.aiPrompt,
                        current_text: this.getModelValue()
                    })
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                this.aiResult = data.text || data.result || data.message || '';
                
            } catch (error) {
                console.error('AI Generation Error:', error);
                alert('Maaf, terjadi kesalahan saat memproses permintaan AI.');
            } finally {
                this.aiLoading = false;
            }
        },

        useAiResult() {
            if (!this.aiResult) return;
            
            this.setModelValue(this.aiResult);
            this.aiOpen = false;
            this.aiPrompt = '';
            this.aiResult = '';
        }
    };
}
</script>
@endpush
@endonce
