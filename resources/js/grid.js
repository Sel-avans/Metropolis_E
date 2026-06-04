import { getNeighborsWithQoL } from './neighbours.js';

let draggedItem = null;
let isDragging = false;
let sourceCell = null;
let dropOccurred = false;
let old_score;
let lastAction = null;

function notify(message) {
    alert(message); 
}

document.addEventListener("DOMContentLoaded", () => {

    function activateCell(cell) {
        document.querySelectorAll(".grid-cell").forEach(c => c.classList.remove("selected"));
        cell.classList.add("selected");
    }

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;
    const popup = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    // --- APPROVE / LOCK LOGIC ---
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
                    const lockIndicator = selectedCell.querySelector('.lock-indicator');
                    const deleteBtn = selectedCell.querySelector(".delete-btn");
                    
                    if (data.is_approved) {
                        selectedCell.classList.add("is-locked", "bg-stripes", "opacity-60", "border-red-600");
                        selectedCell.classList.remove("bg-gray-300", "border-gray-800", "dark:bg-blue-950", "dark:border-gray-300", "hover:bg-gray-400", "hover:dark:bg-gray-100");
                        selectedCell.setAttribute("draggable", "false");
                        
                        if (lockIndicator) lockIndicator.classList.remove('hidden');
                        if (deleteBtn) deleteBtn.classList.add("hidden");
                        
                        activateCell(selectedCell);
                        alert("Cell successfully approved and locked!"); 
                    } else {
                        selectedCell.classList.remove("is-locked", "bg-stripes", "opacity-60", "border-red-600");
                        selectedCell.classList.add("bg-gray-300", "border-gray-800", "dark:bg-blue-950", "dark:border-gray-300", "hover:bg-gray-400", "hover:dark:bg-gray-100");
                        
                        if (selectedCell.querySelector("img")) selectedCell.setAttribute("draggable", "true");
                        if (lockIndicator) lockIndicator.classList.add('hidden');
                        if (deleteBtn) deleteBtn.classList.remove("hidden");
                        
                        activateCell(selectedCell);
                        alert("Cell successfully unlocked!"); 
                    }
                }
            } catch (err) {
                console.error("Error during cell approval:", err);
            }
        });
    }

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

            if (breakdownEl) breakdownEl.innerHTML = renderQoLBreakdown(data);
        } catch (err) {
            console.error("Error fetching QoL data:", err);
        }
    }

    function compareScores(data) {
        let html = '';
        if (old_score !== undefined) {
            const delta_score = data.total_score - old_score;
            html += `<span class="text-xl float-right ${delta_score < 0 ? 'text-red-600' : 'text-green-600'}">${delta_score >= 0 ? '+' : ''}${delta_score}</span>`;
        }
        if (data.total_score !== 0) old_score = data.total_score;
        return html;
    }

    function renderQoLBreakdown(data) {
        let html = '<h3 class="text-xl font-semibold dark:text-teal-500">Breakdown QoL Score</h3>';
        for (const [category, info] of Object.entries(data.categories)) {
            const score = Number(info.total);
            let scoreClass = score > 0 ? 'text-green-600' : (score < 0 ? 'text-red-600' : 'text-slate-400');
            html += `<h3 class="font-semibold mt-3 dark:text-teal-600">${category}: <span class="${scoreClass}">${score > 0 ? '+' : ''}${score}</span></h3>`;
        }
        return html;
    }

    // --- DRAG & DROP & CELL LISTENERS ---
    items.forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true;
            draggedItem = { id: Number(item.dataset.functionId), name: item.dataset.functionName, image: item.dataset.image };
            e.dataTransfer.setDragImage(item.querySelector("img"), 16, 16);
        });
    });

    cells.forEach(cell => {
        cell.addEventListener("dragstart", e => {
            if (cell.classList.contains("is-locked")) { e.preventDefault(); notify("Modification denied: This cell is locked."); return; }
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;
            isDragging = true;
            draggedItem = { id: Number(img.dataset.functionId), name: img.alt, image: img.src };
            sourceCell = cell;
            cell.classList.add("drag-source");
        });

        cell.addEventListener("dragover", e => { 
            if (!cell.classList.contains("is-locked")) { e.preventDefault(); cell.classList.add("drag-over"); } 
        });
        
        cell.addEventListener("dragleave", () => cell.classList.remove("drag-over"));

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            if (cell.classList.contains("is-locked")) { cell.classList.remove("drag-over"); return; }
            
            cell.classList.remove("drag-over");
            if (cell.querySelector("img") && !window.confirm("Replace this feature?")) return;

            const oldRow = sourceCell?.dataset.row;
            const oldCol = sourceCell?.dataset.col;
            if (sourceCell) { sourceCell.innerHTML = ""; sourceCell.classList.remove("drag-source"); }
            
            cell.innerHTML = "";
            const img = document.createElement("img");
            img.src = draggedItem.image;
            img.classList.add("grid-function-icon");
            cell.appendChild(img);
            
            const deleteBtn = document.createElement("button");
            deleteBtn.className = "delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 rounded";
            deleteBtn.innerHTML = "✖";
            cell.appendChild(deleteBtn);

            const res = await saveMove(oldRow, oldCol, cell.dataset.row, cell.dataset.col, false);
            if (res && res.status === 409 && window.confirm("Placement forbidden. Force placement?")) {
                await saveMove(oldRow, oldCol, cell.dataset.row, cell.dataset.col, true);
            }
            updateQoL();
        });

        cell.addEventListener("click", () => { if (!isDragging) activateCell(cell); });
    });

    // Cleanup
    document.addEventListener("dragend", () => { isDragging = false; if (sourceCell) sourceCell.classList.remove("drag-source"); });
    updateQoL();
});