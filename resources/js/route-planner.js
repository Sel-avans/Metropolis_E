const MAIN_ACCESS_ROAD_NAME = 'Road';

let routePlannerEnabled = false;
let startPointMode = false;
let selectedEventId = null;
let eventRoutes = [];
let roadFunctionId = null;
let routesFetchGeneration = 0;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function getRoutePanel() {
    return document.getElementById('route-planner-panel');
}

export function isRoutePlannerEnabled() {
    return routePlannerEnabled;
}

export function isStartPointModeActive() {
    return startPointMode;
}

function setStatus(message, type = 'info') {
    const statusEl = document.getElementById('route-planner-status');
    if (!statusEl) return;

    statusEl.textContent = message;
    statusEl.dataset.statusType = type;
    statusEl.classList.remove('text-red-400');
    statusEl.classList.add('text-white');
}

function getCellFunctionName(cell) {
    const icon = cell.querySelector('.grid-function-icon');
    if (!icon) return null;
    return icon.dataset.functionName || icon.alt || null;
}

function isMainAccessRoadCell(cell) {
    const icon = cell.querySelector('.grid-function-icon');
    if (!icon) return false;

    if (roadFunctionId && icon.dataset.functionId) {
        return Number(icon.dataset.functionId) === Number(roadFunctionId);
    }

    const name = getCellFunctionName(cell);
    return name && name.toLowerCase() === MAIN_ACCESS_ROAD_NAME.toLowerCase();
}

function clearStartPointMarkers() {
    document.querySelectorAll('.route-start-marker').forEach((marker) => marker.remove());
    document.querySelectorAll('.grid-cell.route-start-point').forEach((cell) => {
        cell.classList.remove('route-start-point');
    });
}

function clearStartPointForCell(row, col) {
    const stillUsed = eventRoutes.some(
        (r) => Number(r.start_row) === Number(row) && Number(r.start_col) === Number(col)
    );
    if (stillUsed) return;

    const cell = document.querySelector(
        `.grid-cell[data-row="${row}"][data-col="${col}"]`
    );
    if (!cell) return;

    cell.classList.remove('route-start-point');
    cell.querySelector('.route-start-marker')?.remove();
}

function invalidateRoutesFetch() {
    routesFetchGeneration += 1;
}

function applyStartPointMarker(cell) {
    cell.classList.add('route-start-point');

    let marker = cell.querySelector('.route-start-marker');
    if (!marker) {
        marker = document.createElement('div');
        marker.className = 'route-start-marker absolute z-20 bottom-1 left-1 bg-emerald-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow uppercase tracking-wide';
        marker.setAttribute('aria-hidden', 'true');
        marker.textContent = 'Start';
        cell.appendChild(marker);
    }
}

function renderStartPointsOnGrid() {
    clearStartPointMarkers();

    eventRoutes.forEach((route) => {
        const cell = document.querySelector(
            `.grid-cell[data-row="${route.start_row}"][data-col="${route.start_col}"]`
        );
        if (cell) {
            applyStartPointMarker(cell);
        }
    });
}

function updateStartPointButtonState() {
    const btn = document.getElementById('route-set-start-btn');
    if (!btn) return;

    const hasEvent = Boolean(selectedEventId);
    btn.disabled = !hasEvent;
    btn.setAttribute('aria-pressed', startPointMode ? 'true' : 'false');
    btn.classList.toggle('ring-2', startPointMode);
    btn.classList.toggle('ring-emerald-400', startPointMode);
}

function updateRemoveButtonState() {
    const btn = document.getElementById('route-remove-start-btn');
    if (!btn) return;

    const route = eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId));
    btn.disabled = !route;
}

function updateStatusForSelectedEvent() {
    if (!selectedEventId) {
        setStatus('Select an event to plan a visitor route.');
        return;
    }

    const route = eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId));
    if (route) {
        setStatus(
            `Start point set at row ${route.start_row}, column ${route.start_col}.`,
            'success'
        );
        return;
    }

    if (startPointMode) {
        setStatus('Click a main access road (Road) on the grid to set the start point.');
        return;
    }

    setStatus('No start point yet. Use "Set start point" and click a Road cell.');
}

