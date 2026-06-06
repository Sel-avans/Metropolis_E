import { getNeighborsWithQoL } from './neighbours.js';
import { simulationLoop, onSimulationTimeUpdate } from './simulation.js';
import { setMaxTime, syncTimelineUI, syncPlayPauseUI, minutesToHHMM, datetimeToSimMinutes, getCurrentTime, getMaxTime, getIsPlaying, setCurrentTime, setIsPlaying } from './regulation.js';

const SIM_STATE_KEY = 'metropolis_simulation_state';

function initGridPage() {
    if (!document.querySelector('.city-grid')) {
        return;
    }

    const gridRoot = document.querySelector('.city-grid');
    if (gridRoot.dataset.gridInitialized === 'true') {
        return;
    }
    gridRoot.dataset.gridInitialized = 'true';

    // =========================================================
    // VARIABELEN
    // =========================================================

    let draggedItem = null;
    let isDragging  = false;
    let sourceCell  = null;
    let dropOccurred = false;
    let old_score;
    let lastAction = null;

    // activatedEarly = cycle-event vroegtijdig geactiveerd
    // activatedForCycle = lang event handmatig actief voor rest van simulatiedag
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

    function loadSimulationState() {
        try {
            const raw = sessionStorage.getItem(SIM_STATE_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch {
            return null;
        }
    }

    function saveSimulationState() {
        if (!allEvents.length) {
            return;
        }

        sessionStorage.setItem(SIM_STATE_KEY, JSON.stringify({
            currentTime: getCurrentTime(),
            isPlaying: getIsPlaying(),
            events: allEvents.map(e => ({
                id: e.id,
                activatedEarly: Boolean(e.activatedEarly),
                activatedForCycle: Boolean(e.activatedForCycle),
                cycleActivationStart: e.cycleActivationStart ?? null,
            })),
        }));
    }

    function scheduleSaveSimulationState() {
        if (saveStateTimer) {
            clearTimeout(saveStateTimer);
        }
        saveStateTimer = setTimeout(saveSimulationState, 300);
    }

    function clearSimulationState() {
        sessionStorage.removeItem(SIM_STATE_KEY);
    }

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

    function matchesRecurringSchedule(event, referenceDate) {
        if (event.type !== 'recurring') {
            return true;
        }

        // Simulation runs one 24h cycle — match recurring events by time window only.
        if (event.recurringStartDate && referenceDate && referenceDate < event.recurringStartDate) {
            return false;
        }
        if (event.recurringEndDate && referenceDate && referenceDate > event.recurringEndDate) {
            return false;
        }

        return true;
    }

    function isScheduledEventActive(event, simTime) {
        if (event.type === 'recurring' && !matchesRecurringSchedule(event, simulationReferenceDate)) {
            return false;
        }

        return isEventActiveAtSimTime(event, simTime);
    }

    function isEventContributingToQoL(event, simTime) {
        if (isSimulationAtDayStart(simTime)) {
            return false;
        }

        if (!event.fitsInCycle) {
            if (!event.activatedForCycle) {
                return false;
            }

            const activationStart = Number(event.cycleActivationStart);
            if (Number.isNaN(activationStart) || simTime < activationStart || simTime >= getMaxTime()) {
                return false;
            }

            return true;
        }

        if (hasEventEnded(event, simTime)) {
            return false;
        }

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

    function buildQoLQueryString() {
        return `?active_event_ids=${getActiveEventIdsForQoL().join(',')}`;
    }

    function isEventActiveAtSimTime(event, simTime) {
        const start = Number(event.start_minutes);
        const end = Number(event.end_minutes);

        if (Number.isNaN(start) || Number.isNaN(end)) {
            return false;
        }

        // Simulation timeline runs 06:00 → 06:00 (0–1440). Events that cross midnight
        // only run from their start time until the end of this cycle.
        if (end > 1440) {
            return simTime >= start;
        }

        return simTime >= start && simTime <= end;
    }

    function isBeforeEventStart(event, simTime) {
        return simTime < Number(event.start_minutes);
    }

    function hasEventEnded(event, simTime) {
        const end = Number(event.end_minutes);
        if (Number.isNaN(end)) {
            return false;
        }
        if (end > 1440) {
            return false;
        }
        return simTime > end;
    }

    function syncEventActiveStates(simTime) {
        if (!allEvents.length) return false;

        let changed = false;

        allEvents.forEach(event => {
            if (!event.fitsInCycle) {
                if (!event.activatedForCycle) {
                    if (event.isActive) {
                        event.isActive = false;
                        changed = true;
                    }
                    return;
                }

                const activationStart = Number(event.cycleActivationStart);
                const cycleEnd = getMaxTime();
                if (simTime >= cycleEnd || Number.isNaN(activationStart)) {
                    if (simTime >= cycleEnd) {
                        resetLongEventCycleActivation(event);
                        changed = true;
                    } else if (event.isActive) {
                        event.isActive = false;
                        changed = true;
                    }
                    return;
                }

                const shouldBeActive = isSimulationAtDayStart(simTime)
                    ? false
                    : simTime >= activationStart;
                if (event.isActive !== shouldBeActive) {
                    event.isActive = shouldBeActive;
                    changed = true;
                }
                return;
            }

            if (hasEventEnded(event, simTime)) {
                if (event.activatedEarly) {
                    event.activatedEarly = false;
                    changed = true;
                }
                if (event.isActive) {
                    event.isActive = false;
                    changed = true;
                }
                return;
            }

            const scheduledActive = isScheduledEventActive(event, simTime);
            const earlyActive = event.activatedEarly && isBeforeEventStart(event, simTime);
            const shouldBeActive = !isSimulationAtDayStart(simTime) && (scheduledActive || earlyActive);

            if (event.isActive !== shouldBeActive) {
                event.isActive = shouldBeActive;
                changed = true;
            }
        });

        return changed;
    }

    function toggleEventEarlyActivation(event, simTime) {
        if (hasEventEnded(event, simTime) || !isBeforeEventStart(event, simTime)) {
            return;
        }

        if (event.activatedEarly) {
            event.activatedEarly = false;
            event.isActive = false;
            return;
        }

        event.activatedEarly = true;
    }

    function toggleLongEventCycleActivation(event, simTime) {
        if (event.fitsInCycle || simTime >= getMaxTime()) {
            return;
        }

        if (event.activatedForCycle) {
            resetLongEventCycleActivation(event);
            return;
        }

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
        updateQoL({ immediate: true });
        scheduleSaveSimulationState();
    }

    function applySimulationTime(simTime) {
        if (!allEvents.length) return;

        syncEventActiveStates(simTime);

        const signature = buildEventSignature(allEvents, simTime);
        const idsKey = buildQoLActiveIdsKey(simTime);
        const uiChanged = signature !== lastActiveEventSignature;
        const qolChanged = lastQoLEventIdsKey === null || idsKey !== lastQoLEventIdsKey;

        if (!uiChanged && !qolChanged) {
            return;
        }

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

        const categories = data?.categories ?? {};
        const categoryKeys = {
            Safety: 'safety', Recreation: 'recreation', Environment: 'environment',
            Amenities: 'amenities', Mobility: 'mobility',
        };
        const eventModifiers = data?.event_modifiers ?? {};
        const entries = Object.entries(categories);

        if (!entries.length) {
            listEl.innerHTML = '<li class="text-slate-400 text-sm">No active QoL influences on this cell</li>';
            return;
        }

        let html = '';
        let hasInfluence = false;

        for (const [categoryName, info] of entries) {
            const cellScore  = Number(info?.total ?? 0);
            const eventScore = Number(eventModifiers[categoryKeys[categoryName]] ?? 0);
            const displayScore = cellScore + eventScore;

            if (displayScore === 0 && eventScore === 0 && cellScore === 0) {
                continue;
            }

            hasInfluence = true;
            const cls  = displayScore > 0 ? 'text-green-600' : displayScore < 0 ? 'text-red-600' : 'text-slate-400';
            const sign = displayScore > 0 ? '+' : '';

            html += `<div class="mb-2 last:mb-0 w-full">
                <div class="flex justify-between items-center gap-8">
                    <span class="text-white font-medium text-sm">${categoryName}</span>
                    <span class="${cls} font-bold text-sm">${sign}${displayScore}</span>
                </div>`;

            if (cellScore !== 0) {
                const cellCls = cellScore > 0 ? 'text-green-500' : 'text-red-500';
                const cellSign = cellScore > 0 ? '+' : '';
                html += `<div class="text-[10px] text-slate-400 mt-0.5">Base: <span class="${cellCls}">${cellSign}${cellScore}</span></div>`;
            }

            if (eventScore !== 0) {
                const eventCls = eventScore > 0 ? 'text-amber-400' : 'text-red-400';
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
        const data = await getNeighborsWithQoL(row, col, getActiveEventIdsForQoL());
        renderNeighborsList(data);
        showPopup();
    }

    // =========================================================
    // QOL UPDATE
    // =========================================================

    async function updateQoL({ immediate = false } = {}) {
        if (qolUpdateTimer) {
            clearTimeout(qolUpdateTimer);
            qolUpdateTimer = null;
        }

        const executeFetch = async () => {
            qolFetchGeneration += 1;
            const generation = qolFetchGeneration;
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
                if (!response.ok || generation !== qolFetchGeneration) {
                    return;
                }

                const data = await response.json();
                if (generation !== qolFetchGeneration) {
                    return;
                }

                if (scoreEl) scoreEl.textContent = data.total_score;
                if (oldScoreEl) oldScoreEl.innerHTML = compareScores(data);
                if (breakdownEl) breakdownEl.innerHTML = renderQoLBreakdown(data);
            } catch (err) {
                console.error("Fout bij ophalen QoL:", err);
            } finally {
                qolFetchInFlight = false;
                if (qolFetchPending) {
                    qolFetchPending = false;
                    executeFetch();
                }
            }
        };

        const scheduleFetch = () => {
            if (qolFetchInFlight) {
                qolFetchPending = true;
                return;
            }
            return executeFetch();
        };

        if (immediate) {
            await scheduleFetch();
            return;
        }

        qolUpdateTimer = setTimeout(() => { scheduleFetch(); }, 250);
    }

    // =========================================================
    // HIGHLIGHT LOGICA
    // Cellen die beïnvloed worden door een actief event krijgen een gele rand.
    // Events met specifieke city_functions → alleen die functies highlighten.
    // Events zonder functie-target → fallback op categorie-match.
    // =========================================================

    function updateCellHighlights() {
        const activeEvents = allEvents.filter(e => e.isActive);

        const activeCategories = new Set();
        const activeFunctionIds = new Set();

        activeEvents.forEach(event => {
            const functionIds = (event.affectedFunctionIds || [])
                .map(id => Number(id))
                .filter(id => !Number.isNaN(id) && id > 0);

            if (functionIds.length > 0) {
                functionIds.forEach(id => activeFunctionIds.add(id));
                return;
            }

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

            const functionId = Number(functionImg.dataset.functionId);
            const cellCategories = (functionImg.dataset.categories || '').toLowerCase().split(',').filter(Boolean);
            const isAffected = activeFunctionIds.has(functionId)
                || cellCategories.some(cat => activeCategories.has(cat));

            cell.classList.toggle('event-highlight', isAffected);
        });
    }

    // =========================================================
    // SIMULATIE TICK — events aan/uit op basis van simulatietijd
    // =========================================================

    function onSimulationTick(simTime) {
        applySimulationTime(simTime);
    }

    onSimulationTimeUpdate(onSimulationTick);

    // =========================================================
    // EVENTS PANEL
    // Cycle-events: auto op starttijd; Activate = vroegtijdig.
    // Lang events (>24u): alleen handmatig via Activate in All Events.
    // =========================================================

    function renderToggleButton(event, { panel, simTime, showDeactivate }) {
        return `
                <button
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
        const simTime = getCurrentTime();
        const isActive = event.isActive;
        const isWaiting = event.activatedEarly && isBeforeEventStart(event, simTime);
        const isLongEvent = !event.fitsInCycle;
        const startLabel = minutesToHHMM(event.start_minutes);
        const endLabel = minutesToHHMM(event.end_minutes);

        let toggleButton = '';
        if (isLongEvent && panel === 'active' && event.activatedForCycle) {
            toggleButton = renderToggleButton(event, { panel, simTime, showDeactivate: true });
        } else if (isLongEvent && panel === 'all') {
            toggleButton = renderToggleButton(event, { panel, simTime, showDeactivate: false });
        } else if (!isLongEvent) {
            const canToggleEarly = isBeforeEventStart(event, simTime) && !hasEventEnded(event, simTime);
            const showDeactivate = event.activatedEarly && isBeforeEventStart(event, simTime);
            if (canToggleEarly) {
                toggleButton = renderToggleButton(event, { panel, simTime, showDeactivate });
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
                    ${isWaiting && isActive ? '<div class="text-[10px] text-sky-400 mt-1 font-semibold uppercase tracking-wide">Started by triggering activation</div>' : ''}
                    ${isWaiting && !isActive ? '<div class="text-[10px] text-sky-400 mt-1 font-semibold uppercase tracking-wide">Starts at ' + startLabel + '</div>' : ''}
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

        events.forEach(event => {
            listEl.appendChild(renderEventListItem(event, { panel: 'all' }));
        });
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

        events.forEach(event => {
            listEl.appendChild(renderEventListItem(event, { panel: 'active' }));
        });
    }

    // Toggle handler via event delegation (werkt voor beide panels)
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

    // Timeline scrub / skip: update highlights even when not playing
    const simulationTimeline = document.getElementById('simulation-timeline');
    if (simulationTimeline) {
        simulationTimeline.addEventListener('input', (e) => {
            applySimulationTime(parseInt(e.target.value, 10));
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
            if (event.activatedForCycle) {
                resetLongEventCycleActivation(event);
            }
        });
        clearSimulationState();
        lastQoLEventIdsKey = null;
        applySimulationTime(getCurrentTime());
        updateQoL({ immediate: true });
    });

    // =========================================================
    // FETCH EVENTS VAN SERVER
    // Verwacht van de server per event:
    //   id, name, modifiers (object), start_at (datetime), end_at (datetime)
    // =========================================================

    async function fetchAllEvents() {
        try {
            const response = await fetch('/events/simulation', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) return;

            const data = await response.json();
            if (!data || !data.events) return;

            simulationReferenceDate = data.simulation_reference_date ?? null;
            const persisted = loadSimulationState();
            const storedById = {};
            (persisted?.events ?? []).forEach(e => {
                storedById[e.id] = e;
            });

            // Bewaar toggle-state per event (in-memory + sessionStorage)
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

                return {
                id:              e.id,
                name:            e.name,
                type:            e.type ?? 'one-off',
                recurringSchedule: e.recurring_schedule ?? null,
                recurringStartDate: e.recurring_start_date ?? null,
                recurringEndDate: e.recurring_end_date ?? null,
                modifiers:       e.modifiers ?? {},
                affected_categories: e.affected_categories ?? [],
                affectedFunctionIds: e.affected_function_ids ?? [],
                start_minutes:   e.start_minutes ?? datetimeToSimMinutes(e.start_at),
                end_minutes:     e.end_minutes   ?? datetimeToSimMinutes(e.end_at),
                durationMinutes: e.duration_minutes ?? Math.max(0, (e.end_minutes ?? 0) - (e.start_minutes ?? 0)),
                calendarDurationMinutes: e.calendar_duration_minutes ?? e.duration_minutes ?? 0,
                fitsInCycle:     e.fits_in_cycle ?? ((e.calendar_duration_minutes ?? e.duration_minutes ?? 1440) <= 1440),
                activatedEarly:  stored?.activatedEarly ?? memory?.activatedEarly ?? false,
                activatedForCycle: stored?.activatedForCycle ?? memory?.activatedForCycle ?? false,
                cycleActivationStart: stored?.cycleActivationStart ?? memory?.cycleActivationStart ?? null,
                isActive:        false,
            };
            });

            if (persisted?.currentTime != null) {
                setCurrentTime(Number(persisted.currentTime));
            }

            const simTime = getCurrentTime();
            allEvents.forEach(event => {
                if (hasEventEnded(event, simTime)) {
                    event.activatedEarly = false;
                    if (event.activatedForCycle) {
                        resetLongEventCycleActivation(event);
                    }
                }
            });

            setMaxTime();
            syncTimelineUI();

            if (persisted?.isPlaying) {
                setIsPlaying(true);
                syncPlayPauseUI();
            }

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
    requestAnimationFrame(simulationLoop);
    window.addEventListener('beforeunload', saveSimulationState);
}

document.addEventListener('DOMContentLoaded', initGridPage);
document.addEventListener('turbo:load', initGridPage);
document.addEventListener('turbo:render', initGridPage);