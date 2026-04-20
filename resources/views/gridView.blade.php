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

        /* De Bibliotheek aan de linkerkant */
        .library { 
            background-color: #f4f4f4; 
            padding: 20px; 
            border-radius: 10px; 
            width: 200px; 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .draggable-item { 
            padding: 15px; 
            margin-bottom: 10px; 
            color: white; 
            font-weight: bold;
            text-align: center; 
            border-radius: 5px; 
            cursor: grab;
            border: 1px solid rgba(0,0,0,0.1);
        }

        /* Het Grid gedeelte */
        main {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%; /* Zorgt dat de grid ruimte krijgt */
        }

        h1 { color: black; margin-bottom: 20px; }

        /* Het flexibele grid */
        .grid { 
            display: grid; 
            width: 100%; 
            max-width: 75vh; 
            aspect-ratio: 1 / 1; /* Houdt het complete grid altijd in de vorm van een perfect vierkant */
            background-color: #eeeeee; 
            border: 2px solid black;
            margin: 0 auto; /* Zet het grid netjes in het midden */
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
                 data-image="{{ asset($function->image) }}"
                 style="background-color: white; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; cursor: grab; text-align: center; border-radius: 5px;">
                
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
                    // Hier roepen we de controller aan die het grid tekent
                    $grid = new \App\Http\Controllers\Grid(12);
                    $grid->paintGrid();
                ?>
            </div>
        </main>
    </div>

<script>
    const draggables = document.querySelectorAll('.library-item');
    const cells = document.querySelectorAll('.grid-item, .grid-cell');

    let draggedData = { 
        id: null, 
        image: null,
        sourceX: null, 
        sourceY: null 
    };

    // 1. Slepen vanuit de Library (Nieuwe items)
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

    // 2. Functie om een grid-vakje sleepbaar te maken
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
        cell.addEventListener('dragend', () => cell.style.opacity = '1');
    }

    // 3. Drop logica (Verplaatsen of Plakken)
    cells.forEach(cell => {
        cell.addEventListener('dragover', e => e.preventDefault()); 

        cell.addEventListener('drop', async (e) => {
            e.preventDefault();

            const targetX = cell.getAttribute('data-col') || cell.getAttribute('data-x');
            const targetY = cell.getAttribute('data-row') || cell.getAttribute('data-y');

            if (!targetX || !targetY || !draggedData.id) {
                console.warn("Drop mislukt: missende data", {targetX, targetY, id: draggedData.id});
                return;
            }

            // STAP A: Als we verplaatsen (sourceX is gevuld), maak de oude cel leeg
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
                }
            }

            // STAP B: Vul de nieuwe cel
            cell.innerHTML = `<img src="${draggedData.image}" style="width: 100%; height: 100%; object-fit: cover;">`;
            cell.style.backgroundColor = "transparent";
            cell.setAttribute('data-function-id', draggedData.id);
            enableGridDrag(cell); // Maak deze cel nu ook sleepbaar

            // STAP C: Database Update (Autosave)
            try {
                const response = await fetch('/save-cell', {
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
                
                const result = await response.json();
                console.log("Server response:", result);
            } catch (error) {
                console.error("Autosave error:", error);
            }
        });
    });

    // 4. Inladen bij start
    const savedCells = @json($savedCells);
    savedCells.forEach(cell => {
        const gridItem = document.querySelector(
            `.grid-item[data-col="${cell.x}"][data-row="${cell.y}"], 
             .grid-cell[data-col="${cell.x}"][data-row="${cell.y}"],
             .grid-item[data-x="${cell.x}"][data-y="${cell.y}"],
             .grid-cell[data-x="${cell.x}"][data-y="${cell.y}"]`
        );
        
        if (gridItem && cell.city_function) {
            gridItem.innerHTML = `<img src="/${cell.city_function.image}" style="width: 100%; height: 100%; object-fit: cover;">`;
            gridItem.style.backgroundColor = "transparent";
            gridItem.setAttribute('data-function-id', cell.city_function_id);
            enableGridDrag(gridItem); // Zorg dat opgeslagen items ook gesleept kunnen worden
        }
    });
</script>

</body>
</html>