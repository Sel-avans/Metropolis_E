<x-app-layout>
    <div class="flex gap-4 max-h-screen overflow-y-auto">

        {{-- QoL Breakdown Screen --}}
        <div id="qol-details-modal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
            <div class="bg-white p-6 max-w-xl w-full max-h-[80vh] overflow-y-auto rounded-lg relative">

                <button id="qol-close" class="text-black absolute top-3 right-3 text-lg cursor-pointer">✕</button>

                <h2 class="text-black text-xl font-bold mb-4">Quality of Life details</h2>

                <div id="qol-details-content"></div>
            </div>
        </div>

        {{-- Linkerzijde: Function Library --}}
        <div class="w-1/3 p-6 border-t-indigo-900 max-h-400 overflow-y-auto">
          
            <div>    
                <h1 class="text-2xl text-teal-500 font-bold mb-4">Function Library</h1>
                <a href="{{ route('effects.index') }}" 
                    class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">
                    Manage Functions
                </a>
            </div>

            @forelse($functions as $category => $items)
                <h2 class="text-xl text-teal-600 font-semibold mt-4 mb-2">
                    {{ ucfirst($category) }}
                </h2>

                <ul class="mt-2 space-y-2">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-2 p-2 border rounded cursor-pointer"
                            draggable="true"
                            data-function="{{ $function->name }}"
                            data-image="{{ asset($function->image) }}">

                            <img src="{{ asset($function->image) }}" 
                                alt="{{ $function->name }}" 
                                class="w-8 h-8 object-contain pointer-events-none">

                            <span class="library-name text-base">{{ $function->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-gray-500">No functions available.</p>
            @endforelse
        </div>

        <div class="flex flex-col">

            {{-- Rechterzijde: City Grid --}}
            <div class="w-2/3 p-6">
                <h1 class="text-2xl font-bold mb-4 text-teal-500">City Grid (3x4)</h1>

                <div class="grid grid-flow-col grid-rows-4 gap-3 w-min">
                    @for($col = 1; $col <= 3; $col++)
                        @for($row = 1; $row <= 4; $row++)

                            @php
                                $cell = $grid->first(function($c) use ($row, $col) {
                                    return $c->row == $row && $c->col == $col;
                                });
                            @endphp
                            <div 
                            class="grid-cell border-2 bg-blue-950 border-gray-300 w-24 h-24 items-center justify-center hover:bg-gray-100 cursor-pointer transition"
                            data-row="{{$row}}"
                            data-col="{{$col}}"
                            draggable="{{ $cell ? 'true' : 'false' }}">

                            @if($cell && $cell->function)
                                <img src="{{ asset($cell->function->image) }}"
                                    alt="{{ $cell->function->name }}"
                                    class="grid-function-icon">
                            @endif

                            </div>
                        @endfor
                    @endfor
                </div>
            </div>

                                {{-- Qol Score --}}
            <div class="w-auto h-min border-4 bg-indigo-800 border-teal-600 rounded-md p-6">

                <span id="qol-score" onclick="openQolModal()" class="cursor-pointer text-xl font-semibold mb-2 text-teal-500">
                    QoL score: <span id="qol-score-value"></span>
                </span>
                
            </div>

        </div>

        <div class="bg-green-950 w-2/5">

        </div>

    </div>
</x-app-layout>