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
            background-color: white; 
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
            height: 100%; /* Vult het vierkante grid netjes op */
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="page-layout">
        
    <aside class="library-panel" style="background-color: #f4f4f4; padding: 20px; border-radius: 10px; width: 200px;">
    <h2 style="color: #333; margin-top: 0;">Functies</h2>
    
    <div class="library-grid">
        @foreach($functions as $function)
            <div class="library-item" 
                 draggable="true" 
                 data-id="{{ $function->id }}" 
                 data-name="{{ $function->name }}" 
                 data-image="{{ asset($function->image) }}"
                 style="background-color: white; border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; cursor: grab; text-align: center; border-radius: 5px;">
                
                <img src="{{ asset($function->image) }}" alt="{{ $function->name }}" style="width: 50px; height: 50px; object-fit: contain;">
                
                <p class="library-name" style="margin: 5px 0 0 0; font-weight: bold; color: #333;">{{ $function->name }}</p>
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
    // We selecteren de bibliotheek items en de grid vakjes
    const draggables = document.querySelectorAll('.library-item');
    const cells = document.querySelectorAll('.grid-item, .grid-cell');

    let draggedData = { id: null, name: null, image: null };

    // 1. Sleep-logica
    draggables.forEach(item => {
        item.addEventListener('dragstart', () => {
            draggedData.id = item.getAttribute('data-id');
            draggedData.name = item.getAttribute('data-name');
            draggedData.image = item.getAttribute('data-image'); 
            item.style.opacity = '0.5';
        });
        item.addEventListener('dragend', () => item.style.opacity = '1');
    });

    // 2. Drop-logica
    cells.forEach(cell => {
        cell.addEventListener('dragover', e => e.preventDefault()); 

        cell.addEventListener('drop', async (e) => {
            e.preventDefault();

            // We checken alle mogelijke namen voor de coördinaten (x/y of col/row)
            const x = cell.getAttribute('data-col') || cell.getAttribute('data-x');
            const y = cell.getAttribute('data-row') || cell.getAttribute('data-y');

            if (!x || !y) {
                console.error("Fout: Dit grid-vakje heeft geen coördinaten!", cell);
                return;
            }

            // Directe visuele feedback met het plaatje
            cell.innerHTML = `<img src="${draggedData.image}" style="width: 100%; height: 100%; object-fit: cover;">`;
            cell.style.backgroundColor = "transparent";

            // Opslaan in de database (Autosave)
            try {
                const response = await fetch('/save-cell', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ x: x, y: y, city_function_id: draggedData.id })
                });
                if (response.ok) console.log("Opgeslagen!");
            } catch (error) {
                console.error("Opslaan mislukt:", error);
            }
        });
    });

    // 3. Inladen bij refresh (De 'savedCells' maar één keer declareren!)
    const savedCells = @json($savedCells);
    
    savedCells.forEach(cell => {
        // Zoek het vakje op alle mogelijke manieren (flexibel voor verschillende controllers)
        const gridItem = document.querySelector(
            `.grid-item[data-col="${cell.x}"][data-row="${cell.y}"], 
             .grid-cell[data-col="${cell.x}"][data-row="${cell.y}"],
             .grid-item[data-x="${cell.x}"][data-y="${cell.y}"],
             .grid-cell[data-x="${cell.x}"][data-y="${cell.y}"]`
        );
        
        if (gridItem && cell.city_function) {
            gridItem.innerHTML = `<img src="/${cell.city_function.image}" style="width: 100%; height: 100%; object-fit: cover;">`;
            gridItem.style.backgroundColor = "transparent";
        }
    });
</script>
    <script>
        // Haal de opgeslagen cellen uit Laravel en geef ze aan JavaScript
        const savedCells = @json($savedCells);

        // Loop door elke opgeslagen cel heen
        savedCells.forEach(cell => {
            // Zoek het juiste vakje op basis van X en Y
            const gridItem = document.querySelector(`.grid-item[data-x="${cell.x}"][data-y="${cell.y}"]`);
            
            if (gridItem && cell.city_function) {
                gridItem.innerText = cell.destination.name;
                gridItem.style.backgroundColor = cell.destination.color;
                gridItem.style.color = "white";
                gridItem.style.fontWeight = "bold";
            }
        });
    </script>
</body>
</html>