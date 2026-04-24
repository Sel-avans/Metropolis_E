@extends('layouts.app')

@section('content')
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="w-1/3 bg-gray-100 p-6 overflow-y-auto">
        <h1 class="text-2xl font-bold mb-4">Function Library</h1>

        <input 
            id="searchInput"
            type="text"
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
                            class="grid-cell border-2 border-dashed border-gray-300 h-24 flex items-center justify-center hover:bg-gray-100 transition"
                            data-row="{{ $row }}" 
                            data-col="{{ $col }}"
                        ></div>
                    @endfor
                </div>
            @endfor
        </div>
    </main>

</div>

<script>
document.getElementById('searchInput').addEventListener('input', function() {
    const input = this.value.toLowerCase();
    document.querySelectorAll('.library-item').forEach(item => {
        const name = item.querySelector('.library-name').textContent.toLowerCase();
        item.style.display = name.includes(input) ? '' : 'none';
    });
});
</script>
@endsection
