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
</div>

@once
@push('scripts')
<script>
function magicToolbar(config) {
    return {
        target: config.target,
        textareaId: config.textareaId,
        
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
        }
    };
}
</script>
@endpush
@endonce
