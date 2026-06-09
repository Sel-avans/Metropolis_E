import { registerActiveEventIdsProvider, getNeighborsWithQoL } from './neighbours.js';
import { initLibraryFilter } from './library-filter.js';
import { simulationLoop, onSimulationTimeUpdate, onSimulationCycleComplete } from './simulation.js';
import {
    setMaxTime,
    setFullCycleMode,
    getFullCycleMode,
    syncTimelineUI,
    syncPlayPauseUI,
    minutesToHHMM,
    datetimeToSimMinutes,
    getCurrentTime,
    getMaxTime,
    getIsPlaying,
    setCurrentTime,
    setIsPlaying,
    setDayNightDuration,
    getDayHours,
    getNightHours,
    HOURS_MIN,
    HOURS_MAX,
} from './regulation.js';
import { resetDayNightIndicatorState } from './day-night-indicator.js';
import { initLibraryPreview, closePreview } from './library-preview.js';

const SIM_STATE_KEY = 'metropolis_simulation_state';

// Library filter/preview will be initialized when the grid page initializes

// =========================================================
// HULPFUNCTIE: maak een toegankelijke delete-knop
// WCAG 4.1.2: altijd aria-label meegeven zodat screen readers de knop kunnen benoemen,
// ook als de knop dynamisch aangemaakt wordt via JavaScript.
// =========================================================
function createDeleteBtn(functionName, row = '', col = '') {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-red-400';
    const locationLabel = row && col ? ` in grid cell ${row},${col}` : ' from grid cell';
    btn.setAttribute('aria-label', `Remove ${functionName}${locationLabel}`);
    const icon = document.createElement('span');
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '✖';
    btn.appendChild(icon);
    return btn;
}

// =========================================================
// HULPFUNCTIE: herstel een cel met functie-inhoud
// Gecentraliseerd zodat alle restore-paden (undo, conflict-fallback)
// altijd dezelfde toegankelijke markup genereren.
// =========================================================
function restoreCellContent(cell, functionId, functionName, functionImage) {
    cell.innerHTML = '';

    const img = document.createElement('img');
    img.src = functionImage;
    img.alt = functionName;
    img.dataset.functionId = functionId;
    img.classList.add('grid-function-icon', 'object-contain');
    cell.appendChild(img);

    const row = cell.dataset.row ?? '';
    const col = cell.dataset.col ?? '';
    cell.appendChild(createDeleteBtn(functionName, row, col));
    cell.setAttribute('draggable', 'true');

    // WCAG 4.1.2: aria-label bijwerken na restore
    cell.setAttribute('aria-label', `Cell ${row},${col} contains ${functionName}. Press Enter to select.`);
}

