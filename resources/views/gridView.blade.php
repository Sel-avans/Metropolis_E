<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metropolis - Grid & Library</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #888888; 
            margin: 0; 
            display: flex;
            justify-content: center;
        }

        .page-layout { 
            display: flex; 
            gap: 50px; 
            padding: 40px; 
            align-items: flex-start;
            width: 100%;
            max-width: 1200px;
        }

        /* The Library container */
        .library-panel { 
            background-color: #f4f4f4; 
            padding: 20px; 
            border-radius: 10px; 
            width: 250px; /* Slightly wider for the scrollbar */
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: sticky;
            top: 40px;
        }

        /* The scrollable box for functions */
        .library-grid {
            max-height: 60vh; /* Limits height to 60% of viewport */
            overflow-y: auto;  /* Adds scrollbar when content exceeds height */
            padding-right: 10px;
            display: flex;
            flex-direction: column;
        }

        .library-item { 
            background-color: white; 
            border: 1px solid #ccc; 
            padding: 10px; 
            margin-bottom: 10px; 
            cursor: grab; 
            text-align: center; 
            border-radius: 5px;
            transition: opacity 0.2s;
        }

        /* Grid specific styles */
        main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%; 
        }

        h1 { color: black; margin-bottom: 20px; }

        .grid { 
            display: grid; 
            width: 100%; 
            max-width: 75vh; 
            aspect-ratio: 1 / 1; 
            background-color: #eeeeee; 
            border: 2px solid black;
            margin: 0 auto; 
        }
        
        .grid-item { 
            border: 1px solid #ccc; 
            background-color: #eeeeee;
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%; 
            cursor: pointer;
            overflow: hidden;
        }

        .grid-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>
    <div class="page-layout">
        <aside class="library-panel">
            <h2 style="color: #333; margin-top: 0;">Functies</h2>
            <div class="library-grid">
                @foreach($functions as $function)
                    <div class="library-item" 
                         draggable="true" 
                         data-id="{{ $function->id }}" 
                         data-name="{{ $function->name }}" 
                         data-image="{{ asset($function->image) }}">
                        
                        <img src="{{ asset($function->image) }}" alt="{{ $function->name }}" style="width: 50px; height: 50px; object-fit: contain; pointer-events: none;">
                        <p style="margin: 5px 0 0 0; font-weight: bold; color: #333;">{{ $function->name }}</p>
                    </div>
                @endforeach
            </div>
        </aside>

        <main>
            <h1>City Planner Grid</h1>
            <div id="grid-container" style="width: 100%; max-width: 800px;">
                <?php
                    // Calling the controller that paints the grid
                    $grid = new \App\Http\Controllers\Grid(12);
                    $grid->paintGrid();
                ?>
            </div>
        </main>
    </div>

<script>
    // Global state for the dragged element
    let draggedData = { 
        id: null, 
        image: null,
        sourceX: null, 
        sourceY: null 
    };

    const draggables = document.querySelectorAll('.library-item');
    const cells = document.querySelectorAll('.grid-item, .grid-cell');

    // 1. Logic for dragging from the Library
    draggables.forEach(item => {
        item.addEventListener('dragstart', () => {
            draggedData.id = item.getAttribute('data-id');
            draggedData.image = item.getAttribute('data-image'); 
            draggedData.sourceX = null; 
            draggedData.sourceY = null;
            item.style.opacity = '0.5';
        });
        item.addEventListener('dragend', () => item.style.opacity = '1');
    });

    // 2. Logic to make grid cells draggable (for moving items)
    function enableGridDrag(cell) {
        cell.setAttribute('draggable', 'true');
        cell.addEventListener('dragstart', (e) => {
            const img = cell.querySelector('img');
            if (!img) return;

            draggedData.id = cell.getAttribute('data-function-id');
            draggedData.image = img.getAttribute('src');
            draggedData.sourceX = cell.getAttribute('data-col') || cell.getAttribute('data-x');
            draggedData.sourceY = cell.getAttribute('data-row') || cell.getAttribute('data-y');
            
            cell.style.opacity = '0.5';
        });
        cell.addEventListener('dragend', () => {
            cell.style.opacity = '1';
        });
    }

    // 3. Drop logic (Moving or Placing)
    cells.forEach(cell => {
        cell.addEventListener('dragover', e => e.preventDefault()); 

        cell.addEventListener('drop', async (e) => {
            e.preventDefault();

            const targetX = cell.getAttribute('data-col') || cell.getAttribute('data-x');
            const targetY = cell.getAttribute('data-row') || cell.getAttribute('data-y');

            if (!targetX || !targetY || !draggedData.id) return;

            // Remove item from old location if moving within grid
            if (draggedData.sourceX !== null && draggedData.sourceY !== null) {
                const sourceCell = document.querySelector(
                    `.grid-item[data-col="${draggedData.sourceX}"][data-row="${draggedData.sourceY}"], 
                     .grid-cell[data-col="${draggedData.sourceX}"][data-row="${draggedData.sourceY}"],
                     .grid-item[data-x="${draggedData.sourceX}"][data-y="${draggedData.sourceY}"],
                     .grid-cell[data-x="${draggedData.sourceX}"][data-y="${draggedData.sourceY}"]`
                );
                if (sourceCell) {
                    sourceCell.innerHTML = '';
                    sourceCell.removeAttribute('draggable');
                    sourceCell.removeAttribute('data-function-id');
                    sourceCell.style.backgroundColor = "#eeeeee"; // Reset to grid grey
                }
            }

            // Place item in new cell
            cell.innerHTML = `<img src="${draggedData.image}" style="opacity: 1;">`;
            cell.style.backgroundColor = "#eeeeee"; // Ensure background stays grey
            cell.style.opacity = '1';
            cell.setAttribute('data-function-id', draggedData.id);
            enableGridDrag(cell); 

            // Trigger Autosave
            try {
                await fetch('/save-cell', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        x: targetX,
                        y: targetY,
                        city_function_id: draggedData.id,
                        oldX: draggedData.sourceX,
                        oldY: draggedData.sourceY
                    })
                });
            } catch (error) {
                console.error("Autosave failed:", error);
            }
        });
    });

    // 4. Load saved cells on page refresh
    const savedCells = @json($savedCells);
    savedCells.forEach(cell => {
        const gridItem = document.querySelector(
            `.grid-item[data-col="${cell.x}"][data-row="${cell.y}"], 
             .grid-cell[data-col="${cell.x}"][data-row="${cell.y}"],
             .grid-item[data-x="${cell.x}"][data-y="${cell.y}"],
             .grid-cell[data-x="${cell.x}"][data-y="${cell.y}"]`
        );
        
        if (gridItem && cell.city_function) {
            gridItem.innerHTML = `<img src="/${cell.city_function.image}" style="opacity: 1;">`;
            gridItem.setAttribute('data-function-id', cell.city_function_id);
            enableGridDrag(gridItem); 
        }
    });
</script>

</body>
</html>