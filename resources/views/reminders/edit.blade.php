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
                                
                                <button type="button" onclick="addRecipient()" class="mt-2 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    Add Recipient
                                </button>
                                
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
    </script>
</x-app-layout>
