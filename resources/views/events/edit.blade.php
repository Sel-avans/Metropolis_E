<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit Event: ') }} {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <form method="POST" action="{{ route('events.update', $event) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 font-bold mb-2">Event Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $event->name) }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">{{ old('description', $event->description) }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label for="type" class="block text-gray-700 font-bold mb-2">Event Type</label>
                        <select id="type" name="type" onchange="toggleEventFields()" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="one-off" {{ old('type', $event->type) === 'one-off' ? 'selected' : '' }}>One-off Event</option>
                            <option value="recurring" {{ old('type', $event->type) === 'recurring' ? 'selected' : '' }}>Recurring Event</option>
                        </select>
                    </div>

                    <div id="one_off_fields" class="mb-4 bg-gray-50 p-4 rounded border">
                        <div class="mb-2">
                            <label for="start_moment" class="block text-gray-700 font-bold mb-2">Start Moment</label>
                            <input type="datetime-local" id="start_moment" name="start_moment" value="{{ old('start_moment', $event->start_moment ? \Carbon\Carbon::parse($event->start_moment)->format('Y-m-d\TH:i') : '') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        
                        <div>
                            <label for="end_moment" class="block text-gray-700 font-bold mb-2">End Moment</label>
                            <input type="datetime-local" id="end_moment" name="end_moment" value="{{ old('end_moment', $event->end_moment ? \Carbon\Carbon::parse($event->end_moment)->format('Y-m-d\TH:i') : '') }}" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>

                    <div id="recurring_fields" class="mb-4 bg-gray-50 p-4 rounded border" style="display: none;">
                        <label for="recurring_schedule" class="block text-gray-700 font-bold mb-2">Schedule Pattern</label>
                        <select id="recurring_schedule" name="recurring_schedule" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">-- Select a pattern --</option>
                            <option value="daily" {{ old('recurring_schedule', $event->recurring_schedule) === 'daily' ? 'selected' : '' }}>Every Day</option>
                            <option value="weekly" {{ old('recurring_schedule', $event->recurring_schedule) === 'weekly' ? 'selected' : '' }}>Every Week</option>
                            <option value="weekends" {{ old('recurring_schedule', $event->recurring_schedule) === 'weekends' ? 'selected' : '' }}>Weekends Only</option>
                            <option value="monthly" {{ old('recurring_schedule', $event->recurring_schedule) === 'monthly' ? 'selected' : '' }}>Every Month</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Update Event
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function toggleEventFields() {
            const type = document.getElementById('type').value;
            const oneOffFields = document.getElementById('one_off_fields');
            const recurringFields = document.getElementById('recurring_fields');

            if (type === 'one-off') {
                oneOffFields.style.display = 'block';
                recurringFields.style.display = 'none';
            } else {
                oneOffFields.style.display = 'none';
                recurringFields.style.display = 'block';
            }
        }
        
        // Run once on page load to ensure correct state based on saved database data
        document.addEventListener('DOMContentLoaded', toggleEventFields);
    </script>
</x-app-layout>