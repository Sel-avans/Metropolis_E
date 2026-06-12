const MAIN_ACCESS_ROAD_NAME = 'Road';
const SET_START_LABEL = 'Set start point';
const CHANGE_START_LABEL = 'Change starting point';

const ROUTE_STATUS = {
    selectEvent: 'Choose an event from the list above to plan a visitor route.',
    noStartPoint: `No start point yet. Press ${SET_START_LABEL}, then select a Road cell on the City Grid.`,
    selectRoadCell: 'Start point selection is on. Select a Road cell on the City Grid below — click the cell or focus it and press Enter.',
    selectChangeRoadCell: 'Starting point selection is on. Select a new Road cell on the City Grid below — click the cell or focus it and press Enter.',
    invalidCell: 'This cell cannot be the start point. Select a cell that contains a Road function.',
    startPointSet: (row, col) => `Start point saved on the City Grid at row ${row}, column ${col}.`,
    startPointChanged: (row, col) => `Starting point changed to row ${row}, column ${col} on the City Grid.`,
    startPointRemoved: `Start point removed. Press ${SET_START_LABEL} and select a Road cell to choose a new one.`,
    saving: 'Saving start point…',
    removing: 'Removing start point…',
    saveFailed: 'Could not save the start point. Please try again.',
    removeFailed: 'Could not remove the start point. Please try again.',
};

let routePlannerEnabled = false;
let startPointMode = false;
let selectedEventId = null;
let eventRoutes = [];
let roadFunctionIds = [];
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

function isRoadFunctionName(name) {
    return Boolean(name) && name.trim().toLowerCase() === MAIN_ACCESS_ROAD_NAME.toLowerCase();
}

function isMainAccessRoadCell(cell) {
    const icon = cell.querySelector('.grid-function-icon');
    if (!icon) return false;

    const name = getCellFunctionName(cell);
    if (isRoadFunctionName(name)) {
        return true;
    }

    const functionId = Number(icon.dataset.functionId);
    return !Number.isNaN(functionId) && roadFunctionIds.includes(functionId);
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

    const route = getSelectedEventRoute();
    if (!route) return;

    const cell = document.querySelector(
        `.grid-cell[data-row="${route.start_row}"][data-col="${route.start_col}"]`
    );
    if (cell) {
        applyStartPointMarker(cell);
    }
}

function getSelectedEventRoute() {
    if (!selectedEventId) return null;
    return eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId)) ?? null;
}

function updateStartPointButtonState() {
    const btn = document.getElementById('route-set-start-btn');
    if (!btn) return;

    const hasEvent = Boolean(selectedEventId);
    const hasStartPoint = Boolean(getSelectedEventRoute());
    btn.disabled = !hasEvent;
    btn.textContent = hasStartPoint ? CHANGE_START_LABEL : SET_START_LABEL;
    btn.setAttribute('aria-pressed', startPointMode ? 'true' : 'false');
    btn.setAttribute(
        'aria-label',
        startPointMode
            ? (hasStartPoint
                ? 'Cancel changing starting point'
                : 'Cancel start point selection')
            : (hasStartPoint
                ? 'Change starting point on a Road cell in the City Grid'
                : 'Set start point on a Road cell in the City Grid')
    );
    btn.classList.toggle('ring-2', startPointMode);
    btn.classList.toggle('ring-emerald-400', startPointMode);
}

function updateRemoveButtonState() {
    const btn = document.getElementById('route-remove-start-btn');
    if (!btn) return;

    btn.disabled = !getSelectedEventRoute();
}

function updateStatusForSelectedEvent() {
    if (!selectedEventId) {
        setStatus(ROUTE_STATUS.selectEvent);
        return;
    }

    const route = getSelectedEventRoute();
    if (route) {
        if (startPointMode) {
            setStatus(ROUTE_STATUS.selectChangeRoadCell);
            return;
        }
        setStatus(ROUTE_STATUS.startPointSet(route.start_row, route.start_col), 'success');
        return;
    }

    if (startPointMode) {
        setStatus(ROUTE_STATUS.selectRoadCell);
        return;
    }

    setStatus(ROUTE_STATUS.noStartPoint);
}

function populateEventSelect(events) {
    const select = document.getElementById('route-event-select');
    if (!select) return;

    const currentValue = select.value;
    select.innerHTML = '<option value="">— Select —</option>';

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
        roadFunctionIds = data.road_function_ids
            ?? (data.road_function_id != null ? [data.road_function_id] : []);
        roadFunctionIds = roadFunctionIds.map((id) => Number(id)).filter((id) => !Number.isNaN(id));
        renderStartPointsOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
    } catch (error) {
        if (generation !== routesFetchGeneration) return;
        console.error('Failed to load event routes:', error);
    }
}

async function saveStartPoint(row, col) {
    if (!selectedEventId) return false;

    const hadStartPoint = Boolean(getSelectedEventRoute());

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
            setStatus(data.message || ROUTE_STATUS.invalidCell, 'error');
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
            hadStartPoint
                ? ROUTE_STATUS.startPointChanged(data.route.start_row, data.route.start_col)
                : ROUTE_STATUS.startPointSet(data.route.start_row, data.route.start_col),
            'success'
        );
        return true;
    } catch (error) {
        console.error('Failed to save start point:', error);
        setStatus(ROUTE_STATUS.saveFailed, 'error');
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
    updateStartPointButtonState();
    updateRemoveButtonState();
    setStatus(ROUTE_STATUS.removing);

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
            setStatus(ROUTE_STATUS.removeFailed, 'error');
            return;
        }

        renderStartPointsOnGrid();
        updateStartPointButtonState();
        setStatus(ROUTE_STATUS.startPointRemoved);
    } catch (error) {
        console.error('Failed to remove start point:', error);
        await fetchEventRoutes();
        setStatus(ROUTE_STATUS.removeFailed, 'error');
    }
}

export function handleRouteCellClick(cell) {
    if (!routePlannerEnabled || !startPointMode || !selectedEventId) {
        return false;
    }

    if (!isMainAccessRoadCell(cell)) {
        setStatus(ROUTE_STATUS.invalidCell, 'error');
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
    renderStartPointsOnGrid();
    updateStartPointButtonState();
    updateRemoveButtonState();
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
        renderStartPointsOnGrid();
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
    setStatus(ROUTE_STATUS.selectEvent);
}
