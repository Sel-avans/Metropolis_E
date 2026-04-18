<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metropolis - Grid & Library</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        /* De basis layout zoals in je screenshot */
        body { 
            font-family: Arial, sans-serif; 
            background-color: #888888; /* De grijze achtergrond uit je screenshot */
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

        /* Jouw Bibliotheek aan de linkerkant */
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
        }

        h1 { color: black; margin-bottom: 20px; }

        /* Styling voor het grid van je teamgenoot */
        .grid { 
            display: grid; 
            background-color: white; 
            border: 2px solid black;
            /* De grid-template wordt vaak via PHP gezet, maar we zorgen voor een fallback */
        }
        
        .grid-item { 
            border: 1px solid #ccc; 
            background-color: #eeeeee;
            display: flex;
            justify-content: center;
            align-items: center;
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
                     data-type="{{ $destination->name }}" 
                     data-color="{{ $destination->color }}"
                     style="background-color: {{ $destination->color }};">
                     {{ $destination->name }}
                </div>
            @endforeach
        </aside>

        <main>
            <h1>Default grid</h1>
            <?php
            // Hier roepen we zijn controller aan die het grid tekent
            $grid = new \App\Http\Controllers\Grid(12);
            $grid->paintGrid();
            ?>
        </main>
    </div>

    <script>
        // Simpele drag & drop logica om het weer werkend te maken
        const draggables = document.querySelectorAll('.draggable-item');
        const cells = document.querySelectorAll('.grid-item');
        let draggedType = null;
        let draggedColor = null;

        draggables.forEach(item => {
            item.addEventListener('dragstart', () => {
                draggedType = item.getAttribute('data-type');
                draggedColor = item.getAttribute('data-color');
            });
        });

        cells.forEach(cell => {
            cell.addEventListener('dragover', e => e.preventDefault());
            cell.addEventListener('drop', () => {
                if (cell.innerText === "" || cell.innerText === " ") {
                    cell.innerText = draggedType;
                    cell.style.backgroundColor = draggedColor;
                }
            });
        });
    </script>
</body>
</html>