<div x-data="{
    isOpen: false,
    fetching: false,
    message: '',
    error: false,
    
    init() {
        // listen for open event
    },
    
    open() {
        this.isOpen = true;
        this.message = '';
        this.error = false;
        this.fetchGroups();
    },
    
    close() {
        this.isOpen = false;
    },

    async fetchGroups() {
        this.fetching = true;
        this.message = 'Fetching groups from WhatsApp...';
        this.error = false;
        
        try {
            const res = await fetch('{{ route("whatsapp.groups.fetch") }}', {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                    'Accept': 'application/json' 
                }
            });
            const data = await res.json();
            
            if (res.ok) {
                this.message = `Successfully fetched ${data.groups ? data.groups.length : 0} groups!`;
                // If the parent component has a refresh method for groups, we should trigger it.
                // For now, reload is safest to propagate new group list everywhere
                setTimeout(() => window.location.reload(), 1500);
            } else {
                this.error = true;
                this.message = 'Failed: ' + (data.error || 'Unknown error');
            }
        } catch(e) {
            this.error = true;
            this.message = 'Error connecting to server';
            console.error(e);
        } finally {
            this.fetching = false;
        }
    }
}"
@open-fetch-groups-modal.window="open()"
x-show="isOpen"
class="fixed inset-0 z-50 overflow-y-auto"
style="display: none;">

    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="close"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center">
        <div class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-sm">
            <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100">Fetch WhatsApp Groups</h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="message || 'Starting fetch...'"></p>
                            <div x-show="fetching" class="mt-3 flex justify-center">
                                <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <button type="button" @click="close" class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white dark:bg-gray-800 px-4 py-2 text-base font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Close</button>
            </div>
        </div>
    </div>
</div>
