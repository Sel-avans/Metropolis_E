import { getNeighborsWithQoL } from './neighbours.js';
import '../css/grid.css';
let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;
    let dropOccurred = false;
    let old_score;
    let lastAction = null;


document.addEventListener("DOMContentLoaded", () => {

    function activateCell(cell) {
        // Toggle the selected class to allow multi-select
        cell.classList.toggle("selected");
    }

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;

    const popup = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    function flashLockExplanation(cell) {
        cell.classList.add('show-lock-explanation');
        window.setTimeout(() => cell.classList.remove('show-lock-explanation'), 2500);
    }

    function isCellOccupied(cell) {
        return cell.querySelector('.grid-function-icon') !== null;
    }

    function isLockedCell(cell) {
        return cell?.classList.contains('is-locked');
    }

    function canSelectLockedCell(cell) {
        return cell?.dataset.allowLockSelect === 'true';
    }

    function showLockedPlacementMessage(cell) {
        const message = isCellOccupied(cell)
            ? "You can't replace the function in this area"
            : "You can't add a function in this area";
        alert(message);
    }

    const LOCK_MESSAGE = 'This area is approved and cannot be changed.';

    function ensureLockUi(cell) {
        let lockIndicator = cell.querySelector('.lock-indicator');
        if (!lockIndicator) {
            lockIndicator = document.createElement('div');
            lockIndicator.className = 'lock-indicator absolute z-50 top-1 left-1 bg-red-600 text-white text-[10px] font-bold px-1 rounded flex items-center gap-0.5 shadow hidden';
            lockIndicator.setAttribute('aria-hidden', 'true');
            lockIndicator.innerHTML = '🔒 <span class="uppercase text-[9px]">Locked</span>';
            cell.prepend(lockIndicator);
        }

        let lockExplanation = cell.querySelector('.area-lock-explanation');
        if (!lockExplanation) {
            lockExplanation = document.createElement('div');
            lockExplanation.className = 'area-lock-explanation hidden';
            lockExplanation.setAttribute('role', 'tooltip');
            lockExplanation.setAttribute('aria-live', 'polite');
            lockExplanation.textContent = LOCK_MESSAGE;
            lockIndicator.insertAdjacentElement('afterend', lockExplanation);
        }

        return { lockIndicator, lockExplanation };
    }

    function clearCellFunction(cell) {
        cell.querySelectorAll('.grid-function-icon, .delete-btn').forEach((element) => element.remove());
    }

    function appendCellFunction(cell, functionData) {
        const img = document.createElement('img');
        img.src = functionData.image;
        img.alt = functionData.name;
        img.className = 'grid-function-icon object-contain w-full h-full p-4 relative z-0';
        img.dataset.functionId = functionData.id;

        if (isLockedCell(cell)) {
            img.setAttribute('draggable', 'false');
            img.setAttribute('ondragstart', 'return false;');
            img.classList.add('pointer-events-none', 'select-none');
        } else {
            img.setAttribute('draggable', 'true');
            cell.setAttribute('draggable', 'true');

            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete-btn absolute z-10 top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center';
            deleteBtn.innerHTML = '✖';
            cell.appendChild(deleteBtn);
        }

        cell.appendChild(img);
    }

    function applyLockedCellState(cell) {
        ensureLockUi(cell);

        cell.classList.add('is-locked', 'bg-stripes', 'opacity-60', 'border-red-600');
        cell.classList.remove('bg-gray-300', 'border-gray-800', 'dark:bg-blue-950', 'dark:border-gray-300', 'hover:bg-gray-400', 'hover:dark:bg-gray-100', 'cursor-pointer');
        cell.classList.add(document.getElementById('approve-btn') ? 'cursor-pointer' : 'cursor-not-allowed');
        cell.dataset.allowLockSelect = document.getElementById('approve-btn') ? 'true' : 'false';
        cell.setAttribute('draggable', 'false');
        cell.setAttribute('aria-label', `Approved area row ${cell.dataset.row}, column ${cell.dataset.col}`);

        const img = cell.querySelector('.grid-function-icon');
        if (img) {
            img.setAttribute('draggable', 'false');
            img.setAttribute('ondragstart', 'return false;');
            img.classList.add('pointer-events-none', 'select-none');
        }

        const { lockIndicator, lockExplanation } = ensureLockUi(cell);
        lockIndicator.classList.remove('hidden');
        lockIndicator.setAttribute('aria-hidden', 'false');
        lockExplanation.classList.remove('hidden');

        const deleteBtn = cell.querySelector('.delete-btn');
        if (deleteBtn) deleteBtn.remove();
    }

    function applyUnlockedCellState(cell) {
        cell.classList.remove('is-locked', 'bg-stripes', 'opacity-60', 'border-red-600', 'cursor-not-allowed');
        cell.classList.add('bg-gray-300', 'border-gray-800', 'dark:bg-blue-950', 'dark:border-gray-300', 'hover:bg-gray-400', 'hover:dark:bg-gray-100', 'cursor-pointer');
        cell.dataset.allowLockSelect = 'false';
        cell.setAttribute('draggable', 'true');
        cell.removeAttribute('aria-label');
        cell.classList.remove('show-lock-explanation');

        const img = cell.querySelector('.grid-function-icon');
        if (img) {
            img.setAttribute('draggable', 'true');
            img.removeAttribute('ondragstart');
            img.classList.remove('pointer-events-none', 'select-none');
        }

        const { lockIndicator, lockExplanation } = ensureLockUi(cell);
        lockIndicator.classList.add('hidden');
        lockIndicator.setAttribute('aria-hidden', 'true');
        lockExplanation.classList.add('hidden');

        if (img && !cell.querySelector('.delete-btn')) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'delete-btn absolute z-10 top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center';
            deleteBtn.innerHTML = '✖';
            cell.appendChild(deleteBtn);
        }
    }

// --- APPROVE / LOCK LOGIC ---
const approveBtn = document.getElementById("approve-btn");
if (approveBtn) {
    approveBtn.addEventListener("click", async () => {
        console.log("Button clicked!");
        
        // Find all currently selected cells
        const selectedCells = document.querySelectorAll(".grid-cell.selected");
        if (selectedCells.length === 0) {
            alert("Please select at least one grid cell first to lock or unlock it.");
            return;
        }

        // Map selected cells to an array of row/col objects
        const cellsPayload = Array.from(selectedCells).map(cell => ({
            row: cell.dataset.row,
            col: cell.dataset.col
        }));

        try {
            const response = await fetch('/grid/approve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ cells: cellsPayload })
            });

            const data = await response.json();
            console.log("Antwoord van server:", data);

            if (data.success) {
                data.updated_cells.forEach(updatedData => {
                    const cell = document.querySelector(`.grid-cell[data-row="${updatedData.row}"][data-col="${updatedData.col}"]`);
                    if (!cell) return;

                    if (updatedData.is_approved) {
                        applyLockedCellState(cell);
                        flashLockExplanation(cell);
                    } else {
                        applyUnlockedCellState(cell);
                    }
                });
                
                // Clear the selections
                document.querySelectorAll(".grid-cell.selected").forEach(c => c.classList.remove("selected"));
                alert("Selected areas successfully updated!"); 
            }
        } catch (err) {
            console.error("Error during cell approval:", err);
        }
    });
    }

    // --- DELETE LOGIC ---
    document.addEventListener("click", async (e) => {
        if (!e.target.classList.contains("delete-btn")) return;

        const cell = e.target.closest(".grid-cell");
        if (!cell) return;

        if (isLockedCell(cell)) {
            return;
        }

        clearCellFunction(cell);
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

    cells.forEach((cell) => {
        ensureLockUi(cell);
        if (isLockedCell(cell)) {
            applyLockedCellState(cell);
        }
    });

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
        if (old_score !== undefined) {
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
        let html = '<h3 class="text-xl font-semibold dark:text-teal-500">Breakdown QoL Score</h3>';
        for (const [category, info] of Object.entries(data.categories)) {
            const score = Number(info.total);
            let scoreClass = score > 0 ? 'text-green-600' : (score < 0 ? 'text-red-600' : 'text-slate-400');
            let scoreSign = score > 0 ? '+' : '';
            html += `<h3 class="font-semibold mt-3 dark:text-teal-600">${category}: <span class="${scoreClass}">${scoreSign}${score}</span></h3>`;
        }
        const totalScore = Number(data.total_score);
        let totalClass = totalScore > 0 ? 'text-green-600' : (totalScore < 0 ? 'text-red-600' : 'text-slate-400');
        html += `<h3 class="font-bold mt-4 dark:text-teal-600">Total QoL: <span class="${totalClass}">${totalScore > 0 ? '+' : ''}${totalScore}</span></h3>`;
        return html;
    }

    async function handleTileHover(row, col, event) {
        const cell = document.querySelector(`.grid-cell[data-row="${row}"][data-col="${col}"]`);
        positionPopup(event.pageX, event.pageY, cell);
        const data = await getNeighborsWithQoL(row, col);
        renderNeighborsList(data);
        showPopup();
    }

    function positionPopup(x, y, cell) {
        if (cell && isLockedCell(cell)) {
            const rect = cell.getBoundingClientRect();
            popup.style.left = `${rect.right + window.scrollX + 12}px`;
            popup.style.top = `${rect.top + window.scrollY + 8}px`;
            return;
        }

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
            let catClass = totalScore > 0 ? 'text-green-600' : (totalScore < 0 ? 'text-red-600' : 'text-slate-400');
            html += `
                <div class="mb-2 w-full">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-slate-200 font-medium text-sm">${categoryName}</span>
                        <span class="${catClass} font-bold text-sm">${totalScore > 0 ? '+' : ''}${totalScore}</span>
                    </div>
                </div>`;
        }
        const finalTotal = Number(data.total_score);
        html += `
            <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-600/50 w-full">
                <span class="text-slate-300 font-bold text-xs uppercase">Total QoL:</span>
                <span class="${finalTotal > 0 ? 'text-green-600' : (finalTotal < 0 ? 'text-red-600' : 'text-slate-400')} font-extrabold text-base">${finalTotal > 0 ? '+' : ''}${finalTotal}</span>
            </div>`;
        neighborsList.innerHTML = html;
    }

    function showPopup() {
        popup.classList.remove('hidden');
        popup.classList.replace('opacity-0', 'opacity-100');
        popup.classList.replace('scale-95', 'scale-100');
    }

    function hidePopup() {
        popup.classList.replace('opacity-100', 'opacity-0');
        popup.classList.replace('scale-100', 'scale-95');
        setTimeout(() => { popup.classList.add('hidden'); }, 150);
    }

    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true;
            dropOccurred = false;
            draggedItem = { id: Number(item.dataset.functionId), name: item.dataset.functionName, image: item.dataset.image };
            e.dataTransfer.setDragImage(item.querySelector("img"), 16, 16);
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragstart", e => {
            if (isLockedCell(cell)) {
                e.preventDefault();
                return;
            }
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;
            isDragging = true;
            dropOccurred = false;
            draggedItem = { id: Number(img.dataset.functionId), name: img.alt, image: img.src };
            sourceCell = cell;
            cell.classList.add("drag-source");
        });

        cell.addEventListener("dragover", e => {
            e.preventDefault();
            if (isLockedCell(cell)) {
                return;
            }
            cell.classList.add("drag-over");
        });
        cell.addEventListener("dragleave", () => cell.classList.remove("drag-over"));

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            cell.classList.remove("drag-over");

            if (isLockedCell(cell)) {
                isDragging = false;
                dropOccurred = true;
                showLockedPlacementMessage(cell);
                if (sourceCell) sourceCell.classList.remove("drag-source");
                return;
            }
            
            isDragging = false; dropOccurred = true;
            
            if (cell.querySelector("img") && !window.confirm("Replace this feature?")) {
                if (sourceCell) sourceCell.classList.remove("drag-source");
                return;
            }

            const oldRow = sourceCell?.dataset.row;
            const oldCol = sourceCell?.dataset.col;
            if (sourceCell) {
                clearCellFunction(sourceCell);
                sourceCell.classList.remove("drag-source");
            }

            clearCellFunction(cell);
            appendCellFunction(cell, draggedItem);

            const res = await saveMove(oldRow, oldCol, cell.dataset.row, cell.dataset.col, false);
            if (res && res.status === 403) {
                const data = await res.json();
                alert(data.message ?? "You can't modify this locked area.");
                location.reload();
                return;
            }
            if (res && res.status === 409 && window.confirm("Placement forbidden. Force placement?")) {
                await saveMove(oldRow, oldCol, cell.dataset.row, cell.dataset.col, true);
            }
            updateQoL();
        });

        cell.addEventListener("click", (e) => {
            if (isDragging) return;
            if (isLockedCell(cell) && !canSelectLockedCell(cell)) {
                e.preventDefault();
                flashLockExplanation(cell);
                return;
            }
            activateCell(cell);
        });
        cell.addEventListener('mouseenter', (event) => { hoverTimer = setTimeout(() => handleTileHover(parseInt(cell.dataset.row), parseInt(cell.dataset.col), event), HOVER_DELAY_MS); });
        cell.addEventListener('mouseleave', () => { clearTimeout(hoverTimer); hidePopup(); });
    });

    const undoBtn = document.getElementById("undo-btn");
    if (undoBtn) {
        undoBtn.addEventListener("click", async () => {
            if (lastAction) {
                await fetch('/grid/undo', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                location.reload();
            }
        });
    }

    document.addEventListener("dragend", () => { isDragging = false; if (sourceCell) sourceCell.classList.remove("drag-source"); });

    updateQoL();
    document.addEventListener("DOMContentLoaded", () => {
    const undoBtn = document.getElementById("undo-btn");

    if (undoBtn) {
        console.log("Undo knop gevonden!"); // Test 1: Verschijnt dit in je console?
        
        undoBtn.addEventListener("click", () => {
            console.log("Undo knop is aangeklikt!"); // Test 2: Verschijnt dit na klikken?
            
            // Simpele test: reload de pagina
            // location.reload(); 
        });
    } else {
        console.error("Undo knop NIET gevonden! Check je HTML ID."); // Test 3: Foutmelding
    }
    // TEST CODE: Plak dit helemaal onderaan je bestand
window.addEventListener('load', () => {
    const btn = document.getElementById('undo-btn');
    if (btn) {
        btn.style.border = "5px solid red"; // Krijgt de knop een rode rand?
        btn.addEventListener('click', () => {
            alert('De knop werkt en de code bereikt hem!');
        });
    } else {
        console.error("DEBUG: Undo knop is niet gevonden door het systeem.");
    }
});
});
});