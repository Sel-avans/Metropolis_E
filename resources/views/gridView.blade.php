<x-app-layout>
    <div class="flex gap-4">
        
        <div class="w-auto p-6 h-screen overflow-y-auto">
          
            <div>    
                <h1 class="text-2xl dark:text-teal-500 font-bold mb-4">Function Library</h1>
                <a href="{{ route('effects.index') }}" 
                    class="px-3 py-1.5 bg-teal-600 text-white rounded hover:bg-teal-700 text-xs shadow">
                    Effect Functions
                </a>

                <a href="{{ route('functions.index') }}" 
                   class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs shadow">
                    Function Management
                </a>

                <a href="{{ route('conditions.index') }}" 
                   class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs shadow">
                    Conditions
                </a>
            </div>

            @forelse($functions as $category => $items)
                <h2 class="text-xl dark:text-teal-600 font-semibold mt-4 mb-2">
                    {{ ucfirst($category) }}
                </h2>

                <ul class="mt-2 space-y-2 dark:text-white">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-2 px-4 py-2 border border-gray-400 dark:border-white rounded cursor-pointer"
                            draggable="true"
                            data-function-id="{{ $function->id }}"
                            data-function-name="{{ $function->name }}"
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

            <div class="w-auto h-min mt-4 border-4 border-gray-400 dark:bg-indigo-800 dark:border-teal-600 rounded-md p-6">
                <span id="qol-score" class="text-xl font-semibold mb-2 dark:text-teal-500">
                    QoL score: <span id="qol-score-value"></span>
                </span>
            </div>

            <div class="w-auto p-6">
                <h1 class="text-2xl text-center font-bold mb-4 dark:text-teal-500">City Grid (3x4)</h1>

                <div class="grid grid-flow-col grid-rows-4 gap-3 w-min">
                    @for($col = 1; $col <= 3; $col++)
                        @for($row = 1; $row <= 4; $row++)

                            @php
                                $cell = $grid->first(function($c) use ($row, $col) {
                                    return $c->row == $row && $c->col == $col;
                                });
                            @endphp
                            <div 
                            class="grid-cell border-2 bg-gray-300 border-gray-800 dark:bg-blue-950 dark:border-gray-300 w-32 h-32 items-center justify-center hover:bg-gray-400 hover:dark:bg-gray-100 cursor-pointer transition"
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

        </div>

        {{-- BreakDown QoL Score --}}
        <div id="breakdown-qol-score" class="border-solid border-l border-gray-400 dark:border-white w-2/12 p-3 ml-auto">
        </div>

    </div>
</x-app-layout>