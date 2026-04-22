document.addEventListener("DOMContentLoaded", () => {
    const cells = document.querySelectorAll(".grid-cell");
    const items = document.querySelectorAll(".library-item");

    let draggedItem = null;
    let isDragging = false;

    function createDragImage(src) {
        const canvas = document.createElement("canvas");
        canvas.width = 64;
        canvas.height = 64;

        const ctx = canvas.getContext("2d");
        const img = new Image();
        img.src = src;

        return new Promise(resolve => {
            img.onload = () => {
                ctx.drawImage(img, 0, 0, 64, 64);
                resolve(canvas);
            };
        });
    }

    items.forEach(item => {
        item.addEventListener("dragstart", async e => {
            isDragging = true;

            if (document.activeElement && document.activeElement.classList.contains('grid-cell')) {
                document.activeElement.blur();
            }

            draggedItem = {
                name: item.dataset.function,
                image: item.dataset.image
            };

            cells.forEach(c => c.classList.remove("selected"));

            const dragImg = await createDragImage(draggedItem.image);
            e.dataTransfer.setDragImage(dragImg, 32, 32);

            e.dataTransfer.effectAllowed = "copy";
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragstart", async e => {
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;

            isDragging = true;

            cell.blur();

            draggedItem = {
                name: img.alt,
                image: img.src
            };

            cells.forEach(c => c.classList.remove("selected"));

            const dragImg = await createDragImage(draggedItem.image);
            e.dataTransfer.setDragImage(dragImg, 32, 32);

            cell.innerHTML = "";
            cell.removeAttribute("draggable");
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragover", e => {
            e.preventDefault();

            if (isDragging) {
                cells.forEach(c => c.classList.remove("selected"));
            }

            cell.classList.add("drag-over");
        });

        cell.addEventListener("dragleave", () => {
            cell.classList.remove("drag-over");
        });

        cell.addEventListener("drop", e => {
            e.preventDefault();
            isDragging = false;

            cell.classList.remove("drag-over");

            cell.innerHTML = "";

            const img = document.createElement("img");
            img.src = draggedItem.image;
            img.alt = draggedItem.name;
            img.classList.add("grid-function-icon");

            cell.appendChild(img);

            cell.setAttribute("draggable", "true");
        });
    });


    cells.forEach(cell => {
        cell.setAttribute("tabindex", "0");

        cell.addEventListener("click", () => {
            if (isDragging) return;

            cells.forEach(c => c.classList.remove("selected"));
            cell.classList.add("selected");
        });

        cell.addEventListener("keydown", e => {
            if (isDragging) return;

            if (e.key === "Enter" || e.key === " ") {
                cells.forEach(c => c.classList.remove("selected"));
                cell.classList.add("selected");
            }
        });
    });
});
