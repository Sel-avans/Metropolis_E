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

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

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
                                        <p class="text-gray-900 whitespace-no-wrap font-bold">{{ $event->name }}</p>
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
                                            <p class="text-gray-900 whitespace-no-wrap">
                                                From: {{ \Carbon\Carbon::parse($event->start_moment)->format('d-m-Y H:i') }} <br>
                                                To: {{ \Carbon\Carbon::parse($event->end_moment)->format('d-m-Y H:i') }}
                                            </p>
                                        @else
                                            <p class="text-gray-900 whitespace-no-wrap">
                                                Repeats: <strong>{{ ucfirst($event->recurring_schedule) }}</strong>
                                            </p>
                                        @endif
                                    </td>
                                    
                                    @can('CanManageEvents', App\Policies\PagePolicy::class)
                                        <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                            <div class="flex items-center space-x-4">
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