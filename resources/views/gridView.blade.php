<x-app-layout>
    <div class="flex gap-4">

        <div class="w-auto p-6 max-h-[93vh] overflow-y-auto">
            {{-- Section Library --}}
            <div class="flex flex-col mb-4 gap-3">
                <h1 class="text-2xl dark:text-teal-500 font-bold mb-4">Function Library</h1>

                    <div class="grid grid-cols-2 gap-3 w-full">

                        <a href="{{ route('functions.index') }}"
                        class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm shadow text-center
                                focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Navigate to Function Management page"
                                >
                            Function Management
                        </a>

                        <a href="{{ route('effects.index') }}"
                        class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm shadow text-center
                                focus:outline-none focus:ring-2 focus:ring-green-500"
                        aria-label="Navigate to Effect Management page"
                                >
                            Effect Management
                        </a>

                        <a href="{{ route('conditions.index') }}"
                        class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm shadow text-center
                                focus:outline-none focus:ring-2 focus:ring-blue-500"
                        aria-label="Navigate to Condition Management page"
                                >
                            Condition Management
                        </a>

                        <a href="{{ route('events.index') }}"
                        class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm shadow text-center
                                focus:outline-none focus:ring-2 focus:ring-purple-500"
                        aria-label="Navigate to Events page"
                                >
                            Events
                        </a>

                    </div>

            </div>

            @forelse($functions as $category => $items)
                <h2 class="text-xl dark:text-teal-600 font-semibold mt-6 mb-2">{{ ucfirst($category) }}</h2>
                <ul class="space-y-2 dark:text-white">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-3 px-4 py-3 border border-gray-400 dark:border-gray-600 rounded cursor-pointer hover:border-blue-500 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                            draggable="true" data-function-id="{{ $function->id }}" data-function-name="{{ $function->name }}"
                            data-image="{{ asset($function->image) }}">
                            <img src="{{ asset($function->image) }}" alt="{{ $function->name }}"
                                class="w-8 h-8 object-contain pointer-events-none">
                            <span class="text-sm font-medium">{{ $function->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-gray-500">No functions available.</p>
            @endforelse
        </div>

        {{-- Section Total QoL-score --}}
        <div class="flex flex-col mx-auto">
            <div
                class="w-auto h-min mt-4 border-4 border-gray-400 dark:bg-indigo-900 dark:border-teal-600 rounded-md p-6">
                <span id="qol-score" class="text-xl font-semibold dark:text-teal-300">
                    QoL score: <span id="qol-score-value"></span>
                </span>
                <span id="old-qol-score" class="float-right text-gray-500"></span>
            </div>

            {{-- Section Undo Button --}}
            <button id="undoButton"
                class="mt-4 px-4 py-2 bg-yellow-500 text-black font-semibold rounded shadow hover:bg-yellow-600 transition focus:ring-2 focus:ring-yellow-400">
                Undo
            </button>

            {{-- Section City Grid --}}
            <div class="w-auto p-6">
                <h1 class="text-2xl text-center font-bold mb-4 dark:text-teal-300">City Grid</h1>

                <div class="city-grid grid grid-flow-col grid-rows-3 gap-3 w-min">
                    @for($col = 1; $col <= 4; $col++)
                        @for($row = 1; $row <= 3; $row++)
                            @php
                                $cell = $grid->first(fn($c) => $c->row == $row && $c->col == $col);
                            @endphp
                            <div class="grid-cell relative border-2 bg-gray-200 border-gray-400 dark:bg-blue-950 dark:border-gray-600 w-32 h-32 flex items-center justify-center cursor-pointer transition hover:bg-gray-300 dark:hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                data-row="{{ $row }}" data-col="{{ $col }}" data-id="{{ $cell->id ?? '' }}"
                                draggable="{{ $cell ? 'true' : 'false' }}" role="button"
                                aria-label="{{ $cell && $cell->function ? 'Cell ' . $row . ',' . $col . ' with ' . $cell->function->name : 'Empty cell ' . $row . ',' . $col }}">

                                @if($cell && $cell->function)
                                    <img src="{{ asset($cell->function->image) }}" alt="{{ $cell->function->name }}"
                                        class="grid-function-icon object-contain w-20 h-20"
                                        data-function-id="{{ $cell->function->id }}">
                                    <button type="button"
                                        class="delete-btn absolute top-1 right-1 bg-red-600 text-white w-5 h-5 text-xs rounded-full flex items-center justify-center hover:bg-red-700"
                                        aria-label="Remove">✖</button>
                                @endif
                            </div>
                        @endfor
                    @endfor
                </div>
            </div>

{{-- Section Simulation Controls --}}
<section id="simulation-controls" class="mt-6 p-6 border-t border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-b-lg">
    <h3 class="text-lg font-semibold mb-4 text-sky-500 dark:text-teal-500">Simulation Controls</h3>
    
    {{-- Timeline --}}
    <div class="mb-8">
        <input type="range" id="simulation-timeline" 
               class="w-full h-2 bg-gray-300 rounded-lg appearance-none cursor-pointer dark:bg-gray-600 accent-sky-500 dark:accent-teal-500" 
               min="0" max="100" value="0">
    </div>

    <div class="flex flex-wrap gap-8 items-start">
{{-- Left Column: Speed & Status --}}
<div class="flex flex-col items-center gap-2">
    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400">
        Simulation Speed
    </span>
    
    <div class="flex gap-2 bg-gray-100 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
        @foreach([1, 2, 5] as $speed)
            <button type="button" onclick="setSimulationSpeed({{ $speed }})"
                class="speed-btn px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition"
                data-speed="{{ $speed }}">{{ $speed }}×</button>
        @endforeach
    </div>

    <p class="text-xs text-gray-600 dark:text-gray-400">
        Current: <span id="active-speed-display" class="font-bold text-sky-600 dark:text-teal-500">1×</span>
    </p>
</div>

{{-- Middle Column: Playback Controls --}}
<div class="flex flex-col items-center gap-2">
    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400">
        Animation Play
    </span>
    
    <div class="flex gap-2 bg-gray-100 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
        <button type="button" id="reverseBtn" title="Rewind"
            class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
            &#x23EA;
        </button>
        <button type="button" id="playPauseBtn" title="Play/Pause"
            class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
            &#x23F8;
        </button>
        <button type="button" id="forwardBtn" title="Skip Forward"
            class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
            &#x23E9;
        </button>
        <button type="button" id="replayBtn" title="Repeat"
            class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
            &#x21BB;
        </button>
    </div>
</div>

        {{-- Right Column: Active Events (Gecorrigeerde padding) --}}
        <div class="flex-1 min-w-[200px] bg-gray-50 dark:bg-gray-800 p-4 rounded-md border border-gray-200 dark:border-gray-600">
            <h4 class="text-sm font-semibold text-sky-500 dark:text-teal-500 mb-2">Active Events</h4>
            <ul id="active-events-list" class="text-xs text-gray-700 dark:text-gray-300 list-disc pl-4 space-y-1">
                <li id="no-events-msg">No active events</li>
            </ul>
        </div>
    </div>
</section>
        </div>

        {{-- Section QoL Breakdown and Active Events --}}
        <div class="border-l border-gray-400 dark:border-gray-700 w-2/12 p-3 ml-auto flex flex-col gap-4">
            <div id="breakdown-qol-score"></div>
            <div id="active-events-panel">
                <h3 class="text-lg font-semibold mb-2 dark:text-teal-500">Events</h3>
                <div id="active-events-empty" class="text-sm text-gray-500">No events.</div>
                <ul id="active-events-list" class="space-y-2 text-sm dark:text-white"></ul>
            </div>
        </div>
    </div>

    @vite(['resources/js/grid.js'])
</x-app-layout>