import { getNeighborsWithQoL } from './neighbours.js';
import { simulationLoop } from './simulation.js';
import { setMaxTime, syncTimelineUI } from './regulation.js';


document.addEventListener("DOMContentLoaded", () => {

    // =========================================================
    // VARIABELEN
    // =========================================================

    let currentSimTime = 0;
    let maxSimTime = 100;

    let draggedItem = null;
    let isDragging = false;
    let sourceCell = null;
    let dropOccurred = false;
    let old_score;
    let lastAction = null;

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;
    let lastActiveEventSignature = '';

    const popup = document.getElementById('qol-popup');
    const neighborsList = document.getElementById('popup-neighbors-list');

    let eventTimerInterval = null;
    let serverClockOffsetMs = 0;
    let eventBoundaryTimeouts = [];
    const EVENT_POLL_MS = 1000;
    const MAX_SCHEDULE_MS = 24 * 60 * 60 * 1000;
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
            const num = Number(value);
            const sign = num >= 0 ? '+' : '';
            const label = category.charAt(0).toUpperCase() + category.slice(1);
            return `<li class="text-amber-400">- ${label}: ${sign}${num}</li>`;
        });

        if (items.length === 0) return '';

        return `<ul class="text-[11px] text-gray-400 mt-1 space-y-0.5 list-none pl-0">${items.join('')}</ul>`;
    }

    function buildEventSignature(events) {
        return JSON.stringify(
            (events || []).map(event => ({
                id: event.id,
                end_at: event.end_at ?? null,
                modifiers: event.modifiers ?? {},
            }))
        );
    }

    function simulationNowMs() {
        return Date.now() + serverClockOffsetMs;
    }

    function clearEventBoundarySchedules() {
        eventBoundaryTimeouts.forEach((timeoutId) => clearTimeout(timeoutId));
        eventBoundaryTimeouts = [];
    }

    function scheduleEventBoundary(delayMs) {
        if (delayMs < 0 || delayMs > MAX_SCHEDULE_MS) {
            return;
        }

        const timeoutId = setTimeout(() => {
            updateActiveEvents({ forceQolRefresh: true });
        }, delayMs);

        eventBoundaryTimeouts.push(timeoutId);
    }

    function scheduleEventBoundaries(events) {
        clearEventBoundarySchedules();

        (events || []).forEach((event) => {
            if (event.start_at) {
                scheduleEventBoundary((Number(event.start_at) * 1000) - simulationNowMs());
            }

            if (event.end_at) {
                scheduleEventBoundary((Number(event.end_at) * 1000) - simulationNowMs());
            }
        });
    }

    async function updateActiveEvents({ forceQolRefresh = false } = {}) {
        const listEl = document.getElementById('active-events-list');
        const emptyEl = document.getElementById('active-events-empty');

        if (!listEl || !emptyEl) return;

        try {
            const response = await fetch('/events/active');
            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.events) return;
            console.log(data);

            if (typeof data.server_now_ms === 'number') {
                serverClockOffsetMs = data.server_now_ms - Date.now();
            }

            scheduleEventBoundaries(data.events);

            const signature = buildEventSignature(data.events);
            if (signature !== lastActiveEventSignature) {
                lastActiveEventSignature = signature;
                updateQoL();
            } else if (forceQolRefresh) {
                updateQoL();
            }

            const visibleEvents = data.events.filter((event) => event.is_active || event.start_at);

                        if (visibleEvents.length > 0) {
                emptyEl.classList.add('hidden');
                listEl.innerHTML = '';

                const countdownTimers = [];

                visibleEvents.forEach((event, index) => {
                    const li = document.createElement('li');
                    const borderClass = event.is_active
                        ? 'border-amber-500'
                        : 'border-slate-500';
                    li.className = `p-3 bg-slate-800 border-l-4 ${borderClass} rounded shadow-sm mb-2 text-sm`;

                    const timerId = `event-timer-${index}`;
                    const modifiersHtml = event.is_active ? formatModifiers(event.modifiers) : '';
                    const scheduleHtml = event.is_active
                        ? (event.ends_at_display
                            ? `<div class="text-[11px] text-gray-500 mt-0.5">Ends at ${event.ends_at_display}</div>`
                            : '')
                        : (event.starts_at_display
                            ? `<div class="text-[11px] text-gray-500 mt-0.5">Starts at ${event.starts_at_display}</div>`
                            : '');

                    li.innerHTML = `
                        <div class="font-semibold text-slate-200">${event.name || 'Unnamed event'}</div>
                        ${modifiersHtml}
                        ${scheduleHtml}
                        <div id="${timerId}" class="text-[11px] text-emerald-400 font-mono mt-1 font-bold">
                            Loading...
                        </div>
                    `;
                    listEl.appendChild(li);

                    const targetTimestamp = event.is_active ? event.end_at : event.start_at;

                    if (targetTimestamp) {
                        const targetTimeMs = Number(targetTimestamp) * 1000;

                        if (!Number.isNaN(targetTimeMs) && targetTimeMs > 0) {
                            countdownTimers.push({
                                elementId: timerId,
                                targetTimeMs,
                                isActive: Boolean(event.is_active),
                            });
                        }
                    } else if (event.type === 'recurring') {
                        const timerEl = document.getElementById(timerId);
                        if (timerEl) {
                            timerEl.textContent = event.timing || 'Recurring';
                            timerEl.className = "text-[11px] text-teal-400 mt-1";
                        }
                    } else {
                        const timerEl = document.getElementById(timerId);
                        if (timerEl) timerEl.textContent = "No time available";
                    }
                });

                if (eventTimerInterval) clearInterval(eventTimerInterval);

                if (countdownTimers.length > 0) {
                    const tick = () => {
                        const nowMs = simulationNowMs();
                        let stillRunning = false;
                        let boundaryCrossed = false;

                        countdownTimers.forEach((timer) => {
                            const timerEl = document.getElementById(timer.elementId);
                            if (!timerEl) return;

                            const distance = timer.targetTimeMs - nowMs;

                            if (distance <= 0) {
                                boundaryCrossed = true;
                                timerEl.textContent = timer.isActive ? 'Ended' : 'Starting...';
                                timerEl.className = "text-[11px] text-red-500 font-bold mt-1";
                            } else {
                                stillRunning = true;
                                const totalSeconds = Math.floor(distance / 1000);
                                const minutes = Math.floor(totalSeconds / 60);
                                const seconds = totalSeconds % 60;
                                const label = timer.isActive ? 'Time left' : 'Starts in';
                                timerEl.textContent = `${label}: ${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                                timerEl.className = "text-[11px] text-emerald-400 font-mono mt-1 font-bold";
                            }
                        });

                        if (boundaryCrossed) {
                            updateActiveEvents({ forceQolRefresh: true });
                        }

                        if (!stillRunning) {
                            clearInterval(eventTimerInterval);
                            eventTimerInterval = null;
                        }
                    };

                    tick();
                    eventTimerInterval = setInterval(tick, EVENT_POLL_MS);
                }
            } else {
                emptyEl.classList.remove('hidden');
                listEl.innerHTML = '';
                clearEventBoundarySchedules();
                if (eventTimerInterval) {
                    clearInterval(eventTimerInterval);
                    eventTimerInterval = null;
                }
            }
        } catch (err) {
            console.error("Fout bij updaten events:", err);
        }
    }

    updateActiveEvents({ forceQolRefresh: true });
    setInterval(updateActiveEvents, 5000);

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
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                }
            });
        } catch (err) {
            console.error("Fout bij verwijderen functie:", err);
        }

        setTimeout(() => updateQoL(), 10);
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
            console.error("Fout bij opslaan gridcel:", err);
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
            console.error("Fout bij ophalen QoL:", err);
        }
    }
    function calculateMaxDuration(events) {
        if (!events || events.length === 0) return 100;
        const now = Date.now() / 1000;
        const durations = events.map(e => (e.end_at ? (e.end_at - now) : 0));
        return Math.max(...durations, 100);
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
        let html = '<h3 class="text-xl font-semibold dark:text-teal-500 mb-2">Breakdown QoL Score</h3>';
        html += '<ul class="space-y-1 list-none pl-0 text-sm">';

        for (const [category, info] of Object.entries(data.categories)) {
            const score = Number(info.total);
            let scoreClass = 'text-slate-400';
            if (score > 0) scoreClass = 'text-green-600';
            else if (score < 0) scoreClass = 'text-red-600';

            const scoreSign = score > 0 ? '+' : '';
            html += `
                <li class="font-semibold dark:text-teal-600">
                    - ${category}: <span class="${scoreClass}">${scoreSign}${score}</span>
                </li>`;
        }

        html += '</ul>';

        const totalScore = Number(data.total_score);
        let totalClass = 'text-slate-400';
        if (totalScore > 0) totalClass = 'text-green-600';
        else if (totalScore < 0) totalClass = 'text-red-600';

        const totalSign = totalScore > 0 ? '+' : '';
        html += `
            <p class="font-bold mt-4 dark:text-teal-600">
                Total QoL: <span class="${totalClass}">${totalSign}${totalScore}</span>
            </p>`;

        return html;
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

        const eventModifiers = data.event_modifiers || {};
        const categoryKeys = {
            Safety: 'safety',
            Recreation: 'recreation',
            Environment: 'environment',
            Amenities: 'amenities',
            Mobility: 'mobility',
        };

        let html = '';
        for (const [categoryName, info] of Object.entries(data.categories)) {
            const cellScore = Number(info.total);
            const eventScore = Number(eventModifiers[categoryKeys[categoryName]] || 0);
            const displayScore = cellScore + eventScore;

            let scoreClass = 'text-slate-400';
            if (displayScore > 0) scoreClass = 'text-green-600';
            else if (displayScore < 0) scoreClass = 'text-red-600';

            const scoreSign = displayScore > 0 ? '+' : '';
            html += `
                <div class="mb-2 last:mb-0 w-full">
                    <div class="flex justify-between items-center gap-8">
                        <span class="text-slate-200 font-medium text-sm">${categoryName}</span>
                        <span class="${scoreClass} font-bold text-sm">${scoreSign}${displayScore}</span>
                    </div>
                </div>`;
        }

        const cellTotal = Number(data.total_score);
        let totalClass = 'text-slate-400';
        if (cellTotal > 0) totalClass = 'text-green-600';
        else if (cellTotal < 0) totalClass = 'text-red-600';

        const totalSign = cellTotal > 0 ? '+' : '';
        html += `
            <div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-600/50 w-full">
                <span class="text-slate-300 font-bold text-xs uppercase tracking-wider">Total QoL:</span>
                <span class="${totalClass} font-extrabold text-base">${totalSign}${cellTotal}</span>
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
            console.error("Fout bij ophalen QoL:", err);
        }
    }

    // =========================================================
    // ACTIVE EVENTS
    // =========================================================

    async function updateActiveEvents({ forceQolRefresh = false } = {}) {
        const listEl = document.getElementById('active-events-list');
        const emptyEl = document.getElementById('active-events-empty');

        if (!listEl || !emptyEl) return;

        try {
            const response = await fetch('/events/active', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.events) return;

            const newMax = calculateMaxDuration(data.events);
            setMaxTime(newMax);
            syncTimelineUI();

            const signature = buildEventSignature(data.events);
            if (signature !== lastActiveEventSignature) {
                lastActiveEventSignature = signature;
                updateQoL();
            } else if (forceQolRefresh) {
                updateQoL();
            }

            if (data.events.length === 0) {
                emptyEl.classList.remove('hidden');
                listEl.innerHTML = '';
                return;
            }

            emptyEl.classList.add('hidden');
            listEl.innerHTML = '';

            data.events.forEach((event, index) => {
                const li = document.createElement('li');
                li.className = "p-3 bg-slate-800 border-l-4 border-amber-500 rounded shadow-sm mb-2 text-sm";

                const timerId = `event-timer-${index}`;
                const modifiersHtml = formatModifiers(event.modifiers);
                const endsAtHtml = event.ends_at_display
                    ? `<div class="text-[11px] text-gray-500 mt-0.5">Ends at ${event.ends_at_display}</div>`
                    : '';

                li.innerHTML = `
                    <div class="font-semibold text-slate-200">${event.name || 'Nameless Event'}</div>
                    ${modifiersHtml}
                    ${endsAtHtml}
                    <div id="${timerId}" class="text-[11px] text-emerald-400 font-mono mt-1 font-bold">
                        Loading...
                    </div>
                `;
                listEl.appendChild(li);

                if (event.end_at) {
                    let endTimeMs = 0;
                    if (typeof event.end_at === 'number' || (!isNaN(event.end_at) && !isNaN(parseFloat(event.end_at)))) {
                        endTimeMs = Number(event.end_at) * 1000;
                    } else {
                        const formattedString = String(event.end_at).replace(' ', 'T');
                        endTimeMs = new Date(formattedString).getTime();
                    }

                    if (!isNaN(endTimeMs) && endTimeMs > 0) {
                        // Timer logic can be added here if needed
                    }
                } else if (event.type === 'recurring') {
                    const timerEl = document.getElementById(timerId);
                    if (timerEl) {
                        timerEl.textContent = event.timing || 'Recurring';
                        timerEl.className = "text-[11px] text-teal-400 mt-1";
                    }
                } else {
                    const timerEl = document.getElementById(timerId);
                    if (timerEl) timerEl.textContent = "No end time known";
                }
            });

            maxSimTime = newMax;
            const timeline = document.getElementById('simulation-timeline');
            if (timeline) timeline.max = maxSimTime;

        } catch (err) {
            console.error("Fout bij ophalen active events:", err);
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
            console.error("Fout bij opslaan gridcel:", err);
            return null;
        }
    }

    // =========================================================
    // EVENT LISTENERS — LIBRARY ITEMS
    // =========================================================

    const items = document.querySelectorAll(".library-item");

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

    // =========================================================
    // EVENT LISTENERS — GRID CELLS
    // =========================================================

    const cells = document.querySelectorAll(".grid-cell");

    cells.forEach(cell => {
        cell.addEventListener("dragstart", e => {
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
            e.preventDefault();
            cell.classList.add("drag-over");
        });

        cell.addEventListener("dragleave", () => {
            cell.classList.remove("drag-over");
        });

        cell.addEventListener("drop", async e => {
            e.preventDefault();
            isDragging = false;
            dropOccurred = true;
            cell.classList.remove("drag-over");

            const isOccupied = cell.querySelector("img") !== null;

            if (isOccupied) {
                const wantsToReplace = window.confirm("Are you sure you want to replace this feature?");
                if (!wantsToReplace) {
                    if (sourceCell) sourceCell.classList.remove("drag-source");
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
            deleteBtn.className = "delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center";
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
                        }
                        cell.innerHTML = "";
                        cell.removeAttribute('draggable');
                        updateQoL();
                        return;
                    }
                } else {
                    if (originalSourceCell) {
                        originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                        originalSourceCell.setAttribute('draggable', 'true');
                    }
                    cell.innerHTML = "";
                    cell.removeAttribute('draggable');
                    updateQoL();
                    return;
                }
            }

            updateQoL();
        });

        cell.addEventListener("click", () => {
            if (!isDragging) activateCell(cell);
        });

        cell.addEventListener("keydown", e => {
            if (!isDragging && (e.key === "Enter" || e.key === " ")) activateCell(cell);
        });

        cell.addEventListener('mouseenter', (event) => {
            const row = parseInt(cell.dataset.row);
            const col = parseInt(cell.dataset.col);
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => {
                handleTileHover(row, col, event);
            }, HOVER_DELAY_MS);
        });

        cell.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimer);
            hidePopup();
        });
    });

    // =========================================================
    // DELETE BUTTON (event delegation)
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
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                }
            });
        } catch (err) {
            console.error("Fout bij verwijderen functie:", err);
        }

        setTimeout(() => updateQoL(), 10);
    });

    // =========================================================
    // DRAG END (buiten grid)
    // =========================================================

    document.addEventListener("dragend", async (e) => {
        if (!draggedItem || !sourceCell) return;
        if (dropOccurred) { dropOccurred = false; return; }

        const grid = document.querySelector(".city-grid");
        const rect = grid.getBoundingClientRect();
        const x = e.pageX;
        const y = e.pageY;

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
        } catch (err) {
            console.error("Fout bij drag-off delete:", err);
        }

        draggedItem = null;
        sourceCell = null;
        setTimeout(() => updateQoL(), 10);
    });

    // =========================================================
    // UNDO KNOP (primair)
    // =========================================================

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

    // =========================================================
    // UNDO KNOP (alternatief via /undo endpoint)
    // =========================================================

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

    // =========================================================
    // INITIËLE CALLS
    // =========================================================

    updateActiveEvents();
    updateQoL();
    requestAnimationFrame(simulationLoop);

    setInterval(() => {
        updateActiveEvents({ forceQolRefresh: false });
    }, 10000);

}); 