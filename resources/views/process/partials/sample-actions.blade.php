<div class="flex w-full flex-col items-stretch gap-2">
    @if($sample->current_process)
        @php
            $process = $sample->current_process;
            $isStarted = $process->started_at !== null;
            $isCompleted = $process->completed_at !== null;
        @endphp
        <div class="relative w-full text-left" x-data="{ open: false }">
            <div class="inline-flex min-h-11 w-full overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
                <a
                    href="{{ route('testing.processes.edit', $process) }}"
                    class="inline-flex min-h-11 min-w-0 flex-1 items-center justify-center px-3 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-50 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                >
                    Kerjakan
                </a>
                <button
                    type="button"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-label="Tampilkan menu aksi lainnya"
                    class="inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center border-l border-gray-300 text-gray-600 transition hover:bg-gray-50 focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                >
                    <x-icon name="chevron-down" size="sm" :decorative="true" />
                </button>
            </div>

            <div
                x-show="open"
                @click.outside="open = false"
                @keydown.escape.window="open = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                class="absolute right-0 z-pd-overlay mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2"
                style="display: none;"
            >
                <div class="py-1">
                    @if(!$isStarted)
                        <button
                            type="button"
                            @click="$dispatch('process-start', { processId: {{ $process->id }}, sampleId: {{ $sample->id }} }); open = false"
                            class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                        >
                            <x-icon name="play" size="sm" class="text-green-600" :decorative="true" />
                            Mulai Proses
                        </button>
                    @endif

                    @if($isStarted && !$isCompleted)
                        <button
                            type="button"
                            @click="$dispatch('process-complete', { processId: {{ $process->id }}, sampleId: {{ $sample->id }} }); open = false"
                            class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                        >
                            <x-icon name="check-circle" size="sm" class="text-primary-600" :decorative="true" />
                            Selesaikan Proses
                        </button>
                    @endif

                    @if($lhuProcess && $lhuNumber && $lhuPreviewUrl && $lhuDownloadUrl)
                        <a
                            href="{{ $lhuPreviewUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                        >
                            <x-icon name="document-text" size="sm" class="text-primary-600" :decorative="true" />
                            Buka LHU
                        </a>
                        <a
                            href="{{ $lhuDownloadUrl }}"
                            class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                        >
                            <x-icon name="arrow-down-tray" size="sm" class="text-primary-600" :decorative="true" />
                            Unduh LHU
                        </a>
                    @endif

                    <button
                        type="button"
                        @click="$dispatch('process-quick-view', { processId: {{ $process->id }} }); open = false"
                        class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-primary-600"
                    >
                        <x-icon name="eye" size="sm" class="text-gray-500" :decorative="true" />
                        Quick View
                    </button>
                </div>
            </div>
        </div>
    @elseif($lhuProcess && $lhuNumber && $lhuPreviewUrl && $lhuDownloadUrl)
        <a
            href="{{ $lhuPreviewUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-semibold text-primary-700 transition hover:bg-primary-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
        >
            <x-icon name="document-text" size="sm" :decorative="true" />
            Buka LHU
        </a>
        <a
            href="{{ $lhuDownloadUrl }}"
            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
        >
            <x-icon name="arrow-down-tray" size="sm" :decorative="true" />
            Unduh LHU
        </a>
    @elseif($sample->needs_additional_review && auth()->user()?->hasAnyPermission(['pengujian.create', 'pengujian.edit']))
        <a
            href="{{ route('testing.additional-samples.review', [$testRequest, $sample]) }}"
            class="inline-flex min-h-11 w-full items-center justify-center whitespace-nowrap rounded-md bg-amber-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-amber-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-600"
        >
            Kaji Ulang Sampel
        </a>
    @else
        <span class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-gray-50 px-3 py-2 text-sm font-medium text-gray-500 ring-1 ring-inset ring-gray-200">
            Tidak ada aksi
        </span>
    @endif
</div>
