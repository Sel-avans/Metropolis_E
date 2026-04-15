<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grid template</title>

    <style>
       body {
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-color: #f4f4f4;
        background-color: #888888;
        text-align: center;
        }

        main {    
            display:flex;
            flex-flow: row wrap;
            justify-content: center;
        }


        .container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            max-width: 90vw;
        }

        .grid {
            display: grid;
            grid-gap: 0px;
            width: 100%;
            aspect-ratio: 1 / 1;
        }


        .grid-item {
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            background-color: #cccccc;
            font-size: 1.5rem;
            border: 2px solid black;
        }

        .colour-uncolored {
            background-color: #3498db;
        }

        .colour-path {
            background-color: rgb(167, 166, 166);;
        }
        .colour-originpath {
            background-color: goldenrod;
        }
        .colour-destinationpath {
            background-color: purple;
        }
        .colour-calcpath {
            background-color: green;
        }

        .colour-blocked {
            background-color: rgb(22, 18, 62);
        }

        .tile-origin,
        .tile-destination {
            background-color: rgb(67, 66, 66);
        }

        .tile-origin::after {
            content: 'O';
        }

        .tile-destination::after {
            content: 'D';
        }

        .tile-blocked::after {
            content: 'B';
        }
    </style>
</head>
<body>

    <main>
    
        <?php
        $grid = new \App\Http\Controllers\Grid(12);
        $grid->paintGrid();
       ?>
    </main>
</body>
</html>