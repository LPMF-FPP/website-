@props([
    'columns' => 5,
    'rows' => 8,
])

<div role="status" aria-live="polite" aria-busy="true" class="animate-pulse">
    <div class="overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="min-w-full">
            <div class="grid grid-cols-{{ $columns }} gap-2 bg-gray-50 px-4 py-3">
                @for ($i = 0; $i < $columns; $i++)
                    <div class="h-4 w-24 rounded bg-gray-200"></div>
                @endfor
            </div>
            <div>
                @for ($r = 0; $r < $rows; $r++)
                    <div class="grid grid-cols-{{ $columns }} gap-2 border-t border-gray-100 px-4 py-3">
                        @for ($c = 0; $c < $columns; $c++)
                            <div class="h-4 w-full rounded bg-gray-100"></div>
                        @endfor
                    </div>
                @endfor
            </div>
        </div>
    </div>
    <span class="sr-only">Memuat data...</span>
    <style>
      @media (prefers-color-scheme: dark) {
        .animate-pulse .bg-gray-50 { background-color: rgb(39, 39, 42); }
        .animate-pulse .bg-gray-200 { background-color: rgb(82, 82, 91); }
        .animate-pulse .bg-gray-100 { background-color: rgb(63, 63, 70); }
      }
    </style>
</div>
