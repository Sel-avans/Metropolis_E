<x-app-layout>
    <x-slot name="header">
        {{-- FIX: flex-col on mobile so the buttons stack neatly under the title when space is limited --}}
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Simulation Events') }}
            </h2>
            
            {{-- FIX: Wrapped the buttons in a flex container to align them properly on all screen sizes --}}
            <div class="flex flex-col sm:flex-row w-full sm:w-auto gap-2 sm:gap-4">
                <a href="/grid" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto text-center">
                    &larr; Back to grid
                </a>

                @can('CanManageEvents', App\Policies\PagePolicy::class)
                    <a href="{{ route('events.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition shadow-sm w-full sm:w-auto text-center">
                        + Create New Event
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        {{-- FIX: right-4 on mobile and max-w-[90vw] so the notification doesn't fall off the screen --}}
        <div id="success-toast" class="fixed top-20 right-4 sm:right-10 z-50 bg-green-500 text-white px-4 sm:px-6 py-3 sm:py-4 rounded-lg shadow-xl flex items-center gap-3 transition-opacity duration-500 max-w-[90vw]">
            <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            
            <span class="font-medium text-sm sm:text-base">{{ session('success') }}</span>
            
            <button onclick="document.getElementById('success-toast').style.display='none'" class="ml-auto sm:ml-4 focus:outline-none hover:text-green-200 flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <script>
            // Automatically hide the toast after 4 seconds
            setTimeout(function() {
                let toast = document.getElementById('success-toast');
                if (toast) {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.style.display = 'none', 500);
                }
            }, 4000);
        </script>
    @endif

    <div class="py-6 sm:py-12 px-4 sm:px-0">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4 sm:p-6 text-gray-900">
                    
                    {{-- FIX: Added overflow-x-auto around the table to enable horizontal scrolling on small devices --}}
                    <div class="overflow-x-auto w-full">
                        <table class="w-full min-w-[800px] leading-normal">
                            <thead>
                                <tr>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Event Name
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Type
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Effects
                                    </th>
                                    <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                        Timing
                                    </th>
                                    @can('CanManageEvents', App\Policies\PagePolicy::class)
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($events as $event)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                            <span class="text-sm text-gray-900 whitespace-nowrap font-medium">
                                                {{ ucfirst($event->name) }}
                                            </span>
                                            <p class="text-gray-500 whitespace-nowrap text-xs mt-1">{{ $event->description }}</p>
                                        </td>
                                        <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                            <span class="relative inline-block px-3 py-1 font-semibold text-blue-900 leading-tight">
                                                <span aria-hidden class="absolute inset-0 bg-blue-200 opacity-50 rounded-full"></span>
                                                <span class="relative whitespace-nowrap">{{ ucfirst($event->type) }}</span>
                                            </span>
                                        </td>
                                        <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                            @if($event->categoryEffects->isEmpty())
                                                <span class="text-gray-400 italic text-xs whitespace-nowrap">No effects</span>
                                            @else
                                                <ul class="space-y-1">
                                                    @foreach($event->categoryEffects as $effect)
                                                        <li>
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold whitespace-nowrap
                                                                {{ $effect->value > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                                {{ ucfirst($effect->category) }}: {{ $effect->value > 0 ? '+' : '' }}{{ $effect->value }}
                                                            </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </td>
                                        <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                            
                                            @if($event->type === 'one-off')
                                                <div class="text-sm text-gray-900 whitespace-nowrap">
                                                    <span class="font-semibold">Start:</span> {{ $event->start_moment ? \App\Services\EventModifierService::formatForDisplay($event->start_moment) : 'N/A' }}
                                                    <br>
                                                    <span class="font-semibold">End:</span> {{ $event->end_moment ? \App\Services\EventModifierService::formatForDisplay($event->end_moment) : 'N/A' }}
                                                </div>
                                            @else
                                                <div class="text-sm text-gray-900 whitespace-nowrap">
                                                    <span class="font-semibold">Repeats:</span> {{ ucfirst($event->recurring_schedule) }}
                                                    <br>
                                                    <span class="text-gray-500">
                                                        From: {{ $event->recurring_start_date ? \Carbon\Carbon::parse($event->recurring_start_date)->format('d-m-Y') : 'N/A' }}
                                                        ({{ $event->recurring_start_time ? \Carbon\Carbon::parse($event->recurring_start_time)->format('H:i') : 'N/A' }} 
                                                        - {{ $event->recurring_end_time ? \Carbon\Carbon::parse($event->recurring_end_time)->format('H:i') : 'N/A' }})
                                                    </span>
                                                </div>
                                            @endif

                                        </td>
                                        
                                        @can('CanManageEvents', App\Policies\PagePolicy::class)
                                            <td class="px-5 py-5 border-b border-gray-200 text-sm">
                                                <div class="flex items-center space-x-4">
                                                    <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:text-blue-900 font-semibold whitespace-nowrap">Details</a>
                                                    <a href="{{ route('events.edit', $event) }}" class="text-indigo-600 hover:text-indigo-900 whitespace-nowrap">Edit</a>
                                                    
                                                    <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this event?');" class="m-0">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900 whitespace-nowrap">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endcan
                                    </tr>
                                @empty
                                    @php
                                        $columnCount = auth()->user()->can('CanManageEvents', App\Policies\PagePolicy::class) ? 5 : 4;
                                    @endphp
                                    <tr>
                                        <td colspan="{{ $columnCount }}" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                            No events created yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>