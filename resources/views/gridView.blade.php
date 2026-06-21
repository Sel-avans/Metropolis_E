<x-app-layout>
    {{-- Highlight stijl voor event-beïnvloede cellen --}}
    <style>
        .event-highlight {
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 2px #f59e0b66;
        }
    </style>

<div class="flex flex-col xl:flex-row gap-4 w-full h-auto xl:h-full xl:overflow-hidden">

        {{-- LEFT: Function Library --}}
        <div id="library-column" class="library-sidebar relative w-full xl:w-auto p-4 xl:p-6 flex flex-col order-2 xl:order-1 xl:max-h-[73vh] xl:overflow-y-auto xl:overscroll-contain xl:flex-none xl:min-h-0">
            <div class="flex flex-col mb-4 gap-3 shrink-0">
                <h1 class="text-2xl dark:text-teal-500 font-bold mb-4">Function Library</h1>
                <nav aria-label="Management navigation">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full">
                        @if(auth()->user() && auth()->user()->role->name === 'Administrator')
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
                        @endif
                    </div>
                </nav>
            </div>

            <section id="library-filters" class="shrink-0 mb-3" >
                <label for="library-search" class="sr-only">Search destinations by name</label>
                <input
                    type="search"
                    id="library-search"
                    placeholder="Search by name…"
                    autocomplete="off"
                    class="library-search-input w-full px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
            </section>

            <p
                id="library-no-results"
                class="hidden shrink-0 text-sm text-gray-600 dark:text-gray-400 px-2 py-2 mb-2 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800"
                role="status"
                aria-live="polite"
            >
                No destinations match your search.
            </p>

            <div id="library-list" class="library-list-panel flex-1 min-h-0">
                @forelse($functions as $category => $items)
                    <div class="library-category-group mb-4" data-category="{{ $category }}">
                        <h2 class="text-xl dark:text-teal-600 font-semibold mt-2 mb-2">{{ ucfirst($category) }}</h2>
                        <ul class="space-y-2 dark:text-white" role="list">
                            @foreach($items as $function)
                                {{-- HERSTELD: 'tabindex="0"' en 'role="button"' toegevoegd voor Keyboard Toegankelijkheid --}}
                                <li class="library-item flex items-center gap-3 px-4 py-3 border border-gray-400 dark:border-gray-600 rounded cursor-pointer hover:border-blue-500 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    draggable="{{ (auth()->user() && (auth()->user()->role->name === 'City_planner' || auth()->user()->role->name === 'Administrator')) ? 'true' : 'false' }}"
                                    tabindex="0"
                                    role="button"
                                    data-function-id="{{ $function->id }}"
                                    data-function-name="{{ $function->name }}"
                                    data-category="{{ $function->category }}"
                                    data-image="{{ asset($function->image) }}"
                                    aria-label="{{ $function->name }}">
                                    <img src="{{ asset($function->image) }}" alt="{{ $function->name }}"
                                        class="w-8 h-8 object-contain pointer-events-none">
                                        <span class="text-sm font-medium flex-1">{{ $function->name }}</span>

                                        <button type="button" class="preview-info-btn p-2 text-slate-400 hover:text-sky-500 transition-colors z-10" aria-label="View Details">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 pointer-events-none">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-gray-500">No functions available.</p>
                @endforelse
            </div>

            <div id="library-preview-panel"
            class="hidden absolute inset-x-0 bottom-0 z-10 flex flex-col w-full overflow-hidden bg-slate-900/95 border-t border-slate-600 rounded-t-lg shadow-xl text-white pointer-events-none library-preview-panel"
            role="dialog"
            aria-live="polite"
            aria-hidden="true"
            aria-labelledby="library-preview-title">
            <div id="library-preview-header" class="library-preview-header shrink-0 flex items-start justify-between gap-3 px-4 pt-4 pb-2 border-b border-slate-600 bg-slate-900/95">
                <div class="flex items-center gap-3 min-w-0">
                    <img id="library-preview-icon" src="" alt="" class="w-10 h-10 object-contain hidden">
                    <div class="min-w-0">
                        <h3 id="library-preview-title" class="text-base font-bold text-white truncate">—</h3>
                        <p id="library-preview-category" class="text-xs uppercase tracking-wide text-white/80">—</p>
                    </div>
                </div>
                <button id="library-preview-close" type="button"
                    class="hidden shrink-0 w-7 h-7 rounded-full border border-slate-500 text-white hover:border-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    aria-label="Close destination preview">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div id="library-preview-body" class="library-preview-body p-4 overflow-hidden">
                <div id="library-preview-status" class="text-xs text-amber-400 mb-2"></div>
                <div id="library-preview-effects" class="mb-3"></div>
                <div id="library-preview-conditions" class="text-xs"></div>
            </div>
        </div>

        </div>

        

        {{-- MIDDLE: QoL + Undo + Grid + Simulation Controls --}}
        <div class="flex flex-col w-full py-2 order-1 xl:order-2 overflow-x-hidden xl:flex-1 xl:min-h-0 xl:overflow-y-auto xl:overscroll-contain">
            {{-- Undo and Export Buttons --}}
            <div class="grid grid-cols-2 xl:flex xl:flex-row gap-2 mb-4">

                @if(auth()->user() && (auth()->user()->role->name === 'Municipal_Policy_Maker' || auth()->user()->role->name === 'Administrator'))
                <button type="button" id="approve-btn"
                    class="flex-1 px-4 py-2 bg-amber-600 text-white font-semibold rounded shadow hover:bg-amber-700 transition focus:ring-2 focus:ring-yellow-400"
                > Lock & Unlock Area</button>
                <button type="button" id="approve-grid-btn"
                    class="flex-1 px-4 py-2 bg-lime-600 text-white font-semibold rounded shadow hover:bg-lime-700 transition focus:ring-2 focus:ring-yellow-400"
                > Approve Grid</button>
                @endif

                <button id="undoButton"
                    class="flex-1 px-4 py-2 bg-yellow-500 text-black font-semibold rounded shadow hover:bg-yellow-600 transition focus:ring-2 focus:ring-yellow-400">
                    Undo
                </button>
                @if(auth()->user() && (auth()->user()->role->name === 'Municipal_Policy_Maker' || auth()->user()->role->name === 'Administrator'))
                <button id="exportPdfButton" type="button"
                        class="flex-1 px-4 py-2 bg-teal-600 text-white font-semibold rounded shadow hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 transition"
                        aria-label="Export the current simulation as a PDF report">
                    Export as PDF
                </button>
                @endif
            </div>

            {{-- City Grid --}}
            <div class="justify-center w-full overflow-x-auto pb-4">
                <h1 class="text-2xl text-center font-bold mb-2 dark:text-teal-300">City Grid</h1>

                <div class="city-grid grid grid-flow-col grid-rows-3 gap-3 w-min mx-auto"
                aria-label="City planning grid">
                    @for($col = 1; $col <= 4; $col++)
                        @for($row = 1; $row <= 3; $row++)
