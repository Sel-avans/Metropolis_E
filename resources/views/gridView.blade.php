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
        
        <aside class="library">
            <h2 style="color: #333; margin-top: 0;">Bibliotheek</h2>
            @foreach($destinations as $destination)
                <div class="draggable-item" 
                     draggable="true" 
                     data-id="{{ $destination->id }}" 
                     data-name="{{ $destination->name }}" 
                     data-color="{{ $destination->color }}"
                     style="background-color: {{ $destination->color }};">
                     {{ $destination->name }}
                </div>
            @endforeach
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
        const draggables = document.querySelectorAll('.draggable-item');
        const cells = document.querySelectorAll('.grid-item');

        // Tijdelijke opslag voor wat je sleept
        let draggedData = {
            id: null,
            name: null,
            color: null
        };

        // 1. Start met slepen
        draggables.forEach(item => {
            item.addEventListener('dragstart', () => {
                draggedData.id = item.getAttribute('data-id');
                draggedData.name = item.getAttribute('data-name');
                draggedData.color = item.getAttribute('data-color');
                item.style.opacity = '0.5';
            });

            item.addEventListener('dragend', () => {
                item.style.opacity = '1';
            });
        });

        // 2. Laat los op het grid
        cells.forEach(cell => {
            cell.addEventListener('dragover', e => e.preventDefault()); // Dit is nodig om te mogen droppen

            cell.addEventListener('drop', async (e) => {
                e.preventDefault();

                // Haal coördinaten uit de cel
                const x = cell.getAttribute('data-x');
                const y = cell.getAttribute('data-y');

                // Visuele update (Direct zichtbaar)
                cell.innerText = draggedData.name;
                cell.style.backgroundColor = draggedData.color;
                cell.style.color = "white";
                cell.style.fontWeight = "bold";

                // Sla op in de database via de Laravel route
                if(x !== null && y !== null) {
                    try {
                        const response = await fetch('/save-cell', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                x: x,
                                y: y,
                                destination_id: draggedData.id
                            })
                        });
                        
                        if (!response.ok) {
                            console.error("Database opslaan mislukt");
                        }
                    } catch (error) {
                        console.error("Fout tijdens fetch:", error);
                    }
                } else {
                    console.warn("WAARSCHUWING: Deze cel mist een data-x of data-y attribuut!");
                }
            });
        });
    </script>
    <script>
        // Haal de opgeslagen cellen uit Laravel en geef ze aan JavaScript
        const savedCells = @json($savedCells);

        // Loop door elke opgeslagen cel heen
        savedCells.forEach(cell => {
            // Zoek het juiste vakje op basis van X en Y
            const gridItem = document.querySelector(`.grid-item[data-x="${cell.x}"][data-y="${cell.y}"]`);
            
            if (gridItem && cell.destination) {
                gridItem.innerText = cell.destination.name;
                gridItem.style.backgroundColor = cell.destination.color;
                gridItem.style.color = "white";
                gridItem.style.fontWeight = "bold";
            }
        });
    </script>
</body>
</html>