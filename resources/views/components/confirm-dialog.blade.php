{{-- Confirm Dialog Component --}}
<div x-data="confirmDialogData()" 
     @confirm-dialog.window="openDialog($event.detail)"
     @keydown.escape.window="cancelAction()">
    
    <template x-teleport="#modal-portal">
        <div x-show="isOpen" 
             x-cloak 
             x-trap.noscroll.inert="isOpen"
             class="fixed inset-0 z-50 overflow-y-auto" 
             aria-labelledby="confirm-dialog-title" 
             role="dialog" 
             aria-modal="true">
            
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div x-show="isOpen" 
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     x-transition:leave="ease-in duration-150"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" 
                     @click="cancelAction()"
                     aria-hidden="true"></div>
    
                {{-- Center alignment trick --}}
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
    
                {{-- Dialog panel --}}
                <div x-show="isOpen"
                     x-transition:enter="ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-150"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            {{-- Icon --}}
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center size-12 rounded-full sm:mx-0 sm:size-10"
                                 :class="iconBgClass">
                                <svg class="h-6 w-6" :class="iconColorClass" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path x-show="type === 'danger'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    <path x-show="type === 'warning'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    <path x-show="type === 'info'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
    
                            {{-- Content --}}
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left flex-1">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="confirm-dialog-title" x-text="title"></h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" x-html="message"></p>
                                </div>
                            </div>
                        </div>
                    </div>
    
                    {{-- Actions --}}
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <button type="button" 
                                @click="confirmAction()"
                                :disabled="isProcessing"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:w-auto sm:text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="confirmButtonClass"
                                x-text="isProcessing ? confirmButtonLoadingText : confirmButtonText"></button>
                        
                        <button type="button" 
                                @click="cancelAction()"
                                :disabled="isProcessing"
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                x-text="cancelButtonText"></button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('confirmDialogData', () => ({
        isOpen: false,
        isProcessing: false,
        type: 'danger', // danger, warning, info
        title: '',
        message: '',
        confirmButtonText: 'Konfirmasi',
        confirmButtonLoadingText: 'Memproses...',
        cancelButtonText: 'Batal',
        onConfirm: null,
        onCancel: null,

        get iconBgClass() {
            return {
                'danger': 'bg-red-100',
                'warning': 'bg-yellow-100',
                'info': 'bg-blue-100'
            }[this.type] || 'bg-red-100';
        },

        get iconColorClass() {
            return {
                'danger': 'text-red-600',
                'warning': 'text-yellow-600',
                'info': 'text-blue-600'
            }[this.type] || 'text-red-600';
        },

        get confirmButtonClass() {
            const baseClasses = {
                'danger': 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
                'warning': 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
                'info': 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'
            };
            return baseClasses[this.type] || baseClasses.danger;
        },

        openDialog(options) {
            this.type = options.type || 'danger';
            this.title = options.title || 'Konfirmasi';
            this.message = options.message || 'Apakah Anda yakin?';
            this.confirmButtonText = options.confirmButtonText || 'Konfirmasi';
            this.confirmButtonLoadingText = options.confirmButtonLoadingText || 'Memproses...';
            this.cancelButtonText = options.cancelButtonText || 'Batal';
            this.onConfirm = options.onConfirm || null;
            this.onCancel = options.onCancel || null;
            this.isOpen = true;
            this.isProcessing = false;
        },

        async confirmAction() {
            if (this.isProcessing) return;

            this.isProcessing = true;
            
            try {
                if (this.onConfirm) {
                    const result = await this.onConfirm();
                    
                    // If onConfirm returns false, don't close dialog
                    if (result === false) {
                        this.isProcessing = false;
                        return;
                    }
                }
                
                this.closeDialog();
            } catch (error) {
                console.error('Confirm action error:', error);
                this.isProcessing = false;
            }
        },

        cancelAction() {
            if (this.isProcessing) return;

            if (this.onCancel) {
                this.onCancel();
            }
            
            this.closeDialog();
        },

        closeDialog() {
            this.isOpen = false;
            this.isProcessing = false;
            
            // Reset after animation
            setTimeout(() => {
                this.onConfirm = null;
                this.onCancel = null;
            }, 300);
        }
    }));
});

// Helper function to show confirm dialog
window.showConfirmDialog = function(options) {
    window.dispatchEvent(new CustomEvent('confirm-dialog', { 
        detail: options 
    }));
};
</script>
