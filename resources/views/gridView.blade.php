@extends('layouts.app')

@section('content')

<span id="qol-score" onclick="openQolModal()" class="cursor-pointer">
    QoL score: <span id="qol-score-value">–</span>
</span>


<div id="qol-details-modal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 max-w-xl w-full max-h-[80vh] overflow-y-auto rounded-lg relative">

        <button id="qol-close" class="absolute top-3 right-3 text-lg cursor-pointer">✕</button>

        <h2 class="text-xl font-bold mb-4">Quality of Life details</h2>

        <div id="qol-details-content"></div>
    </div>
</div>

<div class="flex gap-8 w-full items-start">

    {{-- Linkerzijde: Function Library --}}
    <div class="library-section w-1/4 min-w-[200px]">

        <div class="flex items-center justify-between mb-4">
            <h1 class="text-xl font-bold">Function Library</h1>

            <a href="{{ route('effects.index') }}" 
               class="px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-xs">
                Manage Functions
            </a>
        </div>

        @forelse($functions as $category => $items)
            <h2 class="text-lg font-semibold mt-4">{{ ucfirst($category) }}</h2>

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
            <p>No functions available.</p>
        @endforelse
    </div>

    {{-- Rechterzijde: City Grid --}}
    <div class="grid-section w-3/4 min-w-[400px] mt-10 ml-4">
        <h1 class="text-3xl font-bold mb-4">City Grid (3x4)</h1>

        <div class="grid-wrapper space-y-2">
            @for($row = 1; $row <= 3; $row++)
                <div class="grid-row flex gap-2">
                    @for($col = 1; $col <= 4; $col++)

                        @php
                            $cell = $grid->where('row', $row)
                                ->where('col', $col)
                                ->first();
                        @endphp

                        <div class="grid-cell w-20 h-20 border rounded flex items-center justify-center"
                            data-row="{{ $row }}"
                            data-col="{{ $col }}"
                            draggable="{{ $cell ? 'true' : 'false' }}">

                            @if($cell && $cell->function)
                                <img src="{{ asset($cell->function->image) }}"
                                    alt="{{ $cell->function->name }}"
                                    class="grid-function-icon w-12 h-12 object-contain">
                            @endif

                        </div>

                    @endfor
                </div>
            @endfor
        </div>
    </div>

</div>

@endsection
