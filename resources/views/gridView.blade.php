<x-app-layout>
    <div class="flex gap-4">
        
        {{-- Linkerzijde: Function Library --}}
        <div class="w-auto p-6 border-t-indigo-900 h-screen overflow-y-auto">
          
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

                <ul class="mt-2 space-y-2 text-white">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-2 px-4 py-2 border rounded cursor-pointer"
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

        <div class="flex flex-col mx-auto">

            {{-- Midden: City Grid --}}
            <div class="w-auto p-6">
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
                            class="grid-cell border-2 bg-blue-950 border-gray-300 w-32 h-32 items-center justify-center hover:bg-gray-100 cursor-pointer transition"
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

            {{-- QoL Score --}}
            <div class="w-auto h-min border-4 bg-indigo-800 border-teal-600 rounded-md p-6">

                <span id="qol-score" class="text-xl font-semibold mb-2 text-teal-500">
                    QoL score: <span id="qol-score-value"></span>
                </span>
                
            </div><S></S>

        </div>

        {{-- BreakDown QoL Score --}}
        <div id="breakdown-qol-score" class="border-solid border border-white w-2/12 p-3 ml-auto">
        </div>

    </div>
</x-app-layout>