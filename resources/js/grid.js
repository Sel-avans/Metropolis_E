document.addEventListener("DOMContentLoaded", () => {
    const cells = document.querySelectorAll(".grid-cell");
    const items = document.querySelectorAll(".library-item");

    let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;

    async function saveMove(oldRow, oldCol, newRow, newCol) {
        try {
            await fetch('/grid/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    old_row: oldRow,
                    old_col: oldCol,
                    new_row: newRow,
                    new_col: newCol,
                    function_id: draggedItem.id
                })
            });

            updateQoL();
        } catch (err) {
            console.error("Fout bij opslaan gridcel:", err);
        }
    }

    async function updateQoL() {
    try {
        const scoreEl = document.getElementById('qol-score-value');
        const breakdownEl = document.getElementById('breakdown-qol-score');

        if (!scoreEl && !breakdownEl) return;

        const response = await fetch('/qol/details');
        const data = await response.json();

        if (scoreEl) {
            scoreEl.textContent = data.total_score;
        }

        if (breakdownEl) {
            breakdownEl.innerHTML = renderQoLBreakdown(data);
        }

    } catch (err) {
        console.error("Fout bij ophalen QoL:", err);
    }
}
    function renderQoLBreakdown(data) {
        let html = '';

        html += '<h1 class="dark:text-teal-500">Breakdown QoL Score</h1>';
        for (const [category, info] of Object.entries(data.categories)) {
            html += `
                <h3 class="font-semibold mt-3 dark:text-teal-600">
                    ${category} (totaal: ${info.total})
                </h3>
            `;

            info.items.forEach(item => {
                html += `
                    <div class="flex justify-between text-gray-700 dark:text-white">
                        <span>${item.function}</span>
                        <span class="${item.value <= 0 ? 'text-red-600' : 'text-green-600'}">
                            ${item.value}
                        </span>
                    </div>
                `;
            });
        }

        html += `
            <h3 class="font-bold mt-4 dark:text-teal-600">
                Totale QoL: ${data.total_score}
            </h3>
        `;

        return html;
    }

    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true;

            draggedItem = {
                id: Number(item.dataset.functionId),
                name: item.dataset.functionName,
                image: item.dataset.image
            };

            e.dataTransfer.setDragImage(item.querySelector("img"), 16, 16);
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragstart", e => {
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;

            isDragging = true;

            draggedItem = {
                id: Number(img.dataset.functionId),
                name: img.alt,
                image: img.src
            };

            e.dataTransfer.setDragImage(img, 16, 16);

            sourceCell = cell;
            cell.classList.add("drag-source");
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragover", e => {
            e.preventDefault();
            cell.classList.add("drag-over");
        });

        cell.addEventListener("dragleave", () => {
            cell.classList.remove("drag-over");
        });

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            isDragging = false;

            cell.classList.remove("drag-over");

            const newRow = cell.dataset.row;
            const newCol = cell.dataset.col;

            let oldRow = null;
            let oldCol = null;

            if (sourceCell) {
                oldRow = sourceCell.dataset.row;
                oldCol = sourceCell.dataset.col;

                sourceCell.innerHTML = "";
                sourceCell.removeAttribute("draggable");
                sourceCell.classList.remove("drag-source");

                sourceCell = null;
            }

            cell.innerHTML = "";
            const img = document.createElement("img");
            img.src = draggedItem.image;
            img.alt = draggedItem.name;
            img.dataset.functionId = draggedItem.id;
            img.classList.add("grid-function-icon", "object-contain");
            cell.appendChild(img);
            cell.setAttribute("draggable", "true");

            await saveMove(oldRow, oldCol, newRow, newCol);
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

    updateQoL();
});
