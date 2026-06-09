@extends('layouts.app')

@section('content')
<div class="flex min-h-screen relative"> {{-- Sidebar --}}
    <aside class="w-1/3 bg-gray-100 p-6 overflow-y-auto">
        <h1 class="text-2xl font-bold mb-4">Function Library</h1>

        <label for="searchInput" class="sr-only">{{ __('Search') }}</label>
        <input 
            id="searchInput"
            type="text"
            aria-label="{{ __('Search') }}"
            placeholder="Search..."
            class="w-full mb-4 p-2 border rounded"
        >

        @forelse($functions as $category => $items)
            <section class="mb-6">
                <h2 class="text-xl font-semibold mb-2">
                    {{ ucfirst($category) }}
                </h2>

                <div class="grid grid-cols-2 gap-3">
                    @foreach($items as $function)
                        <div 
                            class="library-item p-3 bg-white rounded shadow cursor-pointer flex flex-col items-center text-center hover:bg-gray-100 transition"
                            tabindex="0"
                            role="button"
                            draggable="true"
                            data-function-id="{{ $function->id }}"
                            data-function-name="{{ $function->name }}"
                            data-image="{{ asset($function->image) }}"
                            aria-label="{{ $function->name }}"
                        >
                            <img 
                                src="{{ asset($function->image) }}" 
                                alt="{{ $function->name }}" 
                                class="w-10 h-10 mb-2"
                            >

                            <p class="library-name text-sm font-medium">
                                {{ $function->name }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-gray-500">No functions available.</p>
        @endforelse
    </aside>

    {{-- Grid --}}
    <main class="w-2/3 p-6">
        <h1 class="text-2xl font-bold mb-4">City Grid (3x4)</h1>

        <div class="grid grid-rows-3 gap-4">
            @for($row = 1; $row <= 3; $row++)
                <div class="grid grid-cols-4 gap-4">
                    @for($col = 1; $col <= 4; $col++)
                        <div 
                            class="grid-cell border-2 border-dashed border-gray-300 h-24 flex items-center justify-center hover:bg-gray-100 transition relative"
                            data-row="{{ $row }}" 
                            data-col="{{ $col }}"
                            tabindex="0"
                        ></div>
                    @endfor
                </div>
            @endfor
        </div>
    </main>

    {{-- TOEGEVOEGD: Het dynamische preview-paneel dat vereist is voor library-preview.js --}}
    <div id="library-preview" class="fixed hidden opacity-0 z-50 w-64 bg-slate-900 text-white rounded-lg p-4 shadow-xl border border-slate-700/50 pointer-events-none transition-all duration-150 scale-100">
        <div class="flex justify-between items-start mb-2 border-b border-slate-700/50 pb-1">
            <h3 id="preview-title" class="font-bold text-sm text-teal-400">Loading...</h3>
            <button id="preview-close" type="button" class="text-gray-400 hover:text-white text-xs cursor-pointer px-1 rounded hover:bg-slate-800" aria-label="Sluit preview">✖</button>
        </div>
        <div id="preview-body" class="text-xs text-slate-300 space-y-2">
            </div>
    </div>

</div>

<script>
// Filter functionaliteit
document.getElementById('searchInput').addEventListener('input', function() {
    const input = this.value.toLowerCase();
    document.querySelectorAll('.library-item').forEach(item => {
        const name = item.querySelector('.library-name').textContent.toLowerCase();
        item.style.display = name.includes(input) ? '' : 'none';
    });
});
</script>
@endsection