function initGridPage() {
    if (!document.querySelector('.city-grid')) return;

    const gridRoot = document.querySelector('.city-grid');
    if (gridRoot.dataset.gridInitialized === 'true') return;
    gridRoot.dataset.gridInitialized = 'true';

    // Initialize library UI (filter + preview) when grid and library DOM are present
    try { initLibraryFilter(); } catch (e) { /* ignore if not present */ }
    try { initLibraryPreview(); } catch (e) { /* ignore if not present */ }

    // =========================================================
    // VARIABELEN
    // =========================================================

    let draggedItem = null;
    let isDragging  = false;
    let sourceCell  = null;
    let dropOccurred = false;
    let old_score;
    let lastAction = null;

    let allEvents = [];
    let simulationReferenceDate = null;

    const HOVER_DELAY_MS = 300;
    let hoverTimer = null;
    let qolUpdateTimer = null;
    let qolFetchInFlight = false;
    let qolFetchPending = false;
    let qolFetchGeneration = 0;
    let lastQoLEventIdsKey = null;
    let saveStateTimer = null;
    let lastActiveEventSignature = '';

    // =========================================================
    // SESSION STATE
    // =========================================================

    function loadSimulationState() {
        try {
            const raw = sessionStorage.getItem(SIM_STATE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch { return null; }
    }

    function saveSimulationState() {
        if (!allEvents.length) return;
        sessionStorage.setItem(SIM_STATE_KEY, JSON.stringify({
            currentTime: getCurrentTime(),
            isPlaying: getIsPlaying(),
            fullCycleMode: getFullCycleMode(),
            eventConfigs: Object.fromEntries(allEvents.map(e => [e.id, buildEventConfigFingerprint(e)])),
            events: allEvents.map(e => ({
                id: e.id,
                activatedEarly: Boolean(e.activatedEarly),
                activatedForCycle: Boolean(e.activatedForCycle),
                cycleActivationStart: e.cycleActivationStart ?? null,
            })),
        }));
    }

    function buildEventConfigFingerprint(event) {
        return JSON.stringify({
            start: event.start_minutes,
            end: event.end_minutes,
            ids: (event.affectedFunctionIds || []).slice().sort((a, b) => a - b),
            mods: event.modifiers ?? {},
            fits: event.fitsInCycle,
            type: event.type,
        });
    }

    function scheduleSaveSimulationState() {
        if (saveStateTimer) clearTimeout(saveStateTimer);
        saveStateTimer = setTimeout(saveSimulationState, 300);
    }

    function clearSimulationState() {
        sessionStorage.removeItem(SIM_STATE_KEY);
    }

    let eventTimerInterval = null;
    let serverClockOffsetMs = 0;
    let eventBoundaryTimeouts = [];
    const EVENT_POLL_MS = 1000;
    const MAX_SCHEDULE_MS = 24 * 60 * 60 * 1000;
    // =========================================================
    // HULPFUNCTIES
    // =========================================================

    function activateCell(cell, additive = false) {
        if (!additive) document.querySelectorAll(".grid-cell").forEach(c => c.classList.remove("selected"));
        cell.classList.add("selected");
    }

    // --- LOCK / APPROVE HELPERS ---
    function isCellOccupied(cell) {
        return cell.querySelector('.grid-function-icon') !== null;
    }

    function isLockedCell(cell) {
        return cell && cell.classList.contains('is-locked');
    }

    function canSelectLockedCell(cell) {
        return cell && cell.dataset.allowLockSelect === 'true';
    }

    function flashLockExplanation(cell) {
        if (!cell) return;
        cell.classList.add('show-lock-explanation');
        window.setTimeout(() => cell.classList.remove('show-lock-explanation'), 2500);
    }

    function showLockedPlacementMessage(cell) {
        const message = isCellOccupied(cell)
            ? "You can't replace the function in this area"
            : "You can't add a function in this area";
        alert(message);
    }

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
            lockExplanation.textContent = 'This area is approved and cannot be changed.';
            lockIndicator.insertAdjacentElement('afterend', lockExplanation);
        }

        return { lockIndicator, lockExplanation };
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
            const deleteBtn = createDeleteBtn(img.alt, cell.dataset.row, cell.dataset.col);
            cell.appendChild(deleteBtn);
        }
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

    function buildEventSignature(events, simTime = getCurrentTime()) {
        return JSON.stringify(
            (events || []).map(e => ({
                id: e.id,
                active: e.isActive ?? false,
                contributing: isEventContributingToQoL(e, simTime),
                early: e.activatedEarly ?? false,
                cycle: e.activatedForCycle ?? false,
            }))
        );
    }

    function cycleEvents() {
        return allEvents.filter(e => e.fitsInCycle || e.activatedForCycle);
    }

    function longEvents() {
        return allEvents.filter(e => !e.fitsInCycle && !e.activatedForCycle);
    }

    function isSimulationAtDayStart(simTime) {
        return simTime === 0 && !getIsPlaying();
    }

    function resetLongEventCycleActivation(event) {
        event.activatedForCycle = false;
        event.cycleActivationStart = null;
        event.isActive = false;
    }

    function normalizeSimDate(value) {
        if (value == null || value === '') return null;
        const str = String(value).slice(0, 10);
        return /^\d{4}-\d{2}-\d{2}$/.test(str) ? str : null;
    }

    /**
     * Recurring events in simulation replay a representative city day.
     * The daily time window (e.g. 18:00–22:00) drives activation — not whether
     * simulationReferenceDate equals recurring_start_date (a future policy start
     * should still be previewable on the grid).
     * Only block after recurring_end_date has passed.
     */
    function matchesRecurringSchedule(event, referenceDate) {
        if (event.type !== 'recurring') return true;

        const ref = normalizeSimDate(referenceDate);
        const end = normalizeSimDate(event.recurringEndDate);
        if (ref && end && ref > end) return false;

        return true;
    }

    function isScheduledEventActive(event, simTime) {
        if (event.type === 'recurring' && !matchesRecurringSchedule(event, simulationReferenceDate)) return false;
        return isEventActiveAtSimTime(event, simTime);
    }

    function isEventContributingToQoL(event, simTime) {
        if (isSimulationAtDayStart(simTime)) return false;

        if (!event.fitsInCycle) {
            if (!event.activatedForCycle) return false;
            const activationStart = Number(event.cycleActivationStart);
            if (Number.isNaN(activationStart) || simTime < activationStart || simTime >= getMaxTime()) return false;
            return true;
        }

        if (hasEventEnded(event, simTime)) return false;

        const scheduledActive = isScheduledEventActive(event, simTime);
        const earlyActive = event.activatedEarly && isBeforeEventStart(event, simTime);

        return scheduledActive || earlyActive;
    }

    function getActiveEventIdsForQoL(simTime = getCurrentTime()) {
        return allEvents.filter(e => isEventContributingToQoL(e, simTime)).map(e => e.id);
    }

    function buildQoLActiveIdsKey(simTime = getCurrentTime()) {
        return getActiveEventIdsForQoL(simTime).slice().sort((a, b) => a - b).join(',');
    }

    function isEventActiveAtSimTime(event, simTime) {
        const start = Number(event.start_minutes);
        const end   = Number(event.end_minutes);
        if (Number.isNaN(start) || Number.isNaN(end)) return false;
        if (end > 1440) return simTime >= start;
        return simTime >= start && simTime <= end;
    }

    function isBeforeEventStart(event, simTime) {
        return simTime < Number(event.start_minutes);
    }

    function hasEventEnded(event, simTime) {
        const end = Number(event.end_minutes);
        if (Number.isNaN(end)) return false;
        if (end > 1440) return false;
        return simTime > end;
    }

    function syncEventActiveStates(simTime) {
        if (!allEvents.length) return false;
        let changed = false;

        allEvents.forEach(event => {
            if (!event.fitsInCycle) {
                if (!event.activatedForCycle) {
                    if (event.isActive) { event.isActive = false; changed = true; }
                    return;
                }
                const activationStart = Number(event.cycleActivationStart);
                const cycleEnd = getMaxTime();
                if (simTime >= cycleEnd || Number.isNaN(activationStart)) {
                    if (simTime >= cycleEnd) { resetLongEventCycleActivation(event); changed = true; }
                    else if (event.isActive) { event.isActive = false; changed = true; }
                    return;
                }
                const shouldBeActive = isSimulationAtDayStart(simTime) ? false : simTime >= activationStart;
                if (event.isActive !== shouldBeActive) { event.isActive = shouldBeActive; changed = true; }
                return;
            }

            if (hasEventEnded(event, simTime)) {
                if (event.activatedEarly) { event.activatedEarly = false; changed = true; }
                if (event.isActive) { event.isActive = false; changed = true; }
                return;
            }

            const scheduledActive = isScheduledEventActive(event, simTime);
            const earlyActive = event.activatedEarly && isBeforeEventStart(event, simTime);
            const shouldBeActive = !isSimulationAtDayStart(simTime) && (scheduledActive || earlyActive);

            if (event.isActive !== shouldBeActive) { event.isActive = shouldBeActive; changed = true; }
        });

        return changed;
    }

    function toggleEventEarlyActivation(event, simTime) {
        if (hasEventEnded(event, simTime) || !isBeforeEventStart(event, simTime)) return;
        if (event.activatedEarly) {
            event.activatedEarly = false;
            event.isActive = false;
            return;
        }
        event.activatedEarly = true;
    }

    function toggleLongEventCycleActivation(event, simTime) {
        if (event.fitsInCycle || simTime >= getMaxTime()) return;
        if (event.activatedForCycle) { resetLongEventCycleActivation(event); return; }
        event.activatedForCycle = true;
        event.cycleActivationStart = simTime;
    }

    function refreshEventPanelsAndGrid() {
        const simTime = getCurrentTime();
        syncEventActiveStates(simTime);

        lastActiveEventSignature = buildEventSignature(allEvents, simTime);
        lastQoLEventIdsKey = null;

        renderActiveEventsPanel();
        renderAllEventsPanel();
        updateCellHighlights();
        updateQoL({ immediate: true }).then(() => {
            lastQoLEventIdsKey = buildQoLActiveIdsKey(getCurrentTime());
        });
        scheduleSaveSimulationState();
    }

    function applySimulationTime(simTime) {
        syncEventActiveStates(simTime);

        if (!allEvents.length) return;

        const signature = buildEventSignature(allEvents, simTime);
        const idsKey    = buildQoLActiveIdsKey(simTime);
        const uiChanged  = signature !== lastActiveEventSignature;
        const qolChanged = lastQoLEventIdsKey === null || idsKey !== lastQoLEventIdsKey;

        if (!uiChanged && !qolChanged) return;

        if (uiChanged) {
            lastActiveEventSignature = signature;
            renderActiveEventsPanel();
            renderAllEventsPanel();
            updateCellHighlights();
        }

        if (qolChanged) {
            lastQoLEventIdsKey = idsKey;
            updateQoL();
        }

        scheduleSaveSimulationState();
    }

    function compareScores(data) {
        let html = '';
        if (old_score !== undefined) {
            const delta = data.total_score - old_score;
            html += `<span class="text-xl float-right ${delta < 0 ? 'text-red-600' : 'text-green-600'}">
                ${delta >= 0 ? '+' : ''}${delta}
            </span>`;
        }
        old_score = data.total_score;
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
        const popupEl = document.getElementById('qol-popup');
        if (!popupEl) return;
        popupEl.style.left = `${x + 15}px`;
        popupEl.style.top  = `${y + 15}px`;
    }

    function renderNeighborsList(data) {
        const listEl = document.getElementById('popup-neighbors-list');
        if (!listEl) return;
        listEl.innerHTML = '';

        const categories   = data?.categories ?? {};
        const eventModifiers = data?.event_modifiers ?? {};
        const categoryKeys = {
            Safety: 'safety', Recreation: 'recreation', Environment: 'environment',
            Amenities: 'amenities', Mobility: 'mobility',
        };
        const entries = Object.entries(categories);

        if (!entries.length) {
            listEl.innerHTML = '<li class="text-slate-400 text-sm">No active QoL influences on this cell</li>';
            return;
        }

        let html = '';
        let hasInfluence = false;

        for (const [categoryName, info] of entries) {
            const cellScore    = Number(info?.total ?? 0);
            const eventScore   = Number(eventModifiers[categoryKeys[categoryName]] ?? 0);
            const displayScore = cellScore + eventScore;

            if (displayScore === 0 && eventScore === 0 && cellScore === 0) continue;
            hasInfluence = true;

            const cls  = displayScore > 0 ? 'text-green-600' : displayScore < 0 ? 'text-red-600' : 'text-slate-400';
            const sign = displayScore > 0 ? '+' : '';

            html += `<div class="mb-2 last:mb-0 w-full">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-white font-medium text-sm">${categoryName}</span>
                    <span class="${cls} font-bold text-sm">${sign}${displayScore}</span>
                </div>`;

            if (cellScore !== 0) {
                const cellCls  = cellScore > 0 ? 'text-green-500' : 'text-red-500';
                const cellSign = cellScore > 0 ? '+' : '';
                html += `<div class="text-[10px] text-slate-400 mt-0.5">Base: <span class="${cellCls}">${cellSign}${cellScore}</span></div>`;
            }
            if (eventScore !== 0) {
                const eventCls  = eventScore > 0 ? 'text-amber-400' : 'text-red-400';
                const eventSign = eventScore > 0 ? '+' : '';
                html += `<div class="text-[10px] text-slate-400 mt-0.5">Event: <span class="${eventCls}">${eventSign}${eventScore}</span></div>`;
            }
            html += '</div>';
        }

        if (!hasInfluence) {
            listEl.innerHTML = '<li class="text-slate-400 text-sm">No active QoL influences on this cell</li>';
            return;
        }

        const cellTotal = Number(data?.total_score ?? 0);
        const tcls  = cellTotal > 0 ? 'text-green-600' : cellTotal < 0 ? 'text-red-600' : 'text-slate-400';
        const tsign = cellTotal > 0 ? '+' : '';
        html += `<div class="flex justify-between items-center mt-3 pt-2 border-t border-slate-600/50 w-full">
            <span class="text-slate-300 font-bold text-xs uppercase tracking-wider">Total QoL:</span>
            <span class="${tcls} font-extrabold text-base">${tsign}${cellTotal}</span>
        </div>`;
        listEl.innerHTML = html;
    }

    function showPopup() {
        const popupEl = document.getElementById('qol-popup');
        if (!popupEl) return;
        popupEl.classList.remove('hidden');
        void popupEl.offsetWidth;
        popupEl.classList.remove('opacity-0', 'scale-95');
        popupEl.classList.add('opacity-100', 'scale-100');
    }

    function hidePopup() {
        const popupEl = document.getElementById('qol-popup');
        if (!popupEl) return;
        popupEl.classList.add('opacity-0', 'scale-95');
        popupEl.classList.remove('opacity-100', 'scale-100');
        setTimeout(() => popupEl.classList.add('hidden'), 150);
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

    async function updateQoL({ immediate = false } = {}) {
        if (qolUpdateTimer) { clearTimeout(qolUpdateTimer); qolUpdateTimer = null; }

        const executeFetch = async () => {
            qolFetchGeneration += 1;
            const generation  = qolFetchGeneration;
            const queryString = `?active_event_ids=${getActiveEventIdsForQoL().join(',')}`;

            qolFetchInFlight = true;
            try {
                const scoreEl     = document.getElementById('qol-score-value');
                const breakdownEl = document.getElementById('breakdown-qol-score');
                const oldScoreEl  = document.getElementById('old-qol-score');
                const response    = await fetch(`/qol/details${queryString}`, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok || generation !== qolFetchGeneration) return;
                const data = await response.json();
                if (generation !== qolFetchGeneration) return;
                if (scoreEl)     scoreEl.textContent  = data.total_score;
                if (oldScoreEl)  oldScoreEl.innerHTML = compareScores(data);
                if (breakdownEl) breakdownEl.innerHTML = renderQoLBreakdown(data);
            } catch (err) {
                console.error("Fout bij ophalen QoL:", err);
            } finally {
                qolFetchInFlight = false;
                if (qolFetchPending) { qolFetchPending = false; executeFetch(); }
            }
        };

        const scheduleFetch = () => {
            if (qolFetchInFlight) { qolFetchPending = true; return Promise.resolve(); }
            return executeFetch();
        };

        if (immediate) return scheduleFetch();
        qolUpdateTimer = setTimeout(() => scheduleFetch(), 250);
    }

    // =========================================================
    // PDF EXPORT
    // =========================================================

    function setExportMessage(message, isError = false) {
        const statusEl = document.getElementById('exportPdfStatus');
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.classList.toggle('text-red-600', isError);
        statusEl.classList.toggle('text-slate-600', !isError);
        statusEl.classList.toggle('dark:text-slate-400', !isError);
    }

    async function handleExportPdf() {
        const button = document.getElementById('exportPdfButton');
        if (!button) return;

        button.disabled = true;
        setExportMessage('Preparing PDF export...');

        const timeoutId = setTimeout(() => {
            setExportMessage('Generating PDF. Please wait...');
        }, 2000);

        try {
            const response = await fetch('/grid/export-pdf', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                }
            });

            clearTimeout(timeoutId);

            if (!response.ok) {
                let errorMessage = 'PDF export failed';
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    try {
                        const errorData = await response.json();
                        errorMessage = errorData.error || errorMessage;
                    } catch (e) {
                        console.error('Failed to parse error JSON:', e);
                    }
                } else {
                    const errorText = await response.text();
                    console.error('PDF export error response:', errorText);
                    if (errorText.includes('error')) {
                        errorMessage = 'Server error: Check console for details';
                    }
                }
                
                console.error('PDF export failed with status', response.status, errorMessage);
                setExportMessage(errorMessage, true);
                button.disabled = false;
                return;
            }

            const blob = await response.blob();
            const filename = `simulation-report-${new Date().toISOString().slice(0,19).replace(/[:T-]/g, '-')}.pdf`;
            const fileUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = fileUrl;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            URL.revokeObjectURL(fileUrl);

            setExportMessage('✓ PDF export completed. Check your downloads folder.');
            button.disabled = false;
        } catch (err) {
            clearTimeout(timeoutId);
            console.error('Export PDF error:', err);
            setExportMessage('PDF export failed: ' + (err.message || 'Unknown error'), true);
            button.disabled = false;
        }
    }

    const exportPdfButton = document.getElementById('exportPdfButton');
    if (exportPdfButton) {
        exportPdfButton.addEventListener('click', handleExportPdf);
    }

    // =========================================================
    // HIGHLIGHT LOGICA
    // =========================================================

    function updateCellHighlights() {
        document.querySelectorAll('.grid-cell').forEach(cell => {
            const functionImg = cell.querySelector('.grid-function-icon');
            if (!functionImg) { cell.classList.remove('event-highlight'); return; }

            const functionId = Number(functionImg.dataset.functionId);
            const cellCategories = (functionImg.dataset.categories || '')
                .toLowerCase()
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);

            const isHighlighted = allEvents.some(event => {
                if (!event.isActive) return false;

                const functionIds = (event.affectedFunctionIds || [])
                    .map(Number)
                    .filter(id => !Number.isNaN(id) && id > 0);

                if (functionIds.length > 0) {
                    return functionIds.includes(functionId);
                }

                const eventCats = (event.affected_categories || []).map(c => c.toLowerCase());
                return cellCategories.some(cat => eventCats.includes(cat));
            });

            cell.classList.toggle('event-highlight', isHighlighted);
        });
    }

    // =========================================================
    // SIMULATIE TICK
    // =========================================================

    function onSimulationTick(simTime) {
        applySimulationTime(simTime);
    }

    function onSimulationCycleComplete() {
        allEvents.forEach(event => {
            event.activatedEarly = false;
            if (event.activatedForCycle) resetLongEventCycleActivation(event);
        });
        lastQoLEventIdsKey = null;
        lastActiveEventSignature = '';
        applySimulationTime(getCurrentTime());
        updateQoL({ immediate: true });
        scheduleSaveSimulationState();
    }

    onSimulationTimeUpdate(onSimulationTick);
    onSimulationCycleComplete(onSimulationCycleComplete);

    // =========================================================
    // EVENTS PANEL RENDERING
    // =========================================================

    function renderToggleButton(event, { showDeactivate }) {
        return `<button
            type="button"
            class="event-toggle-btn flex-shrink-0 px-2 py-1 text-xs font-semibold rounded transition
                ${showDeactivate
                    ? 'bg-amber-500 hover:bg-amber-600 text-white'
                    : 'bg-slate-600 hover:bg-teal-600 hover:text-white text-slate-200'}"
            data-event-id="${event.id}">
            ${showDeactivate ? 'Deactivate' : 'Activate'}
        </button>`;
    }

    function renderEventListItem(event, { panel }) {
        const li = document.createElement('li');
        li.className = 'p-3 bg-slate-800 border border-slate-600 rounded mb-2 text-sm';
        li.dataset.eventId = event.id;

        const modifiersHtml = formatModifiers(event.modifiers);
        const simTime    = getCurrentTime();
        const isActive   = event.isActive;
        const isWaiting  = event.activatedEarly && isBeforeEventStart(event, simTime);
        const isLongEvent = !event.fitsInCycle;
        const startLabel = minutesToHHMM(event.start_minutes);
        const endLabel   = minutesToHHMM(event.end_minutes);

        let toggleButton = '';
        if (isLongEvent && panel === 'active' && event.activatedForCycle) {
            toggleButton = renderToggleButton(event, { showDeactivate: true });
        } else if (isLongEvent && panel === 'all') {
            toggleButton = renderToggleButton(event, { showDeactivate: false });
        } else if (!isLongEvent) {
            const canToggleEarly = isBeforeEventStart(event, simTime) && !hasEventEnded(event, simTime);
            const showDeactivate = event.activatedEarly && isBeforeEventStart(event, simTime);
            if (canToggleEarly) {
                toggleButton = renderToggleButton(event, { showDeactivate });
            }
        }

        if (panel === 'active') {
            li.className = isActive
                ? 'p-3 bg-slate-800 border-l-4 border-amber-500 rounded shadow-sm mb-2 text-sm'
                : 'p-3 bg-slate-800/60 border border-slate-700 rounded mb-2 text-sm opacity-80';
        }

        const durationLabel = event.fitsInCycle
            ? `${startLabel} – ${endLabel}`
            : `${startLabel} – ${endLabel} · ${Math.round((event.calendarDurationMinutes ?? event.durationMinutes) / 60)}h total`;

        li.innerHTML = `
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <div class="font-semibold text-slate-200 truncate">${event.name || 'Nameless Event'}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">${durationLabel}</div>
                    ${isActive && !isWaiting ? '<div class="text-[10px] text-amber-400 mt-1 font-semibold uppercase tracking-wide">Active now</div>' : ''}
                    ${isWaiting ? '<div class="text-[10px] text-sky-400 mt-1 font-semibold uppercase tracking-wide">Manually activated · starts at ' + startLabel + '</div>' : ''}
                    ${isLongEvent && event.activatedForCycle && !isActive && isSimulationAtDayStart(simTime) ? '<div class="text-[10px] text-sky-400 mt-1 font-semibold uppercase tracking-wide">Starts on play</div>' : ''}
                    ${isLongEvent && event.activatedForCycle && isActive ? '<div class="text-[10px] text-sky-400 mt-1 font-semibold uppercase tracking-wide">Active until end of cycle</div>' : ''}
                    ${modifiersHtml}
                </div>
                ${toggleButton}
            </div>`;

        return li;
    }

    function renderAllEventsPanel() {
        const listEl = document.getElementById('all-events-detail-list');
        if (!listEl) return;
        listEl.innerHTML = '';
        const events = longEvents();
        if (!events.length) {
            listEl.innerHTML = '<li class="text-sm text-gray-500">No events longer than 24 hours.</li>';
            return;
        }
        events.forEach(event => listEl.appendChild(renderEventListItem(event, { panel: 'all' })));
    }

    function renderActiveEventsPanel() {
        const listEl = document.getElementById('active-events-list');
        if (!listEl) return;
        listEl.innerHTML = '';
        const events = cycleEvents();
        if (!events.length) {
            listEl.innerHTML = '<li class="text-sm text-gray-500">No events within the 24-hour cycle.</li>';
            return;
        }
        events.forEach(event => listEl.appendChild(renderEventListItem(event, { panel: 'active' })));
    }

    // Toggle handler
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.event-toggle-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const eventId = Number(btn.dataset.eventId);
        const event   = allEvents.find(ev => Number(ev.id) === eventId);
        if (!event) return;

        const simTime = getCurrentTime();
        if (event.fitsInCycle) {
            toggleEventEarlyActivation(event, simTime);
        } else {
            toggleLongEventCycleActivation(event, simTime);
        }
        refreshEventPanelsAndGrid();
    });

    function syncFullCycleToggleUI() {
        const btn = document.getElementById('full-cycle-toggle');
        if (!btn) return;

        const active = getFullCycleMode();
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.dataset.active = active ? 'true' : 'false';
        btn.textContent = active ? 'Day/Night Cycle Enabled' : 'Day Simulation';
        btn.setAttribute(
            'aria-label',
            active
                ? 'Day/night cycle enabled. Click to switch to day-only simulation (06:00 to 24:00).'
                : 'Day simulation only (06:00 to 24:00). Click to enable the full day/night cycle (06:00 to 06:00).'
        );
        btn.title = active
            ? 'Day/night cycle is active (06:00 to 06:00). Click to show day only (06:00 to 24:00).'
            : 'Day simulation (06:00 to 24:00). Click to enable the full day/night cycle (06:00 to 06:00).';
    }

    // Timeline scrub
    const simulationTimeline = document.getElementById('simulation-timeline');
    if (simulationTimeline) {
        simulationTimeline.addEventListener('input', (e) => {
            applySimulationTime(parseInt(e.target.value, 10));
        });
    }

    const fullCycleToggle = document.getElementById('full-cycle-toggle');
    if (fullCycleToggle) {
        fullCycleToggle.addEventListener('click', () => {
            setFullCycleMode(!getFullCycleMode());
            resetDayNightIndicatorState();
            syncFullCycleToggleUI();
            syncTimelineUI();
            applySimulationTime(getCurrentTime());
            scheduleSaveSimulationState();
        });
    }

    ['forwardBtn', 'reverseBtn'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => {
            applySimulationTime(getCurrentTime());
        });
    });

    document.getElementById('playPauseBtn')?.addEventListener('click', () => {
        requestAnimationFrame(() => {
            applySimulationTime(getCurrentTime());
            scheduleSaveSimulationState();
        });
    });

    document.getElementById('replayBtn')?.addEventListener('click', () => {
        allEvents.forEach(event => {
            event.activatedEarly = false;
            if (event.activatedForCycle) resetLongEventCycleActivation(event);
        });
        clearSimulationState();
        lastQoLEventIdsKey = null;
        lastActiveEventSignature = '';
        resetDayNightIndicatorState();
        syncTimelineUI();
        applySimulationTime(getCurrentTime());
        updateQoL({ immediate: true });
    });

    // =========================================================
    // DAG / NACHT DUUR INPUTS
    // =========================================================

    const dayInput   = document.getElementById('day-hours-input');
    const nightInput = document.getElementById('night-hours-input');
    const validMsg   = document.getElementById('duration-validation-msg');

    /**
     * Pas duur aan, update gekoppeld veld, herbereken timeline en simulatie.
     * Wordt maximaal 1x per animatieframe uitgevoerd (debounce via rAF).
     */
    let _durationRafPending = false;
    function _applyDurationChange(dayHours) {
        if (_durationRafPending) return;
        _durationRafPending = true;
        requestAnimationFrame(() => {
            _durationRafPending = false;
            setDayNightDuration(dayHours);
            resetDayNightIndicatorState();
            syncTimelineUI();
            applySimulationTime(getCurrentTime());
            scheduleSaveSimulationState();
        });
    }

    if (dayInput) {
        // Zet initiële waarde vanuit localStorage
        dayInput.value = getDayHours();

        dayInput.addEventListener('input', () => {
            const d = parseInt(dayInput.value, 10);
            if (!d || d < HOURS_MIN || d > HOURS_MAX) {
                validMsg?.classList.remove('hidden');
                return;
            }
            validMsg?.classList.add('hidden');
            if (nightInput) nightInput.value = 24 - d;
            _applyDurationChange(d);
        });

        // Klem waarde bij verlaten van het veld
        dayInput.addEventListener('blur', () => {
            const d = parseInt(dayInput.value, 10);
            const clamped = isNaN(d) ? getDayHours() : Math.max(HOURS_MIN, Math.min(HOURS_MAX, d));
            dayInput.value = clamped;
            if (nightInput) nightInput.value = 24 - clamped;
            validMsg?.classList.add('hidden');
            _applyDurationChange(clamped);
        });
    }

    if (nightInput) {
        nightInput.value = getNightHours();

        nightInput.addEventListener('input', () => {
            const n = parseInt(nightInput.value, 10);
            if (!n || n < HOURS_MIN || n > HOURS_MAX) {
                validMsg?.classList.remove('hidden');
                return;
            }
            validMsg?.classList.add('hidden');
            const d = 24 - n;
            if (dayInput) dayInput.value = d;
            _applyDurationChange(d);
        });

        nightInput.addEventListener('blur', () => {
            const n = parseInt(nightInput.value, 10);
            const clamped = isNaN(n) ? getNightHours() : Math.max(HOURS_MIN, Math.min(HOURS_MAX, n));
            nightInput.value = clamped;
            const d = 24 - clamped;
            if (dayInput) dayInput.value = d;
            validMsg?.classList.add('hidden');
            _applyDurationChange(d);
        });
    }

    // =========================================================
    // FETCH EVENTS
    // =========================================================

    async function fetchAllEvents() {
        try {
            const response = await fetch('/events/simulation', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache',
                },
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.events) return;

            simulationReferenceDate = data.simulation_reference_date ?? null;
            const persisted  = loadSimulationState();
            const storedById = {};
            (persisted?.events ?? []).forEach(e => { storedById[e.id] = e; });
            const storedConfigs = persisted?.eventConfigs ?? {};

            const prevState = {};
            allEvents.forEach(e => {
                prevState[e.id] = {
                    activatedEarly: e.activatedEarly,
                    activatedForCycle: e.activatedForCycle,
                    cycleActivationStart: e.cycleActivationStart,
                };
            });

            allEvents = data.events.map(e => {
                const stored = storedById[e.id];
                const memory = prevState[e.id];
                const draft = {
                    id:                    e.id,
                    name:                  e.name,
                    type:                  e.type ?? 'one-off',
                    recurringSchedule:     e.recurring_schedule ?? null,
                    recurringStartDate:    e.recurring_start_date ?? null,
                    recurringEndDate:      e.recurring_end_date ?? null,
                    modifiers:             e.modifiers ?? {},
                    affected_categories:   e.affected_categories ?? [],
                    affectedFunctionIds:   e.affected_function_ids ?? [],
                    start_minutes:         e.start_minutes ?? datetimeToSimMinutes(e.start_at),
                    end_minutes:           e.end_minutes   ?? datetimeToSimMinutes(e.end_at),
                    durationMinutes:       e.duration_minutes ?? 0,
                    calendarDurationMinutes: e.calendar_duration_minutes ?? e.duration_minutes ?? 0,
                    fitsInCycle:           e.fits_in_cycle ?? true,
                    isActive:              false,
                };
                const configKey = buildEventConfigFingerprint(draft);
                const configChanged = storedConfigs[e.id] != null && storedConfigs[e.id] !== configKey;

                draft.activatedEarly = configChanged
                    ? false
                    : (stored?.activatedEarly ?? memory?.activatedEarly ?? false);
                draft.activatedForCycle = configChanged
                    ? false
                    : (stored?.activatedForCycle ?? memory?.activatedForCycle ?? false);
                draft.cycleActivationStart = configChanged
                    ? null
                    : (stored?.cycleActivationStart ?? memory?.cycleActivationStart ?? null);

                return draft;
            });

            if (persisted?.fullCycleMode != null) {
                setFullCycleMode(Boolean(persisted.fullCycleMode));
            } else {
                setFullCycleMode(false);
            }

            if (persisted?.currentTime != null) setCurrentTime(Number(persisted.currentTime));

            const simTime = getCurrentTime();
            allEvents.forEach(event => {
                if (hasEventEnded(event, simTime)) {
                    event.activatedEarly = false;
                    if (event.activatedForCycle) resetLongEventCycleActivation(event);
                }
            });

            setMaxTime();
            syncFullCycleToggleUI();
            syncTimelineUI();

            if (persisted?.isPlaying) { setIsPlaying(true); syncPlayPauseUI(); }

            registerActiveEventIdsProvider(getActiveEventIdsForQoL);

            lastActiveEventSignature = '';
            lastQoLEventIdsKey = null;
            applySimulationTime(getCurrentTime());
            updateQoL({ immediate: true });
            saveSimulationState();

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
                    function_id: draggedItem.id, force
                })
            });
            return res;
        } catch (err) { console.error("Fout bij opslaan gridcel:", err); return null; }
    }

    // =========================================================
    // DRAG & DROP
    // =========================================================

    document.querySelectorAll(".library-item").forEach(item => {
        item.addEventListener("dragstart", e => {
            isDragging = true; dropOccurred = false;
            draggedItem = { id: Number(item.dataset.functionId), name: item.dataset.functionName, image: item.dataset.image };
            e.dataTransfer.setDragImage(item.querySelector("img"), 16, 16);
        });
    });

    document.querySelectorAll(".grid-cell").forEach(cell => {
        // Ensure lock UI elements exist and apply initial locked state if present server-side
        ensureLockUi(cell);
        if (cell.classList.contains('is-locked')) {
            applyLockedCellState(cell);
        }
        cell.addEventListener("dragstart", e => {
            if (isLockedCell(cell)) {
                e.preventDefault();
                return;
            }
            const img = cell.querySelector(".grid-function-icon");
            if (!img) return;
            isDragging = true; dropOccurred = false;
            draggedItem = { id: Number(img.dataset.functionId), name: img.alt, image: img.src };
            e.dataTransfer.setDragImage(img, 16, 16);
            sourceCell = cell; cell.classList.add("drag-source");
        });

        cell.addEventListener("dragover",  e => { e.preventDefault(); cell.classList.add("drag-over"); });
        cell.addEventListener("dragleave", () => cell.classList.remove("drag-over"));

        cell.addEventListener("drop", async e => {
            e.preventDefault(); isDragging = false; dropOccurred = true;
            cell.classList.remove("drag-over");

            // Block drops onto locked cells
            if (isLockedCell(cell)) {
                isDragging = false;
                dropOccurred = true;
                showLockedPlacementMessage(cell);
                if (sourceCell) sourceCell.classList.remove('drag-source');
                return;
            }

            if (cell.querySelector("img") && !window.confirm("Are you sure you want to replace this feature?")) {
                if (sourceCell) sourceCell.classList.remove("drag-source");
                return;
            }

            const newRow = cell.dataset.row, newCol = cell.dataset.col;
            let oldRow = null, oldCol = null;
            const originalSourceCell = sourceCell;

            if (sourceCell) {
                oldRow = sourceCell.dataset.row; oldCol = sourceCell.dataset.col;
                sourceCell.innerHTML = ""; sourceCell.removeAttribute("draggable");
                sourceCell.classList.remove("drag-source");
            }

            sourceCell = null; cell.innerHTML = "";
            const img = document.createElement("img");
            img.src = draggedItem.image; img.alt = draggedItem.name;
            img.dataset.functionId = draggedItem.id;
            img.classList.add("grid-function-icon", "object-contain");
            cell.appendChild(img);

            const deleteBtn = document.createElement("button");
            deleteBtn.type = "button";
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

            if (res && res.status === 403) {
                const data = await res.json().catch(() => ({}));
                alert(data.message ?? "You can't modify this locked area.");
                location.reload();
                return;
            }

            if (res && res.status === 409) {
                if (window.confirm("Placement is forbidden by adjacency rules. Force placement anyway?")) {
                    const res2 = await saveMove(oldRow, oldCol, newRow, newCol, true);
                    if (!res2 || !res2.ok) {
                        if (originalSourceCell) {
                            originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                            originalSourceCell.setAttribute('draggable', 'true');
                        }
                        cell.innerHTML = ""; cell.removeAttribute('draggable'); updateQoL(); return;
                    }
                } else {
                    if (originalSourceCell) {
                        originalSourceCell.innerHTML = `<img src="${draggedItem.image}" alt="${draggedItem.name}" data-function-id="${draggedItem.id}" class="grid-function-icon object-contain"><button class="delete-btn absolute top-[2px] right-[2px] bg-red-600/80 text-white w-5 h-5 text-[14px] rounded cursor-pointer flex items-center justify-center">✖</button>`;
                        originalSourceCell.setAttribute('draggable', 'true');
                    }
                    cell.innerHTML = ""; cell.removeAttribute('draggable'); updateQoL(); return;
                }
            }

            updateCellHighlights();
            updateQoL();
        });

        cell.addEventListener("click",   (e) => { 
            if (isDragging) return;
            if (isLockedCell(cell) && !canSelectLockedCell(cell)) {
                e.preventDefault();
                flashLockExplanation(cell);
                return;
            }

            const approveBtnExists = Boolean(document.getElementById('approve-btn'));

            // If approve button is present, allow multi-select via toggling.
            // Also allow ctrl/meta to add to selection when approve button is not visible.
            const additive = approveBtnExists || e.ctrlKey || e.metaKey;

            if (approveBtnExists) {
                // toggle selection for multi-select UX
                if (cell.classList.contains('selected')) cell.classList.remove('selected');
                else activateCell(cell, true);
            } else if (additive) {
                // additive selection without approve button (ctrl/meta pressed)
                if (cell.classList.contains('selected')) cell.classList.remove('selected');
                else cell.classList.add('selected');
            } else {
                activateCell(cell, false);
            }
        });
        cell.addEventListener("keydown", e  => { if (!isDragging && (e.key === "Enter" || e.key === " ")) activateCell(cell); });
        cell.addEventListener('mouseenter', (event) => {
            const row = parseInt(cell.dataset.row), col = parseInt(cell.dataset.col);
            clearTimeout(hoverTimer);
            hoverTimer = setTimeout(() => handleTileHover(row, col, event), HOVER_DELAY_MS);
        });
        cell.addEventListener('mouseleave', () => { clearTimeout(hoverTimer); hidePopup(); });
    });

    // --- APPROVE / LOCK LOGIC ---
    const approveBtn = document.getElementById("approve-btn");
    if (approveBtn) {
        approveBtn.addEventListener("click", async () => {
            // Find all currently selected cells
            const selectedCells = document.querySelectorAll(".grid-cell.selected");
            if (selectedCells.length === 0) {
                alert("Please select at least one grid cell first to lock or unlock it.");
                return;
            }

            const cellsPayload = Array.from(selectedCells).map(cell => ({ row: cell.dataset.row, col: cell.dataset.col }));

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
                    // Clear selections
                    document.querySelectorAll('.grid-cell.selected').forEach(c => c.classList.remove('selected'));
                    alert('Selected areas successfully updated!');
                }
            } catch (err) {
                console.error('Error during cell approval:', err);
            }
        });
    }

    // =========================================================
    // DELETE BUTTON
    // =========================================================

    document.addEventListener("click", async (e) => {
        if (!e.target.classList.contains("delete-btn")) return;
        const cell = e.target.closest(".grid-cell");
        if (!cell) return;
        if (isLockedCell(cell)) {
            // Do not allow deletion on locked cells
            return;
        }
        const row = cell.dataset.row;
        const col = cell.dataset.col;
        cell.innerHTML = "";
        cell.removeAttribute("draggable");
        // WCAG 4.1.2: aria-label bijwerken naar lege cel na verwijdering
        cell.setAttribute('aria-label', `Empty cell ${row},${col}. Press Enter to select.`);
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
        sourceCell.innerHTML = ""; sourceCell.removeAttribute("draggable"); activateCell(sourceCell);
        try {
            await fetch(`/grid/cell/${sourceCell.dataset.id}/function`, {
                method: "DELETE",
                headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
            });
        } catch (err) { console.error("Fout bij drag-off delete:", err); }
        draggedItem = null; sourceCell = null;
        updateCellHighlights(); setTimeout(() => updateQoL(), 10);
    });

    // =========================================================
    // UNDO
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
            lastAction = null; undoBtn.disabled = true; updateQoL(); location.reload();
        });
    }

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
                    } else { targetCell.innerHTML = ""; targetCell.removeAttribute("draggable"); }
                    activateCell(targetCell);
                }
                if (data.cleared) {
                    const clearedCell = document.querySelector(`[data-row="${data.cleared.row}"][data-col="${data.cleared.col}"]`);
                    if (clearedCell) { clearedCell.innerHTML = ""; clearedCell.removeAttribute("draggable"); clearedCell.classList.remove("selected"); }
                }
                updateCellHighlights(); setTimeout(() => updateQoL(), 50);
            });
        });
    }

    // =========================================================
    // INIT
    // =========================================================

    resetDayNightIndicatorState();
    setFullCycleMode(false);
    syncFullCycleToggleUI();
    syncTimelineUI();

    fetchAllEvents();
    requestAnimationFrame(simulationLoop);
    window.addEventListener('beforeunload', saveSimulationState);

    const refreshEventsFromServer = () => {
        lastActiveEventSignature = '';
        lastQoLEventIdsKey = null;
        fetchAllEvents();
    };

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            refreshEventsFromServer();
        }
    });
}

document.addEventListener('DOMContentLoaded', initGridPage);
document.addEventListener('turbo:load', initGridPage);
document.addEventListener('turbo:render', initGridPage);

