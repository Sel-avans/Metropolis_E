@extends('layouts.app')

@section('content')
<div class="page-wrapper">
    {{-- Linkerzijde: Function Library --}}
    <div class="library-section">
        <h1>Function Library</h1>

        @forelse($functions as $category => $items)
            <h2>{{ ucfirst($category) }}</h2>
            <ul>
                @foreach($items as $function)
                    <li class="library-item">
                        <img src="{{ asset('icons/' . $function['image']) }}" 
                             alt="{{ $function['name'] }}" 
                             class="library-icon">
                        <span class="library-name">{{ $function['name'] }}</span>
                    </li>
                @endforeach
            </ul>
        @empty
            <p>No functions available.</p>
        @endforelse
    </div>

    {{-- Rechterzijde: City Grid --}}
    <div class="grid-section">
        <h1>City Grid (3x4)</h1>

        <div class="grid-wrapper">
            @for($row = 1; $row <= 3; $row++)
                <div class="grid-row">
                    @for($col = 1; $col <= 4; $col++)
                        <div class="grid-cell" 
                            data-row="{{ $row }}" 
                            data-col="{{ $col }}">
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const cells = document.querySelectorAll('.grid-cell');

    cells.forEach(cell => {
        cell.setAttribute('tabindex', '0'); 

        cell.addEventListener('click', () => {
            cells.forEach(c => c.classList.remove('selected'));
            cell.classList.add('selected');
        });

        cell.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                cells.forEach(c => c.classList.remove('selected'));
                cell.classList.add('selected');
            }
        });
    });
});

</script>
@endsection
