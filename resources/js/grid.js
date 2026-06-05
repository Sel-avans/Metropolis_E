import { getNeighborsWithQoL } from './neighbours.js';
import { simulationLoop, onSimulationTimeUpdate } from './simulation.js';
import { setMaxTime, syncTimelineUI, minutesToHHMM, datetimeToSimMinutes } from './regulation.js';

document.addEventListener("DOMContentLoaded", () => {

    // =========================================================
    // VARIABELEN
    // =========================================================

    let draggedItem = null;
    let isDragging  = false;
    let sourceCell  = null;
    let dropOccurred = false;
    let old_score;
    let lastAction = null;

    // Alle events van de server — manuallyEnabled = door planner aan/uit gezet
    let allEvents = [];

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;
    let lastActiveEventSignature = '';

    const popup        = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    // =========================================================
    // HULPFUNCTIES
    // =========================================================

    function activateCell(cell) {
        document.querySelectorAll(".grid-cell").forEach(c => c.classList.remove("selected"));
        cell.classList.add("selected");
    }

    function formatModifiers(modifiers) {
        if (!modifiers || typeof modifiers !== 'object') return '';
        const items = Object.entries(modifiers).map(([category, value]) => {
            const num  = Number(value);
            const sign = num >= 0 ? '+' : '';
            const label = category.charAt(0).toUpperCase() + category.slice(1);
            return `<li class="text-amber-400">- ${label}: ${sign}${num}</li>`;
        });
        if (items.length === 0) return '';
        return `<ul class="text-[11px] text-gray-400 mt-1 space-y-0.5 list-none pl-0">${items.join('')}</ul>`;
    }

    function buildEventSignature(events) {
        return JSON.stringify(
            (events || []).map(e => ({ id: e.id, active: e.isActive ?? false }))
        );
    }

    function compareScores(data) {
        let html = '';
        if (old_score !== undefined) {
            const delta = data.total_score - old_score;
            html += `<span class="text-xl float-right ${delta < 0 ? 'text-red-600' : 'text-green-600'}">
                ${delta >= 0 ? '+' : ''}${delta}
            </span>`;
        }
        if (data.total_score !== 0) old_score = data.total_score;
        return html;
    }

    function renderQoLBreakdown(data) {
        let html = '<h3 class="text-xl font-semibold dark:text-teal-500 mb-2">Breakdown QoL Score</h3>';
        html += '<ul class="space-y-1 list-none pl-0 text-sm">';
        for (const [category, info] of Object.entries(data.categories)) {
            const score = Number(info.total);
            const cls   = score > 0 ? 'text-green-600' : score < 0 ? 'text-red-600' : 'text-slate-400';
            const sign  = score > 0 ? '+' : '';
            html += `<li class="font-semibold dark:text-teal-600">- ${category}: <span class="${cls}">${sign}${score}</span></li>`;
        }
        html += '</ul>';
        const total = Number(data.total_score);
        const tcls  = total > 0 ? 'text-green-600' : total < 0 ? 'text-red-600' : 'text-slate-400';
        const tsign = total > 0 ? '+' : '';
        html += `<p class="font-bold mt-4 dark:text-teal-600">Total QoL: <span class="${tcls}">${tsign}${total}</span></p>`;
        return html;
    }

    function positionPopup(x, y) {
        popup.style.left = `${x + 15}px`;
        popup.style.top  = `${y + 15}px`;
    }

    function renderNeighborsList(data) {
        neighborsList.innerHTML = '';
        if (!data.categories || Object.keys(data.categories).length === 0) {
            neighborsList.innerHTML = '<li class="text-slate-400 text-sm">No active QoL influences on this cell</li>';
            return;
        }
        const categoryKeys = {
            Safety: 'safety', Recreation: 'recreation', Environment: 'environment',
            Amenities: 'amenities', Mobility: 'mobility',
        };
        let html = '';
        const eventModifiers = data.event_modifiers || {};
        for (const [categoryName, info] of Object.entries(data.categories)) {
            const cellScore    = Number(info.total);
            const eventScore   = Number(eventModifiers[categoryKeys[categoryName]] || 0);
            const displayScore = cellScore + eventScore;
            const cls  = displayScore > 0 ? 'text-green-600' : displayScore < 0 ? 'text-red-600' : 'text-slate-400';
            const sign = displayScore > 0 ? '+' : '';
            html += `<div class="mb-2 last:mb-0 w-full">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-slate-200 font-medium text-sm">${categoryName}</span>
                    <span class="${cls} font-bold text-sm">${sign}${displayScore}</span>
                </div>
            </div>`;
        }
        const cellTotal = Number(data.total_score);
        const tcls  = cellTotal > 0 ? 'text-green-600' : cellTotal < 0 ? 'text-red-600' : 'text-slate-400';
        const tsign = cellTotal > 0 ? '+' : '';
        html += `<div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-600/50 w-full">
            <span class="text-slate-300 font-bold text-xs uppercase tracking-wider">Total QoL:</span>
            <span class="${tcls} font-extrabold text-base">${tsign}${cellTotal}</span>
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
        setTimeout(() => popup.classList.add('hidden'), 150);
    }

    async function handleTileHover(row, col, event) {
        positionPopup(event.pageX, event.pageY);
        const data = await getNeighborsWithQoL(row, col);
        renderNeighborsList(data);
        showPopup();
    }

    // =========================================================
    // QOL UPDATE
    // =========================================================

    async function updateQoL() {
        try {
            const scoreEl     = document.getElementById('qol-score-value');
            const breakdownEl = document.getElementById('breakdown-qol-score');
            const oldScoreEl  = document.getElementById('old-qol-score');
            const response    = await fetch('/qol/details');
            const data        = await response.json();
            if (scoreEl)     { scoreEl.textContent = data.total_score; oldScoreEl.innerHTML = compareScores(data); }
            if (breakdownEl) { breakdownEl.innerHTML = renderQoLBreakdown(data); }
        } catch (err) {
            console.error("Fout bij ophalen QoL:", err);
        }
    }

    // =========================================================
    // HIGHLIGHT LOGICA
    // Cellen die beïnvloed worden door een actief event krijgen
    // een gele rand. We bepalen welke cellen beïnvloed zijn op
    // basis van de categorieën van het event die matchen met
    // de functie-categorieën in de grid.
    // =========================================================

    function updateCellHighlights() {
        const activeEvents = allEvents.filter(e => e.isActive && e.manuallyEnabled);

        // Verzamel alle categorieën van actieve events.
        // Gebruik affected_categories van de server als die beschikbaar is,
        // anders fallback naar de keys van modifiers.
        const activeCategories = new Set();
        activeEvents.forEach(event => {
            if (Array.isArray(event.affected_categories) && event.affected_categories.length) {
                event.affected_categories.forEach(cat => activeCategories.add(cat.toLowerCase()));
            } else if (event.modifiers && typeof event.modifiers === 'object') {
                Object.keys(event.modifiers).forEach(cat => activeCategories.add(cat.toLowerCase()));
            }
        });

        document.querySelectorAll('.grid-cell').forEach(cell => {
            const functionImg = cell.querySelector('.grid-function-icon');
            if (!functionImg) {
                cell.classList.remove('event-highlight');
                return;
            }

            // Cel heeft een functie — check of die functie een categorie
            // heeft die overlapt met de actieve event modifiers.
            // data-categories wordt als kommalijst op het img-element gezet
            // vanuit de blade (bijv. data-categories="amenities,recreation").
            const cellCategories = (functionImg.dataset.categories || '').toLowerCase().split(',').filter(Boolean);
            const isAffected = cellCategories.some(cat => activeCategories.has(cat));

            cell.classList.toggle('event-highlight', isAffected);
        });
    }

    // =========================================================
    // SIMULATIE TICK — events aan/uit op basis van simulatietijd
    // =========================================================

    function onSimulationTick(simTime) {
        if (!allEvents.length) return;

        let changed = false;

        allEvents.forEach(event => {
            // Alleen als de planner het event handmatig heeft ingeschakeld
            if (!event.manuallyEnabled) {
                if (event.isActive) { event.isActive = false; changed = true; }
                return;
            }

            const inWindow     = simTime >= event.start_minutes && simTime <= event.end_minutes;
            const shouldBeActive = inWindow;

            if (event.isActive !== shouldBeActive) {
                event.isActive = shouldBeActive;
                changed = true;
            }
        });

        if (changed) {
            renderActiveEventsPanel();
            updateCellHighlights();
            updateQoL();
        }
    }

    onSimulationTimeUpdate(onSimulationTick);

    // =========================================================
    // EVENTS PANEL — rechts in de blade
    // Toont ALLE events met een Activate/Deactivate knop.
    // =========================================================

    function renderAllEventsPanel() {
        const listEl = document.getElementById('all-events-detail-list');
        if (!listEl) return;

        listEl.innerHTML = '';

        if (!allEvents.length) {
            listEl.innerHTML = '<li class="text-sm text-gray-500">No events available.</li>';
            return;
        }

        allEvents.forEach(event => {
            const li = document.createElement('li');
            li.className = "p-3 bg-slate-800 border border-slate-600 rounded mb-2 text-sm";
            li.dataset.eventId = event.id;

            const modifiersHtml = formatModifiers(event.modifiers);
            const startLabel    = minutesToHHMM(event.start_minutes);
            const endLabel      = minutesToHHMM(event.end_minutes);
            const isEnabled     = event.manuallyEnabled;

            li.innerHTML = `
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-slate-200 truncate">${event.name || 'Nameless Event'}</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">${startLabel} – ${endLabel}</div>
                        ${modifiersHtml}
                    </div>
                    <button
                        class="event-toggle-btn flex-shrink-0 px-2 py-1 text-xs font-semibold rounded transition
                            ${isEnabled
                                ? 'bg-amber-500 hover:bg-amber-600 text-black'
                                : 'bg-slate-600 hover:bg-teal-600 hover:text-white text-slate-200'}"
                        data-event-id="${event.id}">
                        ${isEnabled ? 'Deactivate' : 'Activate'}
                    </button>
                </div>`;

            listEl.appendChild(li);
        });
    }

    function renderActiveEventsPanel() {
        const listEl  = document.getElementById('active-events-list');
        const emptyEl = document.getElementById('active-events-empty');
        if (!listEl) return;

        const active = allEvents.filter(e => e.isActive && e.manuallyEnabled);

        if (active.length === 0) {
            if (emptyEl) emptyEl.classList.remove('hidden');
            listEl.innerHTML = '';
            return;
        }

        if (emptyEl) emptyEl.classList.add('hidden');
        listEl.innerHTML = '';

        active.forEach(event => {
            const li = document.createElement('li');
            li.className = "p-3 bg-slate-800 border-l-4 border-amber-500 rounded shadow-sm mb-2 text-sm";

            const modifiersHtml = formatModifiers(event.modifiers);
            const startLabel    = minutesToHHMM(event.start_minutes);
            const endLabel      = minutesToHHMM(event.end_minutes);

            li.innerHTML = `
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold text-slate-200">${event.name || 'Nameless Event'}</div>
                        <div class="text-[11px] text-gray-500 mt-0.5">${startLabel} – ${endLabel}</div>
                        ${modifiersHtml}
                    </div>
                    <button
                        class="event-toggle-btn flex-shrink-0 px-2 py-1 text-xs font-semibold rounded bg-amber-500 hover:bg-amber-600 text-black transition"
                        data-event-id="${event.id}">
                        Deactivate
                    </button>
                </div>`;

            listEl.appendChild(li);
        });
    }

    // Toggle handler via event delegation (werkt voor beide panels)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.event-toggle-btn');
        if (!btn) return;

        const eventId = parseInt(btn.dataset.eventId);
        const event   = allEvents.find(ev => ev.id === eventId);
        if (!event) return;

        event.manuallyEnabled = !event.manuallyEnabled;

        // Als deactivated: ook isActive uitzetten
        if (!event.manuallyEnabled) event.isActive = false;

        // Herrender beide panels + highlights
        renderAllEventsPanel();
        renderActiveEventsPanel();
        updateCellHighlights();
        updateQoL();
    });

    // =========================================================
    // FETCH EVENTS VAN SERVER
    // Verwacht van de server per event:
    //   id, name, modifiers (object), start_at (datetime), end_at (datetime)
    // =========================================================

    async function fetchAllEvents({ forceQolRefresh = false } = {}) {
        try {
            const response = await fetch('/events/simulation', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.events) return;

            // Bewaar manuallyEnabled state voor events die al bekend zijn
            const prevState = {};
            allEvents.forEach(e => { prevState[e.id] = e.manuallyEnabled; });

            allEvents = data.events.map(e => ({
                id:             e.id,
                name:           e.name,
                modifiers:      e.modifiers ?? {},
                // Server levert start_at / end_at als datetime string
                // of start_minutes / end_minutes als integers — beide ondersteund
                start_minutes:  e.start_minutes ?? datetimeToSimMinutes(e.start_at),
                end_minutes:    e.end_minutes   ?? datetimeToSimMinutes(e.end_at),
                // Behoudt vorige toggle-staat van de planner, default = ingeschakeld
                manuallyEnabled: prevState[e.id] ?? true,
                isActive:        false,
            }));

            setMaxTime();
            syncTimelineUI();
            renderAllEventsPanel();

            const signature = buildEventSignature(allEvents);
            if (signature !== lastActiveEventSignature || forceQolRefresh) {
                lastActiveEventSignature = signature;
                updateQoL();
            }

        } catch (err) {
            console.error("Fout bij ophalen events:", err);
        }
    }

    // =========================================================
    // GRID SAVE / MOVE
    // =========================================================

    async function saveMove(oldRow, oldCol, newRow, newCol, force = false) {
        try {
            const res = await fetch('/grid/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    old_row: oldRow, old_col: oldCol,
                    new_row: newRow, new_col: newCol,
                    function_id: draggedItem.id,
                    force: force
                })
            });
            return res;
        } catch (err) {
            console.error("Fout bij opslaan gridcel:", err);
            return null;
        }
    }

    // =========================================================
    // EVENT LISTENERS — LIBRARY ITEMS
    // =========================================================

    document.querySelectorAll(".library-item").forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging   = true;
            dropOccurred = false;
            draggedItem  = {
                id:    Number(item.dataset.functionId),
                name:  item.dataset.functionName,
                image: item.dataset.image
            };
            e.dataTransfer.setDragImage(item.querySelector("img"), 16, 16);
        });
    });

    // =========================================================
    // EVENT LISTENERS — GRID CELLS
    // =========================================================

    document.querySelectorAll(".grid-cell").forEach(cell => {
        cell.addEventListener("dragstart", e => {
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;
            isDragging   = true;
            dropOccurred = false;
            draggedItem  = { id: Number(img.dataset.functionId), name: img.alt, image: img.src };
            e.dataTransfer.setDragImage(img, 16, 16);
            sourceCell = cell;
            cell.classList.add("drag-source");
        });

        cell.addEventListener("dragover",  e => { e.preventDefault(); cell.classList.add("drag-over"); });
        cell.addEventListener("dragleave", () => cell.classList.remove("drag-over"));

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            isDragging   = false;
            dropOccurred = true;
            cell.classList.remove("drag-over");

            if (cell.querySelector("img") && !window.confirm("Are you sure you want to replace this feature?")) {
                if (sourceCell) sourceCell.classList.remove("drag-source");
                return;
            }

            const newRow = cell.dataset.row;
            const newCol = cell.dataset.col;
            let oldRow = null, oldCol = null;
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
            img.src = draggedItem.image; img.alt = draggedItem.name;
            img.dataset.functionId = draggedItem.id;
            img.classList.add("grid-function-icon", "object-contain");
            cell.appendChild(img);

            const deleteBtn = document.createElement("button");
            deleteBtn.type  = "button";
            deleteBtn.className = "delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center";
            deleteBtn.setAttribute("aria-label", `Remove ${draggedItem.name} from grid cell`);
            deleteBtn.append("✖");
            cell.appendChild(deleteBtn);
            cell.setAttribute("draggable", "true");
            activateCell(cell);

            lastAction = { oldRow, oldCol, newRow, newCol, functionId: draggedItem.id };
            const undoBtnEl = document.getElementById("undo-btn");
            if (undoBtnEl) undoBtnEl.disabled = false;

            const res = await saveMove(oldRow, oldCol, newRow, newCol, false);

            if (res && res.status === 409) {
                if (window.confirm("Placement is forbidden by adjacency rules. Force placement anyway?")) {
                    const res2 = await saveMove(oldRow, oldCol, newRow, newCol, true);
                    if (!res2 || !res2.ok) {
                        if (originalSourceCell) {
                            originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                            originalSourceCell.setAttribute('draggable', 'true');
                        }
                        cell.innerHTML = ""; cell.removeAttribute('draggable');
                        updateQoL(); return;
                    }
                } else {
                    if (originalSourceCell) {
                        originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                        originalSourceCell.setAttribute('draggable', 'true');
                    }
                    cell.innerHTML = ""; cell.removeAttribute('draggable');
                    updateQoL(); return;
                }
            }

            updateCellHighlights();
            updateQoL();
        });

        cell.addEventListener("click",   () => { if (!isDragging) activateCell(cell); });
        cell.addEventListener("keydown", e  => { if (!isDragging && (e.key === "Enter" || e.key === " ")) activateCell(cell); });

        cell.addEventListener('mouseenter', (event) => {
            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.col);
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => handleTileHover(row, col, event), HOVER_DELAY_MS);
        });
        cell.addEventListener('mouseleave', () => { clearTimeout(hoverTimer); hidePopup(); });
    });

    // =========================================================
    // DELETE BUTTON
    // =========================================================

    document.addEventListener("click", async (e) => {
        if (!e.target.classList.contains("delete-btn")) return;
        const cell = e.target.closest(".grid-cell");
        if (!cell) return;
        cell.innerHTML = "";
        cell.removeAttribute("draggable");
        activateCell(cell);
        try {
            await fetch(`/grid/cell/${cell.dataset.id}/function`, {
                method: "DELETE",
                headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
            });
        } catch (err) { console.error("Fout bij verwijderen functie:", err); }
        updateCellHighlights();
        setTimeout(() => updateQoL(), 10);
    });

    // =========================================================
    // DRAG END (buiten grid)
    // =========================================================

    document.addEventListener("dragend", async (e) => {
        if (!draggedItem || !sourceCell) return;
        if (dropOccurred) { dropOccurred = false; return; }
        const rect = document.querySelector(".city-grid").getBoundingClientRect();
        if (e.pageX >= rect.left && e.pageX <= rect.right && e.pageY >= rect.top && e.pageY <= rect.bottom) return;
        sourceCell.innerHTML = "";
        sourceCell.removeAttribute("draggable");
        activateCell(sourceCell);
        try {
            await fetch(`/grid/cell/${sourceCell.dataset.id}/function`, {
                method: "DELETE",
                headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
            });
        } catch (err) { console.error("Fout bij drag-off delete:", err); }
        draggedItem = null; sourceCell = null;
        updateCellHighlights();
        setTimeout(() => updateQoL(), 10);
    });

    // =========================================================
    // UNDO (primair)
    // =========================================================

    const undoBtn = document.getElementById("undo-btn");
    if (undoBtn) {
        undoBtn.addEventListener("click", async () => {
            if (!lastAction) return;
            await fetch('/grid/update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ old_row: lastAction.newRow, old_col: lastAction.newCol, new_row: lastAction.oldRow, new_col: lastAction.oldCol, function_id: lastAction.functionId, force: true })
            });
            lastAction = null; undoBtn.disabled = true;
            updateQoL(); location.reload();
        });
    }

    // =========================================================
    // UNDO (alternatief)
    // =========================================================

    const undoButtonAlternative = document.getElementById('undoButton');
    if (undoButtonAlternative) {
        undoButtonAlternative.addEventListener('click', () => {
            fetch('/undo', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Content-Type': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;
                const targetCell = document.querySelector(`[data-row="${data.cell.row}"][data-col="${data.cell.col}"]`);
                if (targetCell) {
                    if (data.cell.function_id) {
                        targetCell.innerHTML = `<img src="${data.cell.image}" class="grid-function-icon object-contain" data-function-id="${data.cell.function_id}"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                        targetCell.setAttribute("draggable", "true");
                    } else {
                        targetCell.innerHTML = ""; targetCell.removeAttribute("draggable");
                    }
                    activateCell(targetCell);
                }
                if (data.cleared) {
                    const clearedCell = document.querySelector(`[data-row="${data.cleared.row}"][data-col="${data.cleared.col}"]`);
                    if (clearedCell) { clearedCell.innerHTML = ""; clearedCell.removeAttribute("draggable"); clearedCell.classList.remove("selected"); }
                }
                updateCellHighlights();
                setTimeout(() => updateQoL(), 50);
            });
        });
    }

    // =========================================================
    // INITIËLE CALLS
    // =========================================================

    fetchAllEvents();
    updateQoL();
    requestAnimationFrame(simulationLoop);

    setInterval(() => fetchAllEvents(), 30000);

});