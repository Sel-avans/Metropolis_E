<x-app-layout>
    <div class="flex gap-4">
        
        {{-- Library Sidebar --}}
        <div class="w-auto p-6 max-h-[93vh] overflow-y-auto">
            <div class="flex flex-col margin-bottom-4 gap-3">    
                <h1 class="text-2xl dark:text-teal-500 font-bold mb-4">Function Library</h1>
                <a href="{{ route('effects.index') }}" class="px-3 py-1.5 bg-teal-600 text-white rounded hover:bg-teal-700 text-xs shadow focus:outline-none focus:ring-2 focus:ring-teal-500">Effect Functions</a>
                <a href="{{ route('functions.index') }}" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs shadow focus:outline-none focus:ring-2 focus:ring-blue-500">Function Management</a>
                <a href="{{ route('conditions.index') }}" class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs shadow focus:outline-none focus:ring-2 focus:ring-blue-500">Conditions</a>
            </div>

            @forelse($functions as $category => $items)
                <h2 class="text-xl dark:text-teal-600 font-semibold mt-4 mb-2">{{ ucfirst($category) }}</h2>
                <ul class="mt-2 space-y-2 dark:text-white">
                    @foreach($items as $function)
                        <li class="library-item flex items-center gap-2 px-4 py-2 border border-gray-400 dark:border-white rounded cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500"
                            draggable="true"
                            data-function-id="{{ $function->id }}"
                            data-function-name="{{ $function->name }}"
                            data-image="{{ asset($function->image) }}">
                            <img src="{{ asset($function->image) }}" alt="image of {{ $function->name }}" class="w-8 h-8 object-contain pointer-events-none">
                            <span class="library-name text-base">{{ $function->name }}</span>
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-gray-500">No functions available.</p>
            @endforelse
        </div>

        {{-- Main Grid Area --}}
        <div class="flex flex-col mx-auto">
            <div class="w-auto h-min mt-4 border-4 border-gray-400 dark:bg-indigo-800 dark:border-teal-600 rounded-md p-6">
                <span id="qol-score" class="text-xl font-semibold mb-2 dark:text-teal-300">QoL score: <span id="qol-score-value"></span></span>
                <span id="old-qol-score" class="float-right"></span>
            </div>

            <button id="undo-btn" class="mt-4 px-4 py-2 bg-yellow-500 text-black font-semibold rounded shadow hover:bg-yellow-600 transition">Undo</button>

            @if(auth()->user() && auth()->user()->role->name === 'City_planner')
                <button type="button" id="approve-btn" class="mt-2 px-4 py-2 bg-amber-600 text-white font-semibold rounded shadow hover:bg-amber-700 transition">Approve & Lock Area</button>
            @endif

            <div class="w-auto p-6">
                <h1 class="text-2xl text-center font-bold mb-4 dark:text-teal-300">City Grid (3x4)</h1>

                <div class="city-grid grid grid-flow-col grid-rows-4 gap-3 w-min">
                    @for($col = 1; $col <= 3; $col++)
                        @for($row = 1; $row <= 4; $row++)
                            @php
                                $cell = $grid->first(fn($c) => $c->row == $row && $c->col == $col);
                                $isApproved = $cell && $cell->is_approved;
                                $isCityPlanner = auth()->user() && auth()->user()->role->name === 'City_planner';
                                // Lock klasse alleen als het APPROVED is EN gebruiker GEEN city planner is
                                $lockClasses = ($isApproved && !$isCityPlanner) ? 'pointer-events-none' : '';
                            @endphp
                            
                            <div class="grid-cell relative border-2 w-32 h-32 items-center justify-center cursor-pointer transition focus:outline-none focus:ring-2 focus:ring-blue-500
                                       {{ $isApproved ? 'is-locked bg-stripes opacity-60 border-red-600 ' . $lockClasses : 'bg-gray-300 border-gray-800 dark:bg-blue-950 dark:border-gray-300 hover:bg-gray-400 hover:dark:bg-gray-100' }}"
                                data-row="{{ $row }}"
                                data-col="{{ $col }}"
                                data-id="{{ $cell ? $cell->id : '' }}"
                                draggable="{{ $cell && !$isApproved ? 'true' : 'false' }}"
                                role="button">
                                
                                <div class="lock-indicator absolute top-1 left-1 bg-red-600 text-white text-[10px] font-bold px-1 rounded flex items-center gap-0.5 shadow {{ $isApproved ? '' : 'hidden' }}">
                                    🔒 <span class="uppercase text-[9px]">Locked</span>
                                </div>

                                @if(!empty($cell) && !empty($cell->function))
                                    <img src="{{ asset($cell->function->image) }}"
                                         alt="{{ $cell->function->name }}"
                                         class="grid-function-icon object-contain w-full h-full p-4"
                                         data-function-id="{{ $cell->function->id }}">

                                    @if(!$isApproved)
                                        <button type="button" class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>
                                    @endif
                                @endif
                            </div>
                        @endfor
                    @endfor
                </div>
            </div>
        </div>

        <div id="breakdown-qol-score" class="border-solid border-l border-gray-400 dark:border-white w-2/12 p-3 ml-auto"></div>
    </div>

    {{-- Pop-up en Stijlen --}}
    <div id="qol-popup" class="absolute z-50 bg-slate-800/95 text-slate-50 border border-slate-600 rounded-lg p-3 shadow-lg min-w-[200px] pointer-events-none opacity-0 scale-95 transition-all duration-150 hidden">
        <div class="font-bold border-b border-slate-600 pb-1.5 mb-2 text-[10px] uppercase tracking-wider text-slate-300">Quality of Life Impact</div>
        <div><ul id="popup-neighbors-list" class="space-y-1 text-xs"></ul></div>
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