@extends('layouts.app')

@section('content')
<div class="flex min-h-screen">

    {{-- Linkerzijde: Function Library --}}
    <div class="w-1/3 p-6 bg-gray-100">
        <h1 class="text-2xl font-bold mb-4">Function Library</h1>

        @forelse($functions as $category => $items)
            <h2 class="text-xl font-semibold mt-4 mb-2">
                {{ ucfirst($category) }}
            </h2>

            <ul class="space-y-2">
                @foreach($items as $function)
                    <li 
                        class="library-item flex items-center gap-3 p-2 bg-white rounded shadow cursor-grab"
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
        <h1 class="text-2xl font-bold mb-4">City Grid (3x4)</h1>

        <div class="grid grid-rows-3 gap-4">
            @for($row = 1; $row <= 3; $row++)
                <div class="grid grid-cols-4 gap-4">
                    @for($col = 1; $col <= 4; $col++)
                        <div 
                            class="grid-cell border-2 border-dashed border-gray-300 h-24 flex items-center justify-center hover:bg-gray-100 transition"
                            data-row="{{ $row }}" 
                            data-col="{{ $col }}"
                        >
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>

</div>
@endsection