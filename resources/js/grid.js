document.addEventListener("DOMContentLoaded", () => {

    const cells = document.querySelectorAll(".grid-cell");
    const items = document.querySelectorAll(".library-item");

    let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;

    async function saveMove(oldRow, oldCol, newRow, newCol, functionName) {
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
                    function: functionName
                })
            });

            updateQoL();
        } catch (err) {
            console.error("Fout bij opslaan gridcel:", err);
        }
    }

    async function updateQoL() {
        try {
            const response = await fetch('/qol/details');
            const data = await response.json();
            document.getElementById('qol-score-value').textContent = data.total_score;
        } catch (err) {
            console.error("Fout bij ophalen QoL:", err);
        }
    }

    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true;

            draggedItem = {
                name: item.dataset.function,
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
            img.classList.add("grid-function-icon", "object-contain");
            cell.appendChild(img);
            cell.setAttribute("draggable", "true");

            await saveMove(oldRow, oldCol, newRow, newCol, draggedItem.name);
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

    window.openQolModal = function() {
        fetch('/qol/details')
            .then(res => res.json())
            .then(data => {

                let html = '';

                for (const [category, info] of Object.entries(data.categories)) {
                    html += `
                        <h3 class="font-semibold mt-3">
                            ${category} (totaal: ${info.total})
                        </h3>
                    `;

                    info.items.forEach(item => {
                        html += `
                            <div class="flex justify-between">
                                <span>${item.function}</span>
                                <span class="${item.value < 0 ? 'text-red-600' : 'text-green-600'}">
                                    ${item.value}
                                </span>
                            </div>
                        `;
                    });
                }

                html += `
                    <h3 class="font-bold mt-4">
                        Totale QoL: ${data.total_score}
                    </h3>
                `;

                document.getElementById('qol-details-content').innerHTML = html;

                document.getElementById('qol-details-modal').classList.remove('hidden');
            })
            .catch(err => console.error("Fout bij QoL details:", err));
    }

    window.closeQolModal = function() {
        document.getElementById('qol-details-modal').classList.add('hidden');
    }

    const closeBtn = document.getElementById('qol-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            closeQolModal();
        });
    }

    updateQoL();
});
