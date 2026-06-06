<x-app-layout>
    {{-- Highlight stijl voor event-beïnvloede cellen --}}
    <style>
        .event-highlight {
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 2px #f59e0b66;
        }
    </style>

    <div class="flex gap-4 h-full">

        {{-- LEFT: Function Library --}}
        <div class="w-auto p-6 max-h-[73vh] overflow-y-auto flex-shrink-0">
            <div class="flex flex-col mb-4 gap-3">
                <h1 class="text-2xl dark:text-teal-500 font-bold mb-4">Function Library</h1>
                <div class="grid grid-cols-2 gap-3 w-full">
                    <a href="{{ route('functions.index') }}"
                        class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm shadow text-center focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Function Management
                    </a>
                    <a href="{{ route('effects.index') }}"
                        class="px-3 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm shadow text-center focus:outline-none focus:ring-2 focus:ring-green-500">
                        Effect Management
                    </a>
                    <a href="{{ route('conditions.index') }}"
                        class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm shadow text-center focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Condition Management
                    </a>
                    <a href="{{ route('events.index') }}"
                        class="px-3 py-2 bg-purple-600 text-white rounded hover:bg-purple-700 text-sm shadow text-center focus:outline-none focus:ring-2 focus:ring-purple-500">
                        Events
                    </a>
                </div>
            </div>

            @forelse($functions as $category => $items)
                <h2 class="text-xl dark:text-teal-600 font-semibold mt-6 mb-2">{{ ucfirst($category) }}</h2>
                <ul class="space-y-2 dark:text-white">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-3 px-4 py-3 border border-gray-400 dark:border-gray-600 rounded cursor-pointer hover:border-blue-500 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                            draggable="true"
                            data-function-id="{{ $function->id }}"
                            data-function-name="{{ $function->name }}"
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

        {{-- MIDDLE: QoL + Undo + Grid + Simulation Controls --}}
        <div class="flex flex-col flex-1 min-w-0 py-2">

            {{-- Undo and Export Buttons --}}
            <div class="flex gap-2 mb-4">
                <button id="undoButton"
                    class="flex-1 px-4 py-2 bg-yellow-500 text-black font-semibold rounded shadow hover:bg-yellow-600 transition focus:ring-2 focus:ring-yellow-400">
                    Undo
                </button>
                <button id="exportPdfButton" type="button"
                        class="flex-1 px-4 py-2 bg-teal-600 text-white font-semibold rounded shadow hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition"
                        aria-label="Export the current simulation as a PDF report">
                    Export as PDF
                </button>
            </div>

            {{-- City Grid --}}
            <div class="justify-center">
                <h1 class="text-2xl text-center font-bold mb-2 dark:text-teal-300">City Grid</h1>

                <div class="city-grid grid grid-flow-col grid-rows-3 gap-3 w-min mx-auto">
                    @for($col = 1; $col <= 4; $col++)
                        @for($row = 1; $row <= 3; $row++)
                            @php
                                $cell = $grid->first(fn($c) => $c->row == $row && $c->col == $col);
                                $fn   = $cell?->function ?? null;
                                // Categorieën als kommalijst op het img-element zetten
                                // zodat de highlight-logica in grid.js ze kan lezen.
                                // Pas dit aan als jouw model een andere relatie heeft.
                                $categories = $fn ? collect($fn->effects)->pluck('category')->unique()->implode(',') : '';
                            @endphp
                            <div class="grid-cell relative border-2 bg-gray-200 border-gray-400 dark:bg-blue-950 dark:border-gray-600 w-32 h-32 flex items-center justify-center cursor-pointer transition hover:bg-gray-300 dark:hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                data-row="{{ $row }}"
                                data-col="{{ $col }}"
                                data-id="{{ $cell->id ?? '' }}"
                                draggable="{{ $fn ? 'true' : 'false' }}"
                                role="button"
                                aria-label="{{ $fn ? 'Cell ' . $row . ',' . $col . ' with ' . $fn->name : 'Empty cell ' . $row . ',' . $col }}">

                                @if($fn)
                                    <img src="{{ asset($fn->image) }}"
                                        alt="{{ $fn->name }}"
                                        class="grid-function-icon object-contain"
                                        data-function-id="{{ $fn->id }}"
                                        data-categories="{{ $categories }}">

                                    <button type="button"
                                        class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center"
                                        aria-label="Remove {{ $fn->name }} from grid cell"
                                        title="Remove {{ $fn->name }} from grid cell">
                                        <span class="sr-only">Remove {{ $fn->name }} from grid cell</span>✖
                                    </button>
                                @endif
                            </div>
                        @endfor
                    @endfor
                </div>
            </div>

            {{-- Simulation Controls --}}
            <section id="simulation-controls"
                class="mt-6 p-6 border-t border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-b-lg">
                <h3 class="text-lg font-semibold mb-4 text-sky-500 dark:text-teal-500">Simulation Controls</h3>

                {{-- Timeline --}}
                <div class="mb-6">
                    <input type="range" id="simulation-timeline" class="w-full" min="0" max="1440" value="0">
                    <div class="flex justify-between items-center mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-mono">06:00</span>
                        <span id="simulation-time-display"
                            class="font-bold font-mono text-sky-600 dark:text-teal-400 text-base tabular-nums">
                            06:00
                        </span>
                        <span class="font-mono">06:00</span>
                    </div>
                </div>

                {{-- Speed + Playback + Active Events --}}
                <div class="flex flex-wrap gap-8 items-start">

                    {{-- Speed --}}
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

                    {{-- Playback --}}
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
                                &#x25B6;
                            </button>
                            <button type="button" id="forwardBtn" title="Skip Forward"
                                class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
                                &#x23E9;
                            </button>
                            <button type="button" id="replayBtn" title="Restart"
                                class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition">
                                &#x21BB;
                            </button>
                        </div>
                    </div>

                    {{-- Active Events (≤24h cycle) --}}
                    <div class="flex-1 min-w-[200px] bg-gray-50 dark:bg-gray-800 p-4 rounded-md border border-gray-200 dark:border-gray-600">
                        <h4 class="text-sm font-semibold text-sky-500 dark:text-teal-500 mb-2">Active Events</h4>
                        <ul id="active-events-list" class="text-xs text-gray-700 dark:text-gray-300 space-y-1">
                            <li class="text-gray-500">Loading events...</li>
                        </ul>
                    </div>

                </div>
            </section>
        </div>
        {{-- END MIDDLE --}}

        {{-- RIGHT: QoL Breakdown + Events panel --}}
        <div class="border-l border-gray-400 dark:border-gray-700 w-64 flex-shrink-0 p-3 flex flex-col gap-4 max-h-[73vh] overflow-y-auto">
            
            {{-- QoL Score --}}
            <div class="border-4 border-gray-400 dark:bg-indigo-900 dark:border-teal-600 rounded-md p-4">
                <span id="qol-score" class="text-xl font-semibold dark:text-teal-300">
                    QoL score: <span id="qol-score-value"></span>
                </span>
                <span id="old-qol-score" class="float-right text-gray-500"></span>
            </div>

            {{-- QoL Breakdown --}}
            <div id="breakdown-qol-score"></div>

            {{-- All Events: >24h --}}
            <div id="all-events-panel">
                <h3 class="text-lg font-semibold mb-2 dark:text-teal-500">All Events</h3>
                <ul id="all-events-detail-list" class="space-y-1 text-sm dark:text-white">
                    <li class="text-sm text-gray-500">Loading events...</li>
                </ul>
            </div>

        </div>
        {{-- END RIGHT --}}

    </div>

    {{-- QoL popup --}}
    <div id="qol-popup"
        class="hidden fixed z-50 bg-slate-900 border border-slate-600 rounded-lg shadow-xl p-3 w-56 pointer-events-none opacity-0 scale-95 transition-all duration-150">
        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cell QoL Influence</h4>
        <ul id="popup-neighbors-list" class="space-y-1"></ul>
    </div>

</x-app-layout>