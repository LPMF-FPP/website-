<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Reminder: ') . $reminder->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    
                    <form action="{{ route('reminders.update', $reminder) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Left Column: Config -->
                            <div>
                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                        Enabled
                                    </label>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="is_enabled" value="1" class="form-checkbox h-5 w-5 text-indigo-600" {{ old('is_enabled', $reminder->is_enabled) ? 'checked' : '' }}>
                                        <span class="ml-2 text-gray-700">Active</span>
                                    </label>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="schedule_time">
                                        Schedule Time
                                    </label>
                                    <input type="time" name="schedule_time" id="schedule_time" value="{{ old('schedule_time', \Carbon\Carbon::parse($reminder->schedule_time)->format('H:i')) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    @error('schedule_time') <p class="text-red-500 text-xs italic">{{ $message }}</p> @enderror
                                </div>

                                @if($reminder->type === 'iso_countdown')
                                    <div class="mb-4">
                                        <label class="block text-gray-700 text-sm font-bold mb-2" for="target_date">
                                            Target Date (Surveillance)
                                        </label>
                                        <input type="date" name="target_date" id="target_date" value="{{ old('target_date', $reminder->metadata['target_date'] ?? '') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                        @error('target_date') <p class="text-red-500 text-xs italic">{{ $message }}</p> @enderror
                                    </div>
                                @endif

                                <div class="mb-4">
                                    <label class="block text-gray-700 text-sm font-bold mb-2" for="message_template">
                                        Message Template
                                    </label>
                                    <textarea name="message_template" id="message_template" rows="10" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline font-mono text-sm">{{ old('message_template', $reminder->message_template) }}</textarea>
                                    <p class="text-gray-500 text-xs mt-1">Available placeholders: {target_date}, {days_remaining}, {motivation_message}</p>
                                    @error('message_template') <p class="text-red-500 text-xs italic">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <!-- Right Column: Recipients -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Recipients</h3>
                                
                                <div id="recipients-container">
                                    @foreach(old('recipients', $reminder->recipients->count() > 0 ? $reminder->recipients : [['type' => 'group', 'value' => '']]) as $index => $recipient)
                                        <div class="recipient-row flex space-x-2 mb-2">
                                            <select name="recipients[{{ $index }}][type]" class="shadow border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-1/3">
                                                <option value="group" {{ (is_array($recipient) ? $recipient['type'] : $recipient->recipient_type) == 'group' ? 'selected' : '' }}>Group ID</option>
                                                <option value="phone" {{ (is_array($recipient) ? $recipient['type'] : $recipient->recipient_type) == 'phone' ? 'selected' : '' }}>Phone Number</option>
                                            </select>
                                            <input type="text" name="recipients[{{ $index }}][value]" value="{{ is_array($recipient) ? $recipient['value'] : $recipient->recipient_value }}" placeholder="e.g. 1203630234@g.us" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                            <button type="button" onclick="removeRecipient(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded focus:outline-none focus:shadow-outline">x</button>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <div class="flex space-x-2 mt-2">
                                    <button type="button" onclick="addRecipient()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                        Add Recipient
                                    </button>
                                    <button type="button" onclick="openGroupModal()" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                        Fetch Groups
                                    </button>
                                </div>
                                
                                <div class="mt-6 p-4 bg-gray-50 rounded text-sm text-gray-600">
                                    <p class="font-bold">Tips:</p>
                                    <ul class="list-disc ml-5">
                                        <li>For Groups: Use Group ID (ends with @g.us)</li>
                                        <li>For Phones: Use format 0812... or 62812...</li>
                                        <li>You can check logs for Group IDs when messages are received.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('reminders.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">Cancel</a>
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                Save Changes
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Group Selection Modal -->
    <div id="group-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" style="z-index: 50;">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 text-center">Select WhatsApp Group</h3>
                <div class="mt-2 px-2 py-3">
                    <div id="group-list" class="text-left max-h-60 overflow-y-auto min-h-[100px]">
                        <!-- Groups will be injected here -->
                        <div class="animate-pulse flex space-x-4">
                            <div class="flex-1 space-y-4 py-1">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-4 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="close-modal" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addRecipient() {
            const container = document.getElementById('recipients-container');
            const index = container.children.length;
            const html = `
                <div class="recipient-row flex space-x-2 mb-2">
                    <select name="recipients[${index}][type]" class="shadow border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline w-1/3">
                        <option value="group">Group ID</option>
                        <option value="phone">Phone Number</option>
                    </select>
                    <input type="text" name="recipients[${index}][value]" placeholder="e.g. 1203630234@g.us" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <button type="button" onclick="removeRecipient(this)" class="bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded focus:outline-none focus:shadow-outline">x</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function removeRecipient(btn) {
            btn.closest('.recipient-row').remove();
        }

        function openGroupModal() {
            document.getElementById('group-modal').classList.remove('hidden');
            fetchGroups();
        }

        document.getElementById('close-modal').addEventListener('click', function() {
            document.getElementById('group-modal').classList.add('hidden');
        });

        function fetchGroups() {
            const list = document.getElementById('group-list');
            list.innerHTML = '<div class="text-center text-gray-500">Loading...</div>';
            
            fetch('{{ route("reminders.fetch-groups") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.groups && data.groups.length > 0) {
                        let html = '<ul class="divide-y divide-gray-200">';
                        data.groups.forEach(group => {
                            const safeName = (group.name || 'Unknown').replace(/'/g, "\\'");
                            html += `
                                <li class="py-2 cursor-pointer hover:bg-gray-50 px-2 rounded" onclick="selectGroup('${group.jid}', '${safeName}')">
                                    <div class="text-sm font-medium text-gray-900">${group.name}</div>
                                    <div class="text-xs text-gray-500">${group.jid}</div>
                                </li>
                            `;
                        });
                        html += '</ul>';
                        list.innerHTML = html;
                    } else {
                        list.innerHTML = '<div class="text-center text-red-500 text-sm p-2">No groups found.<br>Ensure the bot is added to groups and has received messages recently.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    list.innerHTML = '<div class="text-center text-red-500 text-sm">Failed to fetch groups.<br>Check console for details.</div>';
                });
        }

        function selectGroup(jid, name) {
            // Add a new recipient row
            addRecipient();
            
            // Find the last added row
            const container = document.getElementById('recipients-container');
            const rows = container.getElementsByClassName('recipient-row');
            const lastRow = rows[rows.length - 1];
            
            if (lastRow) {
                // Set type to group
                const select = lastRow.querySelector('select');
                if (select) select.value = 'group';
                
                // Set value to JID
                const input = lastRow.querySelector('input[type="text"]');
                if (input) input.value = jid;
            }
            
            // Close modal
            document.getElementById('group-modal').classList.add('hidden');
        }
    </script>
</x-app-layout>
