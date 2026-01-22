@props(['task'])

@php
    $priorityColors = [
        'urgent' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
        'normal' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'low' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
    ];
    $statusColors = [
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400',
    ];
    $priorityLabels = [
        'urgent' => 'Mendesak',
        'high' => 'Tinggi',
        'normal' => 'Normal',
        'low' => 'Rendah',
    ];
    $statusLabels = [
        'pending' => 'Menunggu',
        'in_progress' => 'Dikerjakan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ];
    $isOverdue = $task->due_at && $task->due_at->isPast() && !in_array($task->status, ['completed', 'cancelled']);
@endphp

<div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
     x-data="{ showActions: false }">
    <div class="flex items-start gap-4">
        <!-- Status Checkbox -->
        <div class="flex-shrink-0 pt-0.5">
            @if($task->status === 'completed')
                <button @click="updateStatus({{ $task->id }}, 'pending')"
                        type="button"
                        class="w-5 h-5 rounded-full bg-green-500 flex items-center justify-center text-white hover:bg-green-600 transition-colors"
                        title="Tandai belum selesai">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                </button>
            @else
                <button @click="updateStatus({{ $task->id }}, 'completed')"
                        type="button"
                        class="w-5 h-5 rounded-full border-2 border-gray-300 dark:border-gray-600 hover:border-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors"
                        title="Tandai selesai">
                </button>
            @endif
        </div>

        <!-- Task Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="text-sm font-medium text-pd-text {{ $task->status === 'completed' ? 'line-through opacity-60' : '' }}">
                    {{ $task->title }}
                </h3>

                <!-- Priority Badge -->
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $priorityColors[$task->priority] ?? $priorityColors['normal'] }}">
                    {{ $priorityLabels[$task->priority] ?? $task->priority }}
                </span>

                <!-- Status Badge -->
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$task->status] ?? $statusColors['pending'] }}">
                    {{ $statusLabels[$task->status] ?? $task->status }}
                </span>

                @if($isOverdue)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                        Terlambat
                    </span>
                @endif
            </div>

            @if($task->description)
                <p class="mt-1 text-sm text-pd-text-muted line-clamp-2">
                    {{ $task->description }}
                </p>
            @endif

            <div class="mt-2 flex items-center gap-4 text-xs text-pd-text-muted">
                <!-- Assignee -->
                <div class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    <span>{{ $task->assignee->name ?? 'Tidak ditugaskan' }}</span>
                </div>

                <!-- Due Date -->
                @if($task->due_at)
                    <div class="flex items-center gap-1 {{ $isOverdue ? 'text-red-600 dark:text-red-400' : '' }}">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>{{ $task->due_at->format('d M Y') }}</span>
                    </div>
                @endif

                <!-- Test Request Link -->
                @if($task->testRequest)
                    <a href="{{ route('requests.show', $task->testRequest) }}"
                       class="flex items-center gap-1 hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>{{ $task->testRequest->receipt_number ?? $task->testRequest->request_number }}</span>
                    </a>
                @endif

                <!-- WhatsApp notification indicator -->
                @if($task->notify_whatsapp && $task->notification_sent)
                    <div class="flex items-center gap-1 text-green-600 dark:text-green-400" title="Notifikasi WhatsApp terkirim">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                @endif
            </div>
        </div>

        <!-- Actions -->
        <div class="flex-shrink-0 flex items-center gap-1">
            @if($task->status === 'pending')
                <button @click="updateStatus({{ $task->id }}, 'in_progress')"
                        type="button"
                        class="p-1.5 rounded-lg text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20 transition-colors"
                        title="Mulai kerjakan">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </button>
            @endif

            <button @click="openEditModal({{ $task->toJson() }})"
                    type="button"
                    class="p-1.5 rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors"
                    title="Edit tugas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </button>

            <button @click="deleteTask({{ $task->id }})"
                    type="button"
                    class="p-1.5 rounded-lg text-gray-500 hover:bg-red-50 hover:text-red-600 dark:text-gray-400 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                    title="Hapus tugas">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
    </div>
</div>