function populateEventSelect(events) {
    const select = document.getElementById('route-event-select');
    if (!select) return;

    const currentValue = select.value;
    select.innerHTML = '<option value="">— Select event —</option>';

    events.forEach((event) => {
        const option = document.createElement('option');
        option.value = String(event.id);
        option.textContent = event.name || `Event ${event.id}`;
        select.appendChild(option);
    });

    if (currentValue && events.some((e) => String(e.id) === currentValue)) {
        select.value = currentValue;
        selectedEventId = Number(currentValue);
    }
}

async function fetchEventRoutes() {
    const generation = ++routesFetchGeneration;

    try {
        const response = await fetch('/event-routes', {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;

        const data = await response.json();
        if (generation !== routesFetchGeneration) return;

        eventRoutes = data.routes ?? [];
        roadFunctionId = data.road_function_id ?? null;
        renderStartPointsOnGrid();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
    } catch (error) {
        if (generation !== routesFetchGeneration) return;
        console.error('Failed to load event routes:', error);
    }
}

async function saveStartPoint(row, col) {
    if (!selectedEventId) return false;

    try {
        const response = await fetch('/event-routes/start-point', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                event_id: selectedEventId,
                row,
                col,
            }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            setStatus(data.message || 'Could not set the start point.', 'error');
            return false;
        }

        const existingIndex = eventRoutes.findIndex(
            (r) => Number(r.event_id) === Number(selectedEventId)
        );
        if (existingIndex >= 0) {
            eventRoutes[existingIndex] = data.route;
        } else {
            eventRoutes.push(data.route);
        }

        startPointMode = false;
        updateStartPointButtonState();
        renderStartPointsOnGrid();
        updateRemoveButtonState();
        setStatus(
            `Start point saved at row ${data.route.start_row}, column ${data.route.start_col}.`,
            'success'
        );
        return true;
    } catch (error) {
        console.error('Failed to save start point:', error);
        setStatus('Could not save the start point. Please try again.', 'error');
        return false;
    }
}

async function removeStartPoint() {
    if (!selectedEventId) return;

    const route = eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId));
    if (!route) return;

    const { start_row: removedRow, start_col: removedCol } = route;
    invalidateRoutesFetch();

    eventRoutes = eventRoutes.filter((r) => Number(r.event_id) !== Number(selectedEventId));
    clearStartPointForCell(removedRow, removedCol);
    updateRemoveButtonState();
    setStatus('Removing start point...');

    try {
        const response = await fetch(`/event-routes/${selectedEventId}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success || !data.deleted) {
            await fetchEventRoutes();
            setStatus('Could not remove the start point.', 'error');
            return;
        }

        renderStartPointsOnGrid();
        setStatus('Start point removed. Select a Road cell to set a new one.');
    } catch (error) {
        console.error('Failed to remove start point:', error);
        await fetchEventRoutes();
        setStatus('Could not remove the start point.', 'error');
    }
}

export function handleRouteCellClick(cell) {
    if (!routePlannerEnabled || !startPointMode || !selectedEventId) {
        return false;
    }

    if (!isMainAccessRoadCell(cell)) {
        setStatus('The start point must be a main access road (Road function).', 'error');
        return true;
    }

    const row = Number(cell.dataset.row);
    const col = Number(cell.dataset.col);
    saveStartPoint(row, col);
    return true;
}

export function syncRoutePlannerEvents(events) {
    if (!routePlannerEnabled) return;
    populateEventSelect(events);
    updateStartPointButtonState();
    updateStatusForSelectedEvent();
}

export function initRoutePlanner() {
    const panel = getRoutePanel();
    if (!panel || panel.dataset.initialized === 'true') return;

    panel.dataset.initialized = 'true';
    routePlannerEnabled = true;

    const eventSelect = document.getElementById('route-event-select');
    const setStartBtn = document.getElementById('route-set-start-btn');
    const removeStartBtn = document.getElementById('route-remove-start-btn');

    eventSelect?.addEventListener('change', () => {
        selectedEventId = eventSelect.value ? Number(eventSelect.value) : null;
        startPointMode = false;
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
    });

    setStartBtn?.addEventListener('click', () => {
        if (!selectedEventId) return;
        startPointMode = !startPointMode;
        updateStartPointButtonState();
        updateStatusForSelectedEvent();
    });

    removeStartBtn?.addEventListener('click', () => {
        removeStartPoint();
    });

    fetchEventRoutes();
    setStatus('Select an event to plan a visitor route.');
}
