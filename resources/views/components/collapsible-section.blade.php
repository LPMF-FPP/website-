@props(['id', 'title', 'count' => null, 'open' => false])

<div x-data="{ expanded: {{ $open ? 'true' : 'false' }} }" class="bg-white shadow-sm sm:rounded-lg overflow-hidden border border-gray-200">
    <button 
        @click="expanded = !expanded" 
        type="button" 
        class="w-full px-6 py-4 flex items-center justify-between bg-gray-50 hover:bg-gray-100 transition-colors duration-150 focus:outline-none"
        :aria-expanded="expanded"
        aria-controls="section-{{ $id }}">
        
        <div class="flex items-center space-x-3">
            <svg 
                class="w-5 h-5 text-gray-400 transform transition-transform duration-200" 
                :class="{ 'rotate-90': expanded }" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
            <h3 class="text-lg font-semibold text-gray-900">
                {{ $title }}
                @if($count !== null)
                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">
                        {{ $count }}
                    </span>
                @endif
            </h3>
        </div>

        <span class="text-sm text-gray-500" x-text="expanded ? 'Tutup' : 'Buka'"></span>
    </button>

    <div 
        id="section-{{ $id }}" 
        x-show="expanded" 
        x-collapse
        class="border-t border-gray-200">
        <div class="p-6">
            {{ $slot }}
        </div>
    </div>
</div>
