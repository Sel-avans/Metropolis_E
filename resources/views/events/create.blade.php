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

                <form method="POST" action="{{ route('events.store') }}" novalidate>
                    @csrf

                    <div class="mb-4">
                        <label for="name" class="block text-gray-700 font-bold mb-2">Event Name</label>
                        <input type="text" id="name" name="name" 
                               value="{{ old('name') }}"
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('name') border-red-500 border-2 @enderror" 
                               required>
                        @error('name')
                            <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" 
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('description') border-red-500 border-2 @enderror">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-4">
                        <label for="type" class="block text-gray-700 font-bold mb-2">Event Type</label>
                        <select id="type" name="type" onchange="toggleEventFields()" 
                                class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('type') border-red-500 border-2 @enderror">
                            <option value="one-off" {{ old('type') === 'one-off' ? 'selected' : '' }}>One-off Event</option>
                            <option value="recurring" {{ old('type') === 'recurring' ? 'selected' : '' }}>Recurring Event</option>
                        </select>
                    </div>

                    <div id="one_off_fields" class="mb-4 bg-gray-50 p-4 rounded border">
                        <p class="text-sm text-gray-600 mb-3">Tijden in <strong>Nederlandse tijd</strong>.</p>
                        <div class="mb-2">
                            <label for="start_moment" class="block text-gray-700 font-bold mb-2">Start Moment</label>
                            <input type="datetime-local" id="start_moment" name="start_moment" 
                                   value="{{ old('start_moment') }}"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('start_moment') border-red-500 border-2 @enderror">
                            @error('start_moment')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div>
                            <label for="end_moment" class="block text-gray-700 font-bold mb-2">End Moment</label>
                            <input type="datetime-local" id="end_moment" name="end_moment" 
                                   value="{{ old('end_moment') }}"
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('end_moment') border-red-500 border-2 @enderror">
                            @error('end_moment')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div id="recurring_fields" class="mb-4 bg-gray-50 p-4 rounded border" style="display: none;">
                        
                        <div class="mb-4">
                            <label for="recurring_schedule" class="block text-gray-700 font-bold mb-2">Schedule Pattern</label>
                            <select id="recurring_schedule" name="recurring_schedule" 
                                    class="shadow border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('recurring_schedule') border-red-500 border-2 @enderror">
                                <option value="">-- Select a pattern --</option>
                                <option value="daily" {{ old('recurring_schedule') === 'daily' ? 'selected' : '' }}>Every Day</option>
                                <option value="weekly" {{ old('recurring_schedule') === 'weekly' ? 'selected' : '' }}>Every Week</option>
                                <option value="weekends" {{ old('recurring_schedule') === 'weekends' ? 'selected' : '' }}>Weekends Only</option>
                                <option value="monthly" {{ old('recurring_schedule') === 'monthly' ? 'selected' : '' }}>Every Month</option>
                            </select>
                            @error('recurring_schedule')
                                <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-4 mb-4">
                            <div class="w-1/2">
                                <label for="recurring_start_date" class="block text-gray-700 font-bold mb-2">Start Date</label>
                                <input type="date" id="recurring_start_date" name="recurring_start_date" 
                                       value="{{ old('recurring_start_date') }}"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('recurring_start_date') border-red-500 border-2 @enderror">
                                @error('recurring_start_date')
                                    <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="w-1/2">
                                <label for="recurring_end_date" class="block text-gray-700 font-bold mb-2">End Date</label>
                                <input type="date" id="recurring_end_date" name="recurring_end_date" 
                                       value="{{ old('recurring_end_date') }}"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('recurring_end_date') border-red-500 border-2 @enderror">
                                @error('recurring_end_date')
                                    <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <div class="w-1/2">
                                <label for="recurring_start_time" class="block text-gray-700 font-bold mb-2">Start Time</label>
                                <input type="time" id="recurring_start_time" name="recurring_start_time" 
                                       value="{{ old('recurring_start_time') }}"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('recurring_start_time') border-red-500 border-2 @enderror">
                                @error('recurring_start_time')
                                    <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div class="w-1/2">
                                <label for="recurring_end_time" class="block text-gray-700 font-bold mb-2">End Time</label>
                                <input type="time" id="recurring_end_time" name="recurring_end_time" 
                                       value="{{ old('recurring_end_time') }}"
                                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline @error('recurring_end_time') border-red-500 border-2 @enderror">
                                @error('recurring_end_time')
                                    <p class="text-red-500 text-xs italic mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                    </div>

                    <div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Event Effects (Modifiers)</h3>
                        <p class="text-sm text-gray-500 mb-4">Adjust the impact using the - and + buttons (min -5, max +5).</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($cityFunctions as $function)
                                @php
                                    // Default value is 0
                                    $currentVal = old('effects.'.$function->id, 0);
                                @endphp
                                
                                <div class="bg-white p-3 rounded border border-gray-200 shadow-sm flex flex-col items-center">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2 text-center w-full">
                                        {{ $function->name }}
                                    </label>
                                    
                                    <div class="flex items-center justify-center space-x-3 mt-1">
                                        <button type="button" 
                                                onclick="adjustEffect({{ $function->id }}, -1)" 
                                                class="bg-red-500 hover:bg-red-600 text-white font-bold w-8 h-8 rounded flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-red-400">
                                            -
                                        </button>
                                        
                                        <input type="text" readonly 
                                               name="effects[{{ $function->id }}]" 
                                               id="effect_{{ $function->id }}" 
                                               value="{{ $currentVal }}"
                                               class="w-16 text-center border border-gray-300 rounded-md shadow-sm bg-gray-100 font-bold text-gray-800 focus:ring-0 cursor-default">
                                        
                                        <button type="button" 
                                                onclick="adjustEffect({{ $function->id }}, 1)" 
                                                class="bg-green-500 hover:bg-green-600 text-white font-bold w-8 h-8 rounded flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-green-400">
                                            +
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-8 pt-4 border-t border-gray-200">
                        <a href="{{ route('events.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition-colors">
                             Back
                        </a>

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
            
            // Get HTML inputs
            const startInput = document.getElementById('start_moment');
            const endInput = document.getElementById('end_moment');
            
            const scheduleInput = document.getElementById('recurring_schedule');
            const recurringStartDateInput = document.getElementById('recurring_start_date');
            const recurringEndDateInput = document.getElementById('recurring_end_date');
            const recurringStartTimeInput = document.getElementById('recurring_start_time');
            const recurringEndTimeInput = document.getElementById('recurring_end_time');

            if (type === 'one-off') {
                oneOffFields.style.display = 'block';
                recurringFields.style.display = 'none';
                
                // Set required attributes for one-off fields
                startInput.setAttribute('required', 'required');
                endInput.setAttribute('required', 'required');
                
                // Remove required attributes for recurring fields
                scheduleInput.removeAttribute('required');
                recurringStartDateInput.removeAttribute('required');
                // recurringEndDateInput.removeAttribute('required');
                recurringStartTimeInput.removeAttribute('required');
                recurringEndTimeInput.removeAttribute('required');
                
            } else {
                oneOffFields.style.display = 'none';
                recurringFields.style.display = 'block';
                
                // Remove required attributes for one-off fields
                startInput.removeAttribute('required');
                endInput.removeAttribute('required');
                
                // Set required attributes for recurring fields
                scheduleInput.setAttribute('required', 'required');
                recurringStartDateInput.setAttribute('required', 'required');
                recurringEndDateInput.setAttribute('required', 'required');
                recurringStartTimeInput.setAttribute('required', 'required');
                recurringEndTimeInput.setAttribute('required', 'required');
            }
        }
        
        // Initialize form view state on page load
        document.addEventListener('DOMContentLoaded', toggleEventFields);

        // Adjust the effect value and enforce the limits (-5 to +5)
        function adjustEffect(functionId, changeAmount) {
            const inputField = document.getElementById('effect_' + functionId);
            
            // Parse the current value as an integer
            let currentValue = parseInt(inputField.value) || 0;
            
            // Calculate the new value
            let newValue = currentValue + changeAmount;
            
            // Enforce constraints as per documentation (-5 to 5 limit)
            if (newValue >= -5 && newValue <= 5) {
                inputField.value = newValue;
            }
        }
    </script>
</x-app-layout>