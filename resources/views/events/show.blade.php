<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Event Details: ') }} {{ $event->name }}
            </h2>
            <a href="{{ route('events.edit', $event) }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                Edit Event
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">{{ $event->name }}</h3>
                    
                    @if($event->description)
                        <p class="text-gray-700 mb-6">{{ $event->description }}</p>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50 p-4 rounded-lg border border-gray-100">
                        <div>
                            <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Event Type</p>
                            <p class="mt-1 text-lg text-gray-900 capitalize">{{ str_replace('-', ' ', $event->type) }}</p>
                        </div>

                        @if($event->type === 'one-off')
                            <div>
                                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Start & End</p>
                                <p class="mt-1 text-gray-900">
                                    {{ \App\Services\EventModifierService::formatForDisplay($event->start_moment) }} <br>
                                    <span class="text-gray-500 text-sm">to</span> <br>
                                    {{ \App\Services\EventModifierService::formatForDisplay($event->end_moment) }}
                                </p>
                            </div>
                        @else
                            <div>
                                <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Recurring Schedule</p>
                                <p class="mt-1 text-gray-900 capitalize">{{ $event->recurring_schedule }}</p>
                                <p class="text-sm text-gray-600 mt-1">
                                    {{ \Carbon\Carbon::parse($event->recurring_start_date)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($event->recurring_end_date)->format('M d, Y') }}<br>
                                    {{ \Carbon\Carbon::parse($event->recurring_start_time)->format('H:i') }} to {{ \Carbon\Carbon::parse($event->recurring_end_time)->format('H:i') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white px-6 py-6 sm:px-6 space-y-6">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Category Modifiers</h3>
                        <div class="border border-gray-200 rounded-md">
                            @if($event->categoryEffects->isEmpty())
                                <p class="p-4 text-sm text-gray-500 italic bg-gray-50 rounded-md">No category modifiers assigned.</p>
                            @else
                                <ul class="divide-y divide-gray-200">
                                    @foreach($event->categoryEffects as $effect)
                                        <li class="py-4 flex justify-between items-center px-4 sm:px-6 hover:bg-gray-50 transition-colors">
                                            <span class="text-base font-medium text-gray-900 capitalize">{{ $effect->category }}</span>
                                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-bold rounded-full shadow-sm
                                                {{ $effect->value > 0 ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' }}">
                                                {{ $effect->value > 0 ? '+' : '' }}{{ $effect->value }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Affected City Functions</h3>
                        <div class="border border-gray-200 rounded-md">
                            @if($event->effects->isEmpty())
                                <p class="p-4 text-sm text-gray-500 italic bg-gray-50 rounded-md">No city functions assigned.</p>
                            @else
                                <ul class="divide-y divide-gray-200">
                                    @foreach($event->effects as $effect)
                                        <li class="py-4 px-4 sm:px-6 hover:bg-gray-50 transition-colors">
                                            <div class="flex items-center gap-3">
                                                @if($effect->cityFunction?->image)
                                                    <img src="{{ asset($effect->cityFunction->image) }}"
                                                         alt="{{ $effect->cityFunction->name }}"
                                                         class="w-12 h-12 object-contain flex-shrink-0">
                                                @endif
                                                <span class="text-base font-medium text-gray-900">
                                                    {{ $effect->cityFunction->name ?? 'Unknown Function' }}
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                    <a href="{{ route('events.index') }}" class="text-gray-600 hover:text-gray-900 font-medium transition-colors">
                        &larr; Back to all events
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>