import { getNeighborsWithQoL } from './neighbours.js';

document.addEventListener("DOMContentLoaded", () => {

    function activateCell(cell) {
        document.querySelectorAll(".grid-cell").forEach(c => c.classList.remove("selected"));
        cell.classList.add("selected");
    }

    let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;
    let dropOccurred = false;
    let old_score;

    // Stores the last action so undo knows what state to roll back
    let lastAction = null;

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;

    const popup = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    // --- EVENT LISTENER FOR APPROVE & LOCK BUTTON WITH HIGHLIGHT FIX ---
    const approveBtn = document.getElementById("approve-btn");
    if (approveBtn) {
        approveBtn.addEventListener("click", async () => {
            const selectedCell = document.querySelector(".grid-cell.selected");
            if (!selectedCell) {
                alert("Please select a grid cell first to lock or unlock it.");
                return;
            }

            try {
                const response = await fetch('/grid/approve', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ row: selectedCell.dataset.row, col: selectedCell.dataset.col })
                });

                const data = await response.json();

                if (data.success) {
                    if (data.is_approved) {
                        // Lock the cell visually
                        selectedCell.classList.add("locked-cell", "border-4", "border-red-500", "opacity-80");
                        selectedCell.setAttribute("draggable", "false");
                        
                        // Hide the delete button if it exists
                        const deleteBtn = selectedCell.querySelector(".delete-btn");
                        if (deleteBtn) deleteBtn.classList.add("hidden");
                        
                        // FIX: Heractiveer highlight-status
                        activateCell(selectedCell);
                        alert("Cell locked successfully!"); 
                    } else {
                        // Unlock the cell visually
                        selectedCell.classList.remove("locked-cell", "border-4", "border-red-500", "opacity-80");
                        if (selectedCell.querySelector("img")) {
                            selectedCell.setAttribute("draggable", "true");
                        }
                        
                        // Show the delete button again
                        const deleteBtn = selectedCell.querySelector(".delete-btn");
                        if (deleteBtn) deleteBtn.classList.remove("hidden");
                        
                        // FIX: Heractiveer highlight-status
                        activateCell(selectedCell);
                        alert("Cell unlocked successfully!"); 
                    }
                } else {
                    console.error("Failed to toggle lock status:", data.error);
                }
            } catch (err) {
                console.error("Error during cell approval:", err);
            }
        });
    }

    document.addEventListener("click", async (e) => {
        if (!e.target.classList.contains("delete-btn")) return;

        const cell = e.target.closest(".grid-cell");
        if (!cell) return;

        // SECURITY: Check if cell is approved/locked
        if (cell.classList.contains("locked-cell")) {
            alert("This cell is approved and locked. Action denied.");
            return;
        }

        cell.innerHTML = "";
        cell.removeAttribute("draggable");

        activateCell(cell);

        try {
            await fetch(`/grid/cell/${cell.dataset.id}/function`, {
                method: "DELETE",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                }
            });
        } catch (err) {
            console.error("Error removing function:", err);
        }

        setTimeout(() => updateQoL(), 10);
    });

    const cells = document.querySelectorAll(".grid-cell");
    const items = document.querySelectorAll(".library-item");

    async function saveMove(oldRow, oldCol, newRow, newCol, force = false) {
        try {
            const res = await fetch('/grid/update', {
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

            return res;
        } catch (err) {
            console.error("Error saving grid cell:", err);
            return null;
        }
    }

    async function updateQoL() {
        try {
            const scoreEl = document.getElementById('qol-score-value');
            const breakdownEl = document.getElementById('breakdown-qol-score');
            const oldScoreEl = document.getElementById('old-qol-score');

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
            console.error("Error fetching QoL data:", err);
        }
    }

    function compareScores(data) {
        let html = '';

        if (old_score === undefined) {
            html+=''; 
        }
        else {
            const delta_score = data.total_score - old_score;
            html += `
                <span class="text-xl float-right ${delta_score < 0 ? 'text-red-600' : 'text-green-600'}">
                    ${delta_score >= 0 ? '+' : ''}${delta_score}
                </span>
            `;
        }
        if (data.total_score !== 0) old_score = data.total_score;
        return html;
    }

    function renderQoLBreakdown(data) {
        let html = '';
        html += '<h3 class="text-xl font-semibold dark:text-teal-500">Breakdown QoL Score</h3>';

        for (const [category, info] of Object.entries(data.categories)) {
            const score = Number(info.total);
            let scoreClass = 'text-slate-400';
            if (score > 0) scoreClass = 'text-green-600';
            else if (score < 0) scoreClass = 'text-red-600';

            let scoreSign = score > 0 ? '+' : '';
            html += `
            <h3 class="font-semibold mt-3 dark:text-teal-600">
                ${category}: <span class="${scoreClass}">${scoreSign}${score}</span>
            </h3>`;
        }

        const totalScore = Number(data.total_score);
        let totalClass = 'text-slate-400';
        if (totalScore > 0) totalClass = 'text-green-600';
        else if (totalScore < 0) totalClass = 'text-red-600';

        let totalSign = totalScore > 0 ? '+' : '';
        html += `
        <h3 class="font-bold mt-4 dark:text-teal-600">
            Total QoL: <span class="${totalClass}">${totalSign}${totalScore}</span>
        </h3>`;

        return html;
    }

    async function handleTileHover(row, col, event) {
        positionPopup(event.pageX, event.pageY);
        const data = await getNeighborsWithQoL(row, col);
        renderNeighborsList(data);
        showPopup();
    }

    function positionPopup(x, y) {
        const offset = 15;
        popup.style.left = `${x + offset}px`;
        popup.style.top = `${y + offset}px`;
    }

    function renderNeighborsList(data) {
        neighborsList.innerHTML = '';
        if (!data.categories || Object.keys(data.categories).length === 0) {
            neighborsList.innerHTML = '<li class="text-slate-400 text-sm">No active QoL influences on this cell</li>';
            return;
        }

        let html = '';
        for (const [categoryName, info] of Object.entries(data.categories)) {
            const totalScore = Number(info.total);
            let catClass = 'text-slate-400';
            if (totalScore > 0) catClass = 'text-green-600';
            else if (totalScore < 0) catClass = 'text-red-600';

            let catSign = totalScore > 0 ? '+' : '';
            html += `
                <div class="mb-2 last:mb-0 w-full">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-slate-200 font-medium text-sm">${categoryName}</span>
                        <span class="${catClass} font-bold text-sm">${catSign}${totalScore}</span>
                    </div>
                </div>`;
        }

        const finalTotal = Number(data.total_score);
        let totalClass = 'text-slate-400';
        if (finalTotal > 0) totalClass = 'text-green-600';
        else if (finalTotal < 0) totalClass = 'text-red-600';

        let totalSign = finalTotal > 0 ? '+' : '';
        html += `
            <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-600/50 w-full">
                <span class="text-slate-300 font-bold text-xs uppercase tracking-wider">Total QoL:</span>
                <span class="${totalClass} font-extrabold text-base">${totalSign}${finalTotal}</span>
            </div>`;

        neighborsList.innerHTML = html;
    }

    function showPopup() {
        popup.classList.remove('hidden');
        void popup.offsetWidth;
        popup.classList.remove('opacity-0', 'scale-95');
        popup.classList.add('opacity-100', 'scale-100');
    }

    function hidePopup() {
        popup.classList.add('opacity-0', 'scale-95');
        popup.classList.remove('opacity-100', 'scale-100');
        setTimeout(() => { popup.classList.add('hidden'); }, 150);
    }

    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true;
            dropOccurred = false;
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
            // SECURITY: Prevent drag if cell is approved/locked
            if (cell.classList.contains("locked-cell")) {
                e.preventDefault();
                return;
            }

            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;

            isDragging = true;
            dropOccurred = false;
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
            // Prevent dropping onto locked cells
            if (cell.classList.contains("locked-cell")) return;
            e.preventDefault(); 
            cell.classList.add("drag-over"); 
        });
        
        cell.addEventListener("dragleave", () => { cell.classList.remove("drag-over"); });

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            
            // SECURITY: Guard clause for locked targets
            if (cell.classList.contains("locked-cell")) {
                cell.classList.remove("drag-over");
                return;
            }

            isDragging = false;
            dropOccurred = true;
            cell.classList.remove("drag-over");

            // Verify if the target cell already has an image (is occupied)
            const isOccupied = cell.querySelector("img") !== null;

            if (isOccupied) {
                const wantsToReplace = window.confirm("Are you sure you want to replace this feature?");
                
                if (!wantsToReplace) {
                    if (sourceCell) {
                        sourceCell.classList.remove("drag-source");
                    }
                    return; 
                }
            }

            const newRow = cell.dataset.row;
            const newCol = cell.dataset.col;
            let oldRow = null;
            let oldCol = null;

            const originalSourceCell = sourceCell; 

            if (sourceCell) {
                oldRow = sourceCell.dataset.row;
                oldCol = sourceCell.dataset.col;
                sourceCell.innerHTML = "";
                sourceCell.removeAttribute("draggable");
                sourceCell.classList.remove("drag-source");
            }

            sourceCell = null;
            cell.innerHTML = "";

            const img = document.createElement("img");
            img.src = draggedItem.image;
            img.alt = draggedItem.name;
            img.dataset.functionId = draggedItem.id;
            img.classList.add("grid-function-icon", "object-contain");
            cell.appendChild(img);

            const deleteBtn = document.createElement("button");
            deleteBtn.type = "button";
            deleteBtn.className =
                "delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center";
            const deleteText = `Remove ${draggedItem.name} from grid cell`;
            deleteBtn.setAttribute("aria-label", deleteText);
            deleteBtn.setAttribute("title", deleteText);
            const srText = document.createElement("span");
            srText.className = "sr-only";
            srText.textContent = deleteText;
            deleteBtn.appendChild(srText);
            deleteBtn.append("✖");
            cell.appendChild(deleteBtn);

            cell.setAttribute("draggable", "true");
            activateCell(cell);

            lastAction = {
                oldRow: oldRow,
                oldCol: oldCol,
                newRow: newRow,
                newCol: newCol,
                functionId: draggedItem.id
            };

            const undoBtnEl = document.getElementById("undo-btn");
            if (undoBtnEl) undoBtnEl.disabled = false;

            const res = await saveMove(oldRow, oldCol, newRow, newCol, false);

            if (res && res.status === 409) {
                const forceChoice = window.confirm("Placement is forbidden by adjacency rules. Force placement anyway?");

                if (forceChoice) {
                    const res2 = await saveMove(oldRow, oldCol, newRow, newCol, true);
                    if (!res2 || !res2.ok) {
                        if (originalSourceCell) {
                            originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                            originalSourceCell.setAttribute('draggable', 'true');
                        } else {
                            cell.innerHTML = "";
                            cell.removeAttribute('draggable');
                        }
                        updateQoL();
                        return;
                    }
                } else {
                    if (originalSourceCell) {
                        originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                        originalSourceCell.setAttribute('draggable', 'true');
                    } else {
                        cell.innerHTML = "";
                        cell.removeAttribute('draggable');
                    }
                    updateQoL();
                    return;
                }
            }

            updateQoL();
        });

        cell.addEventListener("click", () => { if (!isDragging) activateCell(cell); });
        cell.addEventListener("keydown", e => { if (!isDragging && (e.key === "Enter" || e.key === " ")) activateCell(cell); });

        cell.addEventListener('mouseenter', (event) => {
            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.col);
            hoverTimer = setTimeout(() => { handleTileHover(row, col, event); }, HOVER_DELAY_MS);
        });

        cell.addEventListener('mouseleave', () => { clearTimeout(hoverTimer); hidePopup(); });
    });

    const undoBtn = document.getElementById("undo-btn");

    if (undoBtn) {
        undoBtn.addEventListener("click", async () => {
            if (!lastAction) return;

            await fetch('/grid/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    old_row: lastAction.newRow,
                    old_col: lastAction.newCol,
                    new_row: lastAction.oldRow,
                    new_col: lastAction.oldCol,
                    function_id: lastAction.functionId,
                    force: true
                })
            });

            lastAction = null;
            undoBtn.disabled = true;
            updateQoL();
            location.reload();
        });
    }

    document.addEventListener("dragend", async (e) => {
        if (!draggedItem || !sourceCell) return;
        if (dropOccurred) { dropOccurred = false; return; }

        // SECURITY: If source became locked somehow, prevent removal
        if (sourceCell.classList.contains("locked-cell")) return;

        const grid = document.querySelector(".city-grid");
        const rect = grid.getBoundingClientRect();
        const x = e.pageX; const y = e.pageY;

        const outside = x < rect.left || x > rect.right || y < rect.top || y > rect.bottom;
        if (!outside) return;

        sourceCell.innerHTML = "";
        sourceCell.removeAttribute("draggable");
        activateCell(sourceCell);

        try {
            await fetch(`/grid/cell/${sourceCell.dataset.id}/function`, {
                method: "DELETE",
                headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
            });
        } catch (err) { console.error("Error during drag-off delete:", err); }

        draggedItem = null; sourceCell = null;
        setTimeout(() => updateQoL(), 10);
    });

    updateQoL();

    const undoButtonAlternative = document.getElementById('undoButton');
    if (undoButtonAlternative) {
        undoButtonAlternative.addEventListener('click', () => {
            fetch('/undo', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                console.log("UNDO RESPONSE:", data);
                if (!data.success) return;

                const targetCell = document.querySelector(
                    `[data-row="${data.cell.row}"][data-col="${data.cell.col}"]`
                );

                if (targetCell) {
                    if (data.cell.function_id) {
                        targetCell.innerHTML = `
                            <img src="${data.cell.image}" 
                                 class="grid-function-icon object-contain"
                                 data-function-id="${data.cell.function_id}">
                            <button 
                                class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">
                                ✖
                            </button>
                        `;
                        targetCell.setAttribute("draggable", "true");
                    } else {
                        targetCell.innerHTML = "";
                        targetCell.removeAttribute("draggable");
                    }
                    activateCell(targetCell);
                }

                if (data.cleared) {
                    const clearedCell = document.querySelector(
                        `[data-row="${data.cleared.row}"][data-col="${data.cleared.col}"]`
                    );

                    if (clearedCell) {
                        clearedCell.innerHTML = "";
                        clearedCell.removeAttribute("draggable");
                        clearedCell.classList.remove("selected"); 
                    }
                }

                document.querySelectorAll('.grid-cell').forEach(c => {
                    const cRow = Number(c.dataset.row);
                    const cCol = Number(c.dataset.col);

                    if (cRow === Number(data.cell.row) && cCol === Number(data.cell.col)) return;

                    if (data.cleared && cRow === Number(data.cleared.row) && cCol === Number(data.cleared.col)) {
                        c.innerHTML = "";
                        c.removeAttribute("draggable");
                    }
                });

                setTimeout(() => updateQoL(), 50);
            });
        });
    }
});