@php
                                $cell = $grid->first(fn($c) => $c->row == $row && $c->col == $col);
                                $isApproved = $cell && $cell->is_approved;
                                $isCityPlanner = auth()->user() && auth()->user()->role->name === 'City_planner';
                                
                                // Define the classes for the cell based on its state
                                $cellClasses = $isApproved
                                    ? 'is-locked bg-stripes opacity-60 border-red-600 ' . ($isCityPlanner ? 'cursor-pointer' : 'cursor-not-allowed')
                                    : 'bg-gray-300 border-gray-800 dark:bg-blue-950 dark:border-gray-300 hover:bg-gray-400 hover:dark:bg-gray-100 cursor-pointer';
                            @endphp
            
                            <div class="grid-cell relative flex border-2 w-32 h-32 items-center justify-center transition focus:outline-none focus:ring-2 focus:ring-blue-500 {{ $cellClasses }}"
                                data-row="{{ $row }}"
                                data-col="{{ $col }}"
                                data-id="{{ $cell ? $cell->id : '' }}"
                                draggable="{{ $cell && !$isApproved ? 'true' : 'false' }}"
                                role="button"
                                tabindex="0"
                                @if($isApproved) aria-label="Approved area row {{ $row }}, column {{ $col }}" @endif>
                                
                                <div class="lock-indicator absolute z-50 top-1 left-1 bg-red-600 text-white text-[10px] font-bold px-1 rounded flex items-center gap-0.5 shadow {{ $isApproved ? '' : 'hidden' }}"
                                    aria-hidden="{{ $isApproved ? 'false' : 'true' }}">
                                    🔒 <span class="uppercase text-[9px]">Locked</span>
                                </div>

                                <div class="area-lock-explanation {{ $isApproved ? '' : 'hidden' }}"
                                    role="tooltip"
                                    aria-live="polite">
                                    This area is approved and cannot be changed.
                                </div>

                                @if(!empty($cell) && !empty($cell->function))
                                    <img src="{{ asset($cell->function->image) }}"
                                        alt="{{ $cell->function->name }}"
                                        class="grid-function-icon object-contain relative z-0 {{ $isApproved ? 'pointer-events-none select-none' : '' }}"
                                        data-function-id="{{ $cell->function->id }}"
                                        draggable="{{ $isApproved ? 'false' : 'true' }}"
                                        ondragstart="{{ $isApproved ? 'return false;' : '' }}">
                                    @if(auth()->user() && (auth()->user()->role->name === 'City_planner' || auth()->user()->role->name === 'Administrator'))
                                        @if(!$isApproved)
                                            <button type="button" class="delete-btn absolute z-10 top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>
                                        @endif
                                    @endif
                                @endif
                            </div>
                        @endfor
                    @endfor
                </div>
            </div>

            {{-- Simulation Controls --}}
            <section id="simulation-controls" aria-label="Simulation controls"
                class="mt-6 p-6 border-t border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 rounded-b-lg">
                <h2 class="text-lg font-semibold mb-4 text-sky-500 dark:text-teal-500">Simulation Controls</h2>

                {{-- Timeline --}}
                <div class="mb-6">
                    <label for="simulation-timeline" class="sr-only">Simulation timeline</label>
                    <input type="range" id="simulation-timeline" class="w-full" min="0" max="1080" value="0"
                        aria-valuemin="0" aria-valuemax="1080" aria-valuenow="0" aria-valuetext="06:00">
                    <div class="flex justify-between items-center mt-2 text-xs text-gray-500 dark:text-gray-400">
                        <span class="font-mono" aria-hidden="true">06:00</span>
                        <div class="flex flex-col items-center gap-1">
                            <span id="simulation-time-display"
                                class="font-bold font-mono text-sky-600 dark:text-teal-400 text-base tabular-nums"
                                aria-hidden="true">
                                06:00
                            </span>
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <div id="day-night-indicator" data-state="day" data-full-cycle="false" role="status"
                                    aria-live="polite" aria-atomic="true"
                                    class="flex items-center gap-2 px-3 py-1 rounded-full border text-xs font-semibold select-none transition-all duration-500
           [&[data-state=day]]:bg-amber-100 [&[data-state=day]]:border-amber-400 [&[data-state=day]]:text-amber-800
           dark:[&[data-state=day]]:bg-amber-900/40 dark:[&[data-state=day]]:border-amber-500 dark:[&[data-state=day]]:text-amber-300
           [&[data-state=night]]:bg-indigo-900 [&[data-state=night]]:border-indigo-400 [&[data-state=night]]:text-indigo-200">

                                    <span data-day class="flex items-center gap-2">
                                        <span class="text-base leading-none" aria-hidden="true">☀️</span>
                                        <span class="font-bold tracking-wide">Day</span>
                                        <span class="opacity-60 font-normal">(06:00 – 24:00)</span>
                                    </span>

                                    <span data-night class="hidden flex items-center gap-2">
                                        <span class="text-base leading-none" aria-hidden="true">🌙</span>
                                        <span class="font-bold tracking-wide">Night</span>
                                        <span class="opacity-60 font-normal">(00:00 – 06:00)</span>
                                    </span>
                                </div>
                                <button type="button" id="full-cycle-toggle" aria-pressed="false" data-active="false"
                                    aria-label="Day simulation only (06:00 to 24:00). Click to enable the full day/night cycle (06:00 to 06:00)."
                                    title="Day simulation (06:00 to 24:00). Click to enable the full day/night cycle (06:00 to 06:00)."
                                    class="px-3 py-1 rounded-full border text-xs font-semibold transition-all duration-200
                                    border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300
                                    hover:border-sky-400 hover:text-sky-600 dark:hover:border-teal-500 dark:hover:text-teal-400
                                    data-[active=true]:bg-sky-100 data-[active=true]:border-sky-500 data-[active=true]:text-sky-800
                                    dark:data-[active=true]:bg-teal-900/40 dark:data-[active=true]:border-teal-500 dark:data-[active=true]:text-teal-300">
                                    Day Simulation
                                </button>

                                {{-- Cycle Duration inline --}}
                                <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-lg border border-gray-200 dark:border-gray-700 mt-2 sm:mt-0">
                                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cycle</span>
                                    <span class="text-sm leading-none" aria-hidden="true">☀️</span>
                                    <input type="number" id="day-hours-input" min="1" max="23" value="18" step="1"
                                        class="w-11 text-center border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 text-sm dark:bg-gray-700 dark:text-white"
                                        aria-label="Day duration in hours (1–23)">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">h</span>
                                    <span class="text-gray-300 dark:text-gray-600 select-none">|</span>
                                    <span class="text-sm leading-none" aria-hidden="true">🌙</span>
                                    <input type="number" id="night-hours-input" min="1" max="23" value="6" step="1"
                                        class="w-11 text-center border border-gray-300 dark:border-gray-600 rounded px-1 py-0.5 text-sm dark:bg-gray-700 dark:text-white"
                                        aria-label="Night duration in hours (1–23)">
                                    <span class="text-xs text-gray-500 dark:text-gray-400">h</span>
                                </div>
                                <p id="duration-validation-msg" class="hidden text-xs text-red-500 dark:text-red-400 w-full text-center mt-1">
                                    Day + night must total 24 h
                                </p>
                            </div>
                            <span id="day-night-live" class="sr-only">Simulation cycle: Day only (06:00 to 24:00)</span>
                        </div>
                        <span id="timeline-end-label" class="font-mono" aria-hidden="true">24:00</span>
                    </div>
                </div>

                {{-- Speed + Playback + Active Events --}}
                <div class="flex flex-col sm:flex-row flex-wrap gap-4 sm:gap-8 items-center sm:items-start w-full">

                    {{-- Speed --}}
                    <div class="flex flex-col items-center gap-2">
                        <fieldset>
                            <legend
                                class="text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">
                                Simulation Speed
                            </legend>
                            <div class="flex gap-2 bg-gray-100 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700"
                                role="group">
                                @foreach([1, 2, 5] as $speed)
                                    <button type="button" onclick="setSimulationSpeed({{ $speed }})"
                                        class="speed-btn px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition focus:outline-none focus:ring-2 focus:ring-sky-500"
                                        data-speed="{{ $speed }}" aria-label="Set simulation speed to {{ $speed }}x"
                                        aria-pressed="false">{{ $speed }}×</button>
                                @endforeach
                            </div>
                        </fieldset>
                        <p class="text-xs text-gray-600 dark:text-gray-400" aria-live="polite">
                            Current: <span id="active-speed-display"
                                class="font-bold text-sky-600 dark:text-teal-500">1×</span>
                        </p>
                    </div>

                    {{-- Playback --}}
                    <div class="flex flex-col items-center gap-2">
                        <fieldset>
                            <legend
                                class="text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400 mb-2">
                                Animation Play
                            </legend>
                            <div class="flex gap-2 bg-gray-100 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700"
                                role="group">
                                <button type="button" id="reverseBtn" aria-label="Rewind simulation"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    <span aria-hidden="true">&#x23EA;</span>
                                </button>
                                <button type="button" id="playPauseBtn" aria-label="Play simulation"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    <span aria-hidden="true">&#x25B6;</span>
                                </button>
                                <button type="button" id="forwardBtn" aria-label="Skip simulation forward"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    <span aria-hidden="true">&#x23E9;</span>
                                </button>
                                <button type="button" id="replayBtn" aria-label="Restart simulation"
                                    class="px-4 py-2 bg-white dark:bg-gray-700 hover:bg-sky-600 hover:text-white text-gray-700 dark:text-gray-200 shadow-sm border border-gray-300 dark:border-gray-600 font-medium rounded transition focus:outline-none focus:ring-2 focus:ring-sky-500">
                                    <span aria-hidden="true">&#x21BB;</span>
                                </button>
                            </div>
                        </fieldset>
                    </div>

                    {{-- Active Events --}}
                    <div
                        class="w-full sm:w-auto flex-1 min-w-[200px] bg-gray-50 dark:bg-gray-800 p-4 rounded-md border border-gray-200 dark:border-gray-600">
                        <h3 class="text-sm font-semibold text-sky-500 dark:text-teal-500 mb-2">Active Events</h3>
                        <ul id="active-events-list" class="text-xs text-gray-700 dark:text-gray-300 space-y-1"
                            aria-live="polite" aria-label="Active simulation events">
                            <li class="text-gray-500">Loading events...</li>
                        </ul>
                    </div>

                </div>
            </section>
        </div>
        {{-- END MIDDLE --}}

        
        
        
        {{-- RIGHT: QoL Breakdown + Events panel --}}
        <div class="border-t xl:border-t-0 xl:border-l border-gray-400 dark:border-gray-700 w-full xl:w-64 p-3 flex flex-col gap-4 order-3 xl:overflow-y-auto xl:max-h-[73vh] xl:min-h-0">
            
            {{-- QoL Score --}}
            <div class="border-4 border-gray-400 dark:bg-indigo-900 dark:border-teal-600 rounded-md p-4">
                <span id="qol-score" class="text-xl font-semibold dark:text-teal-300">
                    QoL score: <span id="qol-score-value"></span>
                </span>
                <span id="old-qol-score" class="float-right text-gray-500"></span>
            </div>

            {{-- QoL Breakdown --}}
            <div id="breakdown-qol-score" aria-live="polite" aria-atomic="true"></div>

