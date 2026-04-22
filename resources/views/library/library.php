@extends('layouts.app')

@section('content')
<div class="library-container">

    <aside class="library-panel">
        <h1 class="library-title">Function Library</h1>


        {{-- Dynamische categorieën + functies --}}
        @forelse($functions as $category => $items)
            <section class="library-category">
                <h2 class="category-title">{{ ucfirst($category) }}</h2>

                <div class="library-grid">
                    @foreach($items as $function)
                        <div class="library-item" tabindex="0" role="button" aria-label="{{ $function->name }}">
                            <img src="{{ asset($function->image) }}" alt="{{ $function->name }}" class="library-icon">
                            <p class="library-name">{{ $function->name }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="no-functions">No functions available.</p>
        @endforelse
    </aside>

    <main class="city-grid">
        <h1 class="grid-title">City Grid (3x4)</h1>

        <div class="grid-wrapper">
            @for($row = 1; $row <= 3; $row++)
                <div class="grid-row">
                    @for($col = 1; $col <= 4; $col++)
                        <div class="grid-cell" data-row="{{ $row }}" data-col="{{ $col }}" tabindex="0"></div>
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
