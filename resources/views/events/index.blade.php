<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Simulation Events') }}
            </h2>
            
            @can('CanManageEvents', App\Policies\PagePolicy::class)
                <a href="{{ route('events.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    + Create New Event
                </a>
            @endcan
        </div>
    </x-slot>

    @if (session('success'))
        <div id="success-toast" class="fixed top-20 right-10 z-50 bg-green-500 text-white px-6 py-4 rounded-lg shadow-xl flex items-center gap-3 transition-opacity duration-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            
            <span class="font-medium">{{ session('success') }}</span>
            
            <button onclick="document.getElementById('success-toast').style.display='none'" class="ml-4 focus:outline-none hover:text-green-200">
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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    
                    <table class="min-w-full leading-normal">
                        <thead>
                            <tr>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Event Name
                                </th>
                                <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                    Type
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
                                <tr>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:text-blue-900 font-bold hover:underline transition-colors">
                                            {{ $event->name }}
                                        </a>
                                        <p class="text-gray-500 whitespace-no-wrap text-xs">{{ $event->description }}</p>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <span class="relative inline-block px-3 py-1 font-semibold text-blue-900 leading-tight">
                                            <span aria-hidden class="absolute inset-0 bg-blue-200 opacity-50 rounded-full"></span>
                                            <span class="relative">{{ ucfirst($event->type) }}</span>
                                        </span>
                                    </td>
                                    <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        
                                        @if($event->type === 'one-off')
                                            <div class="text-sm text-gray-900 whitespace-no-wrap">
                                                <span class="font-semibold">Start:</span> {{ $event->start_moment ? \Carbon\Carbon::parse($event->start_moment)->format('d-m-Y H:i') : 'N/A' }}
                                                <br>
                                                <span class="font-semibold">End:</span> {{ $event->end_moment ? \Carbon\Carbon::parse($event->end_moment)->format('d-m-Y H:i') : 'N/A' }}
                                            </div>
                                        @else
                                            <div class="text-sm text-gray-900 whitespace-no-wrap">
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
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                        <div class="flex items-center space-x-4">
                                        <a href="{{ route('events.show', $event) }}" class="text-blue-600 hover:text-blue-900 font-semibold">Details</a>
    
                                        <a href="{{ route('events.edit', $event) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                                
                                                <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
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
</x-app-layout>