{{-- Upcoming Events --}}
<div id="upcoming-events-panel">
                <h2 class="text-lg font-semibold mb-2 dark:text-teal-500">Upcoming Events</h2>
                <ul id="upcoming-events-list" class="space-y-1 text-sm dark:text-white" aria-live="polite"
                    aria-label="Upcoming simulation events">
                    <li class="text-sm text-gray-500 italic">Loading events...</li>
                </ul>
            </div>

    {{-- QoL popup --}}
    <div id="qol-popup" role="tooltip" aria-label="Cell QoL influence"
        class="hidden fixed z-50 bg-slate-900 border border-slate-600 rounded-lg shadow-xl p-3 w-56 pointer-events-none opacity-0 scale-95 transition-all duration-150">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Cell QoL Influence</h3>
        <ul id="popup-neighbors-list" class="space-y-1"></ul>
    </div>

    <style>
        .bg-stripes {
            background-color: #fca5a5;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(239, 68, 68, 0.15) 10px, rgba(239, 68, 68, 0.15) 20px);
        }
        .dark .bg-stripes {
            background-color: #7f1d1d;
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(239, 68, 68, 0.3) 10px, rgba(239, 68, 68, 0.3) 20px);
        }
    </style>

    @vite(['resources/js/grid.js'])
</x-app-layout>