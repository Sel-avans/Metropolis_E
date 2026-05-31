<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create New Event') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                
            @if ($errors->any())
                    <div class="bg-red-600 text-white p-4 mb-6 shadow-md rounded-md" role="alert">
                        <p class="font-bold text-lg mb-2">Please correct the following errors before proceeding:</p>
                        <ul class="list-disc list-inside font-medium space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('events.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 font-bold mb-2">Event Name</label>
                        <input type="text" id="name" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="type" class="block text-gray-700 font-bold mb-2">Event Type</label>
                        <select id="type" name="type" onchange="toggleEventFields()" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="one-off">One-off Event</option>
                            <option value="recurring">Recurring Event</option>
                        </select>
                    </div>

                    <div id="one_off_fields" class="mb-4 bg-gray-50 p-4 rounded border">
                        <div class="mb-2">
                            <label for="start_moment" class="block text-gray-700 font-bold mb-2">Start Moment</label>
                            <input type="datetime-local" id="start_moment" name="start_moment" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        
                        <div>
                            <label for="end_moment" class="block text-gray-700 font-bold mb-2">End Moment</label>
                            <input type="datetime-local" id="end_moment" name="end_moment" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>

                    <div id="recurring_fields" class="mb-4 bg-gray-50 p-4 rounded border" style="display: none;">
                        <label for="recurring_schedule" class="block text-gray-700 font-bold mb-2">Schedule Pattern</label>
                        <select id="recurring_schedule" name="recurring_schedule" class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <option value="">-- Select a pattern --</option>
                            <option value="daily">Every Day</option>
                            <option value="weekly">Every Week</option>
                            <option value="weekends">Weekends Only</option>
                            <option value="monthly">Every Month</option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Save Event
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
        
        // Run once on page load to ensure correct state
        document.addEventListener('DOMContentLoaded', toggleEventFields);
    </script>
</x-app-layout>