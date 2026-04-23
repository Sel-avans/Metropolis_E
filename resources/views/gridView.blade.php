<x-app-layout>
    <div class="flex gap-4 max-h-screen overflow-y-auto">

        {{-- Linkerzijde: Function Library --}}
        <div class="w-1/3 p-6 bg-gray-100 max-h-400 overflow-y-auto">
            <h1 class="text-2xl font-bold mb-4">Function Library</h1>

            @forelse($functions as $category => $items)
                <h2 class="text-xl font-semibold mt-4 mb-2">
                    {{ ucfirst($category) }}
                </h2>

                <ul class="space-y-2">
                    @foreach($items as $function)
                        <li 
                            class="library-item flex items-center gap-3 p-2 bg-white hover:bg-gray-100 rounded shadow cursor-grab"
                            draggable="true"
                            data-function="{{ $function['name'] }}"
                            data-image="/icons/{{ $function['image'] }}"
                        >
                            <img 
                                src="/icons/{{ $function['image'] }}" 
                                alt="{{ $function['name'] }}" 
                                class="w-8 h-8"
                            >

                            <span class="font-medium">
                                {{ $function['name'] }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-gray-500">No functions available.</p>
            @endforelse
        </div>

        {{-- Rechterzijde: City Grid --}}
        <div class="w-2/3 p-6">
            <h1 class="text-2xl font-bold mb-4 text-teal-500">City Grid (3x4)</h1>

            <div class="grid grid-flow-col grid-rows-4 gap-3 w-min">
                @for($id = 1; $id <= 12; $id++)

                            <div 
                                class="grid-cell border-2 bg-blue-950 border-gray-300 w-24 h-24 items-center justify-center hover:bg-gray-100 transition"
                                data-id="{{ $id}}"
                            >
                            </div>

                @endfor
            </div>
        </div>

    </div>
</x-app-layout>