import { getNeighborsWithQoL } from './neighbours.js';

document.addEventListener("DOMContentLoaded", () => {
    const cells = document.querySelectorAll(".grid-cell");
    const items = document.querySelectorAll(".library-item");

    let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;
    let old_score;

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;

    const popup = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    // This is the QoL effects table. hardcoded for now. Should be changed to follow actual scores.
    const effectsTable = {
        'Police Station': 3,
        'Fire Station': 2,
        'Park': 4,
        'Cinema': 2,
        'Sports Park': 3,
        'Water Treatment': 1,
        'School': 2,
        'Store': 1,
        'Hospital': 3,
        'Train Station': 2,
        'Road': -1,
        'Bicycle Path': 2,
        'Gas Station': -2
    };

    async function saveMove(oldRow, oldCol, newRow, newCol, force = false) {
        try {
            const response = await fetch('/grid/update', {
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
                    function_id: draggedItem.id,
                    force: force
                })
            });

        const data = await response.json();
        
        if (response.status === 409) {
            const userWantsToContinue = window.confirm(data.message);
            
            if (userWantsToContinue) {
                return saveMove(oldRow, oldCol, newRow, newCol, true);
            } else {
                location.reload(); 
                return;
            }
        }

        if (!response.ok) {
            alert(data.message || "Er is een fout opgetreden!"); 
            location.reload(); 
            return;
        }

        updateQoL();
    } catch (err) {
        console.error("Fout:", err);
        }
    }

    async function updateQoL() {
        try {
            const scoreEl = document.getElementById('qol-score-value');
            const breakdownEl = document.getElementById('breakdown-qol-score');
            const oldScoreEl = document.getElementById('old-qol-score');

            if (!scoreEl && !breakdownEl) return;

            const response = await fetch('/qol/details');
            const data = await response.json();

            if (scoreEl) {
                scoreEl.textContent = data.total_score;
                oldScoreEl.innerHTML = compareScores(data);
            }

            if (breakdownEl) {
                breakdownEl.innerHTML = renderQoLBreakdown(data);
            }

        } catch (err) {
            console.error("Fout bij ophalen QoL:", err);
        }
    }

    function compareScores(data) {
        let html ='';

        if (old_score === undefined) {
            html+='';
        }
        else {
            const delta_score = data.total_score - old_score;
            if (delta_score >= 0) {
                html += ` 
                <span class="text-xl float-right ${delta_score < 0 ? 'text-red-600' : 'text-green-600'}">
                    +${delta_score}
                </span>
                `;
            }
            else{
                html += ` 
                <span class="text-xl ${delta_score < 0 ? 'text-red-600' : 'text-green-600'}">
                    ${delta_score}
                </span>
                `;
            }
        }

        if (data.total_score !== 0) {
            old_score = data.total_score;
        }

        return html;
    }

    function renderQoLBreakdown(data) {
        let html = '';

        html += '<h3 class="dark:text-teal-500">Breakdown QoL Score</h3>';
        for (const [category, info] of Object.entries(data.categories)) {
            html += `
                <h3 class="font-semibold mt-3 dark:text-teal-600">
                    ${category} (total:
                    <span class="${info.total <= 0 ? 'text-red-600' : 'text-green-600'}">
                        ${info.total}
                    </span>
                    )
                </h3>
            `;

            // Shows what item function contributes to the QoL score and by how much.
            
            // info.items.forEach(item => {
            //     html += `
            //         <div class="flex justify-between text-gray-700 dark:text-white">
            //             <span>${item.function}</span>
            //             <span class="${item.value <= 0 ? 'text-red-600' : 'text-green-600'}">
            //                 ${item.value}
            //             </span>
            //         </div>
            //     `;
            // });
        }

        // Should we keep this?
        html += `
            <h3 class="font-bold mt-4 dark:text-teal-600">
                Total QoL: 
                <span class="${data.total_score <= 0 ? 'text-red-600' : 'text-green-600'}">${data.total_score}</span>
            </h3>
        `;

        return html;
    }

    function handleTileHover(row, col, event) {
        positionPopup(event.pageX, event.pageY);
        const neighbors = getNeighborsWithQoL(row, col, effectsTable);
        renderNeighborsList(neighbors);
        showPopup();
    }

    function positionPopup(x, y) {
        const offset = 15;
        popup.style.left = `${x + offset}px`;
        popup.style.top = `${y + offset}px`;
    }

    function renderNeighborsList(neighbors) {
        // Clear any previous list items
        neighborsList.innerHTML = '';

        if (!neighbors || neighbors.length === 0) {
            neighborsList.innerHTML = '<li class="text-slate-400">No neighbors with buildings</li>';
            return;
        }

        neighbors.forEach(neighbor => {
            const li = document.createElement('li');
            li.className = 'flex justify-between items-center gap-4 py-0.5';

            const directionName = neighbor.direction.charAt(0).toUpperCase() + neighbor.direction.slice(1);

            // Determine the text color and sign (+/-) based on the QoL score
            let scoreClass = 'text-slate-400'; // Neutral
            let scoreText = `${neighbor.qol_effect}`;

            if (neighbor.qol_effect > 0) {
                scoreClass = 'text-green-400 font-semibold';
                scoreText = `+${neighbor.qol_effect}`;
            } else if (neighbor.qol_effect < 0) {
                scoreClass = 'text-red-400 font-semibold';
            }

            // Fill the list item HTML
            li.innerHTML = `
                <span class="text-slate-300 font-medium">${directionName}: ${neighbor.function}</span>
                <span class="${scoreClass}">${scoreText}</span>
            `;

            neighborsList.appendChild(li);
        });
    }

    function showPopup() {
        popup.classList.remove('hidden');
        
        // Force browser reflow to ensure the transition plays smoothly
        void popup.offsetWidth;
        
        popup.classList.remove('opacity-0', 'scale-95');
        popup.classList.add('opacity-100', 'scale-100');
    }

    function hidePopup() {
        popup.classList.add('opacity-0', 'scale-95');
        popup.classList.remove('opacity-100', 'scale-100');

        // Hide display after the fade-out transition complete (150ms)
        setTimeout(() => {
            popup.classList.add('hidden');
        }, 150);
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

        cell.addEventListener('mouseenter', (event) => {
            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.col);

            // Start the 300ms delay timer
            hoverTimer = setTimeout(() => {
                handleTileHover(row, col, event);
            }, HOVER_DELAY_MS);
        });

        cell.addEventListener('mouseleave', () => {
            // Cancel the timer immediately if the mouse leaves before 300ms
            clearTimeout(hoverTimer);
            hidePopup();
        });
    });

    updateQoL();
});