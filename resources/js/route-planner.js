const MAIN_ACCESS_ROAD_NAME = 'Road';
const SET_START_LABEL = 'Set start point';
const CHANGE_START_LABEL = 'Change starting point';
const SET_END_LABEL = 'Set end point';
const CHANGE_END_LABEL = 'Change end point';

const ROUTE_STATUS = {
    selectEvent: 'Choose an event from the list above to plan a visitor route.',
    noStartPoint: `No start point yet. Press ${SET_START_LABEL}, then select a Road cell on the City Grid.`,
    selectRoadCell: 'Start point selection is on. Select a Road cell on the City Grid below — click the cell or focus it and press Enter.',
    changeStartPoint(row, col) {
        return {
            visual: 'Change starting point mode is on. Select a new Road cell on the City Grid.',
            announce: `Change starting point mode is on. The current start point is at row ${row}, column ${col}. Select a new Road cell on the City Grid.`,
        };
    },
    selectChangeRoadCell: 'Starting point selection is on. Select a new Road cell on the City Grid below — click the cell or focus it and press Enter.',
    invalidCell: 'This cell cannot be the start point. Select a cell that contains a Road function.',
    startPointSet(row, col) {
        return {
            visual: 'Start point saved on the City Grid.',
            announce: `Start point saved on the City Grid at row ${row}, column ${col}.`,
        };
    },
    startPointAutoSet(row, col) {
        return {
            visual: 'Start point set automatically on the City Grid.',
            announce: `Start point set automatically to row ${row}, column ${col} on the City Grid.`,
        };
    },
    startPointChanged(row, col) {
        return {
            visual: 'Starting point changed on the City Grid.',
            announce: `Starting point changed to row ${row}, column ${col} on the City Grid.`,
        };
    },
    startPointRemoved: `Start point removed. Press ${SET_START_LABEL} and select a Road cell to choose a new one.`,
    placeRoadOnGrid: `Place ${MAIN_ACCESS_ROAD_NAME} on the City Grid to set the start point.`,
    placeRoadOnGridToChange(row, col) {
        return {
            visual: `Place ${MAIN_ACCESS_ROAD_NAME} on the City Grid to change the starting point. Do not place Road on the current start point or end point cells.`,
            announce: `Place ${MAIN_ACCESS_ROAD_NAME} on the City Grid to change the starting point. The current start point is at row ${row}, column ${col}. Do not place Road on the current start point or end point cells.`,
        };
    },
    placeFunctionOnGrid: (name) => `Place ${name} on the City Grid to set the end point.`,
    chooseEndpointCell(name, options) {
        const locations = options
            .map((option) => `row ${option.row}, column ${option.col}`)
            .join('; ');

        return {
            visual: `Choose which ${name} cell should be the end point on the City Grid.`,
            announce: `Choose which ${name} cell should be the end point: ${locations}.`,
        };
    },
    endpointSet(name, row, col) {
        return {
            visual: 'End point set on the City Grid.',
            announce: `End point set to ${name} at row ${row}, column ${col}.`,
        };
    },
    endpointAutoSet(name, row, col) {
        return {
            visual: 'End point set automatically on the City Grid.',
            announce: `End point set automatically to ${name} at row ${row}, column ${col}.`,
        };
    },
    endpointChanged(name, row, col) {
        return {
            visual: 'End point changed on the City Grid.',
            announce: `End point changed to ${name} at row ${row}, column ${col}.`,
        };
    },
    dragEndpointToCell: (name) => `End point change mode is on. Drag ${name} to another cell on the City Grid.`,
    selectEndpointFunction: 'End point mode is on. Choose an assigned function from the list above.',
    changeEndPointSelectFunction: 'Change end point mode is on. Choose an assigned function from the dropdown above.',
    invalidEndpointCell: 'This cell is not a valid end point for the selected function. Choose a highlighted cell on the City Grid.',
    invalidEndpointOnStartCell: 'The starting point cannot be the end point. Choose a highlighted cell on the City Grid.',
    invalidEndpointClick: 'This cell cannot be the end point. Follow the end point instructions and try another cell.',
    endpointFailed: 'Could not set the end point. Please try again.',
    noAssignedFunctions: 'This event has no assigned city functions to use as an end point.',
    eventWithFullRoute: 'Start and end points are shown on the City Grid for the selected event.',
    eventWithStartOnly: 'Start point is shown on the City Grid for the selected event.',
    saving: 'Saving start point…',
    removing: 'Removing start point…',
    saveFailed: 'Could not save the start point. Please try again.',
    removeFailed: 'Could not remove the start point. Please try again.',
};

let routePlannerEnabled = false;
let startPointMode = false;
let endPointMode = false;
let endpointDragMode = false;
let endpointCellChoiceMode = false;
let endpointCellOptions = [];
let waitingForFunctionId = null;
let waitingForRoadPlacement = false;
let waitingForRoadPlacementChange = false;
let selectedEndpointFunctionId = null;
let assignedFunctions = [];
let selectedEventId = null;
let eventRoutes = [];
let roadFunctionIds = [];
let routesFetchGeneration = 0;
const gridCellAriaBackup = new WeakMap();

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

export function isEndPointModeActive() {
    return endPointMode;
}

export function isEndpointDragModeActive() {
    return endpointDragMode;
}

function isRoutePointCell(cell) {
    if (!routePlannerEnabled || !selectedEventId || !cell) {
        return null;
    }

    const route = getSelectedEventRoute();
    if (!route) {
        return null;
    }

    const row = Number(cell.dataset.row);
    const col = Number(cell.dataset.col);

    if (
        route.start_row != null
        && route.start_col != null
        && Number(route.start_row) === row
        && Number(route.start_col) === col
    ) {
        return 'start';
    }

    if (
        route.end_row != null
        && route.end_col != null
        && Number(route.end_row) === row
        && Number(route.end_col) === col
    ) {
        return 'end';
    }

    return null;
}

export function canDragRouteCell(cell) {
    if (!routePlannerEnabled || !selectedEventId || !cell) {
        return true;
    }

    const routePoint = isRoutePointCell(cell);
    if (routePoint === 'start') {
        return false;
    }

    if (routePoint === 'end') {
        return endpointDragMode;
    }

    return true;
}

export function canDropOnRouteCell(cell) {
    return isRoutePointCell(cell) === null;
}

export function shouldBlockGridCellDrag() {
    return startPointMode || endpointCellChoiceMode;
}

function syncRoutePointDraggability() {
    if (!routePlannerEnabled) {
        return;
    }

    const blockAllDrag = shouldBlockGridCellDrag();

    document.querySelectorAll('.grid-cell').forEach((cell) => {
        const img = cell.querySelector('.grid-function-icon');
        if (!img || cell.classList.contains('is-locked')) {
            return;
        }

        const allowed = !blockAllDrag && canDragRouteCell(cell);
        cell.setAttribute('draggable', allowed ? 'true' : 'false');
        img.setAttribute('draggable', allowed ? 'true' : 'false');
        if (allowed) {
            img.removeAttribute('ondragstart');
        } else {
            img.setAttribute('ondragstart', 'return false;');
        }
    });
}

function setStatus(message, type = 'info') {
    const statusEl = document.getElementById('route-planner-status');
    if (!statusEl) return;

    statusEl.textContent = message;
    statusEl.dataset.statusType = type;
    statusEl.classList.remove('text-red-400', 'text-gray-900', 'text-gray-700', 'text-black');
    statusEl.classList.add('text-white');
}

function announceStatus(message) {
    const announceEl = document.getElementById('route-planner-announcements');
    if (!announceEl || !message) return;

    announceEl.textContent = '';
    window.setTimeout(() => {
        announceEl.textContent = message;
    }, 50);
}

function announceAndSetStatus(message, type = 'info') {
    setStatus(message, type);
    announceStatus(message);
}

function setPairedStatus(status, type = 'info') {
    if (typeof status === 'string') {
        setStatus(status, type);
        announceStatus(status);
        return;
    }

    setStatus(status.visual, type);
    announceStatus(status.announce);
}

function isRoadFunctionId(functionId) {
    const normalizedId = Number(functionId);
    return !Number.isNaN(normalizedId) && roadFunctionIds.includes(normalizedId);
}

function getRoadCellsOnGrid() {
    const placements = [];

    document.querySelectorAll('.grid-cell').forEach((cell) => {
        if (!isMainAccessRoadCell(cell)) {
            return;
        }

        placements.push({
            row: Number(cell.dataset.row),
            col: Number(cell.dataset.col),
        });
    });

    return placements;
}

function getSelectableRoadCellsForStartChange() {
    const route = getSelectedEventRoute();

    return getRoadCellsOnGrid().filter((placement) => {
        if (
            route?.start_row != null
            && route?.start_col != null
            && Number(route.start_row) === placement.row
            && Number(route.start_col) === placement.col
        ) {
            return false;
        }

        if (
            route?.end_row != null
            && route?.end_col != null
            && Number(route.end_row) === placement.row
            && Number(route.end_col) === placement.col
        ) {
            return false;
        }

        return true;
    });
}

function clearStartPointModes() {
    startPointMode = false;
    waitingForRoadPlacement = false;
    waitingForRoadPlacementChange = false;
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

function clearRouteMarkers() {
    document.querySelectorAll('.route-start-marker, .route-end-marker').forEach((marker) => marker.remove());
    document.querySelectorAll('.grid-cell.route-start-point, .grid-cell.route-end-point, .grid-cell.route-endpoint-option').forEach((cell) => {
        cell.classList.remove('route-start-point', 'route-end-point', 'route-endpoint-option');
        restoreGridCellAriaLabel(cell);
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

function applyEndPointMarker(cell) {
    cell.classList.add('route-end-point');

    let marker = cell.querySelector('.route-end-marker');
    if (!marker) {
        marker = document.createElement('div');
        marker.className = 'route-end-marker absolute z-20 bottom-1 right-1 bg-blue-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded shadow uppercase tracking-wide';
        marker.setAttribute('aria-hidden', 'true');
        marker.textContent = 'End';
        cell.appendChild(marker);
    }
}

function formatPlacementLabel(name, placements) {
    if (!placements.length) {
        return `${name}. Not on City Grid. Place this function on the grid first.`;
    }

    if (placements.length === 1) {
        return `${name} at row ${placements[0].row}, column ${placements[0].col} on the City Grid.`;
    }

    const locations = placements
        .map((placement) => `row ${placement.row}, column ${placement.col}`)
        .join('; ');

    return `${name} on the City Grid at ${locations}.`;
}

function backupGridCellAriaLabel(cell) {
    if (!gridCellAriaBackup.has(cell)) {
        gridCellAriaBackup.set(cell, cell.getAttribute('aria-label'));
    }
}

function restoreGridCellAriaLabel(cell) {
    const previous = gridCellAriaBackup.get(cell);
    if (previous === undefined) {
        cell.removeAttribute('aria-label');
    } else if (previous === null) {
        cell.removeAttribute('aria-label');
    } else {
        cell.setAttribute('aria-label', previous);
    }
    gridCellAriaBackup.delete(cell);
}

function highlightEndpointCellOptions(options, functionName) {
    options.forEach((option) => {
        const cell = document.querySelector(
            `.grid-cell[data-row="${option.row}"][data-col="${option.col}"]`
        );
        if (!cell) return;

        cell.classList.add('route-endpoint-option');
        backupGridCellAriaLabel(cell);
        cell.setAttribute(
            'aria-label',
            `${functionName} end point option at row ${option.row}, column ${option.col} on the City Grid.`
        );
    });
}

function isChangingStartPoint() {
    const route = getSelectedEventRoute();
    return startPointMode && route?.start_row != null && route?.start_col != null;
}

function renderRouteOnGrid() {
    clearRouteMarkers();

    const route = getSelectedEventRoute();
    if (route) {
        if (route.start_row != null && route.start_col != null) {
            const startCell = document.querySelector(
                `.grid-cell[data-row="${route.start_row}"][data-col="${route.start_col}"]`
            );
            if (startCell) {
                applyStartPointMarker(startCell);
                if (isChangingStartPoint()) {
                    backupGridCellAriaLabel(startCell);
                    startCell.setAttribute(
                        'aria-label',
                        `Current start point at row ${route.start_row}, column ${route.start_col} on the City Grid. Select a new Road cell to change the starting point.`
                    );
                } else {
                    backupGridCellAriaLabel(startCell);
                    startCell.setAttribute(
                        'aria-label',
                        `Start point at row ${route.start_row}, column ${route.start_col} on the City Grid. Use ${CHANGE_START_LABEL} to move it.`
                    );
                }
            }
        }

        if (endpointCellChoiceMode) {
            highlightEndpointCellOptions(endpointCellOptions, getSelectedFunctionName(selectedEndpointFunctionId));
        } else if (endpointDragMode && route.end_row != null && route.end_col != null) {
            const endCell = document.querySelector(
                `.grid-cell[data-row="${route.end_row}"][data-col="${route.end_col}"]`
            );
            if (endCell) {
                applyEndPointMarker(endCell);
                backupGridCellAriaLabel(endCell);
                endCell.setAttribute(
                    'aria-label',
                    `Current end point: ${route.end_function_name || getSelectedFunctionName(selectedEndpointFunctionId)} at row ${route.end_row}, column ${route.end_col}. Drag this function to another cell on the City Grid to change the end point.`
                );
            }
        } else if (route.end_row != null && route.end_col != null) {
            const endCell = document.querySelector(
                `.grid-cell[data-row="${route.end_row}"][data-col="${route.end_col}"]`
            );
            if (endCell) {
                applyEndPointMarker(endCell);
                backupGridCellAriaLabel(endCell);
                endCell.setAttribute(
                    'aria-label',
                    `End point: ${route.end_function_name || 'event function'} at row ${route.end_row}, column ${route.end_col} on the City Grid. Use ${CHANGE_END_LABEL} to move it.`
                );
            }
        }
    }

    syncRoutePointDraggability();
}

function getSelectedEventRoute() {
    if (!selectedEventId) return null;
    return eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId)) ?? null;
}

function getSelectedFunctionName(functionId) {
    return assignedFunctions.find((fn) => Number(fn.id) === Number(functionId))?.name ?? 'event function';
}

function clearEndpointModes() {
    endPointMode = false;
    endpointDragMode = false;
    endpointCellChoiceMode = false;
    endpointCellOptions = [];
    waitingForFunctionId = null;
    selectedEndpointFunctionId = null;
    assignedFunctions = [];
}

function updateEndpointControlsVisibility() {
    const controls = document.getElementById('route-endpoint-controls');
    const functionSelect = document.getElementById('route-endpoint-function-select');
    const endBtn = document.getElementById('route-set-end-btn');
    const route = getSelectedEventRoute();
    const hasStartPoint = Boolean(route?.start_row != null && route?.start_col != null);

    if (endBtn) {
        endBtn.hidden = !hasStartPoint;
        endBtn.disabled = !selectedEventId || !hasStartPoint || startPointMode;
        endBtn.textContent = route?.end_row != null ? CHANGE_END_LABEL : SET_END_LABEL;
        endBtn.setAttribute('aria-pressed', endPointMode ? 'true' : 'false');
        endBtn.setAttribute(
            'aria-label',
            endPointMode
                ? 'Cancel end point selection'
                : (route?.end_row != null
                    ? 'Change end point for the selected event'
                    : 'Set end point for the selected event')
        );
        endBtn.classList.toggle('ring-2', endPointMode);
        endBtn.classList.toggle('ring-sky-400', endPointMode);
    }

    if (!controls || !functionSelect) return;

    const showDropdown = endPointMode && assignedFunctions.length > 1;
    controls.classList.toggle('hidden', !showDropdown);
    functionSelect.disabled = !showDropdown;
}

function populateEndpointFunctionSelect(functions) {
    const select = document.getElementById('route-endpoint-function-select');
    if (!select) return;

    select.innerHTML = '<option value="">— Select assigned function —</option>';

    functions.forEach((fn) => {
        const option = document.createElement('option');
        option.value = String(fn.id);
        option.textContent = fn.name;
        option.setAttribute('aria-label', formatPlacementLabel(fn.name, fn.placements ?? []));
        select.appendChild(option);
    });
}

function updateStartPointButtonState() {
    const btn = document.getElementById('route-set-start-btn');
    if (!btn) return;

    const hasEvent = Boolean(selectedEventId);
    const route = getSelectedEventRoute();
    const hasStartPoint = Boolean(route?.start_row != null && route?.start_col != null);
    btn.disabled = !hasEvent || endPointMode;
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

    updateEndpointControlsVisibility();
}

function updateRemoveButtonState() {
    const btn = document.getElementById('route-remove-start-btn');
    if (!btn) return;

    const route = getSelectedEventRoute();
    const hasStartPoint = Boolean(route?.start_row != null && route?.start_col != null);
    btn.disabled = !hasStartPoint || endPointMode;
}

function updateStatusForSelectedEvent() {
    if (!selectedEventId) {
        setStatus(ROUTE_STATUS.selectEvent);
        return;
    }

    if (endpointDragMode) {
        setStatus(ROUTE_STATUS.dragEndpointToCell(getSelectedFunctionName(selectedEndpointFunctionId)));
        return;
    }

    if (endPointMode && assignedFunctions.length > 1 && !selectedEndpointFunctionId) {
        setStatus(ROUTE_STATUS.selectEndpointFunction);
        return;
    }

    if (endpointCellChoiceMode) {
        setStatus(
            ROUTE_STATUS.chooseEndpointCell(
                getSelectedFunctionName(selectedEndpointFunctionId),
                endpointCellOptions
            ).visual
        );
        return;
    }

    if (waitingForRoadPlacement) {
        const route = getSelectedEventRoute();
        if (waitingForRoadPlacementChange && route?.start_row != null && route?.start_col != null) {
            setStatus(ROUTE_STATUS.placeRoadOnGridToChange(route.start_row, route.start_col).visual);
        } else {
            setStatus(ROUTE_STATUS.placeRoadOnGrid);
        }
        return;
    }

    if (waitingForFunctionId) {
        setStatus(ROUTE_STATUS.placeFunctionOnGrid(getSelectedFunctionName(waitingForFunctionId)));
        return;
    }

    const route = getSelectedEventRoute();
    if (route) {
        if (startPointMode) {
            if (route.start_row != null && route.start_col != null) {
                setStatus(ROUTE_STATUS.changeStartPoint(route.start_row, route.start_col).visual);
            } else {
                setStatus(ROUTE_STATUS.selectRoadCell);
            }
            return;
        }
        if (route.end_row != null && route.end_col != null) {
            setStatus(ROUTE_STATUS.eventWithFullRoute);
            return;
        }
        setStatus(ROUTE_STATUS.eventWithStartOnly);
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

function upsertSelectedRoute(route) {
    const existingIndex = eventRoutes.findIndex(
        (r) => Number(r.event_id) === Number(selectedEventId)
    );
    if (existingIndex >= 0) {
        eventRoutes[existingIndex] = route;
    } else {
        eventRoutes.push(route);
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
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
    } catch (error) {
        if (generation !== routesFetchGeneration) return;
        console.error('Failed to load event routes:', error);
    }
}

async function fetchEndpointContext() {
    if (!selectedEventId) return null;

    const response = await fetch(`/event-routes/${selectedEventId}/endpoint-context`, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    const data = await response.json();
    if (!response.ok || !data.success) {
        throw new Error(data.message || ROUTE_STATUS.endpointFailed);
    }

    return data;
}

async function saveEndpoint(functionId, row = null, col = null, { isAuto = false } = {}) {
    if (!selectedEventId) return false;

    const routeBefore = getSelectedEventRoute();
    const hadEndpoint = Boolean(routeBefore?.end_row != null && routeBefore?.end_col != null);

    try {
        const payload = { function_id: functionId };
        if (row != null && col != null) {
            payload.row = row;
            payload.col = col;
        }

        const response = await fetch(`/event-routes/${selectedEventId}/endpoint`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.status === 422 && data.error === 'endpoint_choice_required') {
            endpointCellChoiceMode = true;
            endpointCellOptions = Array.isArray(data.placements) ? data.placements : [];
            waitingForFunctionId = null;
            renderRouteOnGrid();
            const status = ROUTE_STATUS.chooseEndpointCell(
                getSelectedFunctionName(functionId),
                endpointCellOptions
            );
            setPairedStatus(status);
            return false;
        }

        if (!response.ok || !data.success) {
            if (data.error === 'function_not_on_grid') {
                waitingForFunctionId = functionId;
                selectedEndpointFunctionId = functionId;
                endPointMode = true;
                endpointCellChoiceMode = false;
                endpointCellOptions = [];
                endpointDragMode = false;
                renderRouteOnGrid();
                const message = data.message || ROUTE_STATUS.placeFunctionOnGrid(getSelectedFunctionName(functionId));
                setStatus(message);
                announceStatus(message);
                return false;
            }

            announceAndSetStatus(data.message || ROUTE_STATUS.endpointFailed, 'info');
            renderRouteOnGrid();
            updateStartPointButtonState();
            updateEndpointControlsVisibility();
            return false;
        }

        upsertSelectedRoute(data.route);
        waitingForFunctionId = null;
        endpointCellChoiceMode = false;
        endpointCellOptions = [];
        endpointDragMode = false;
        endPointMode = false;
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();

        let status;
        if (hadEndpoint) {
            status = ROUTE_STATUS.endpointChanged(
                data.route.end_function_name || getSelectedFunctionName(functionId),
                data.route.end_row,
                data.route.end_col
            );
        } else if (isAuto) {
            status = ROUTE_STATUS.endpointAutoSet(
                data.route.end_function_name || getSelectedFunctionName(functionId),
                data.route.end_row,
                data.route.end_col
            );
        } else {
            status = ROUTE_STATUS.endpointSet(
                data.route.end_function_name || getSelectedFunctionName(functionId),
                data.route.end_row,
                data.route.end_col
            );
        }

        setPairedStatus(status, 'info');
        return true;
    } catch (error) {
        console.error('Failed to save endpoint:', error);
        announceAndSetStatus(ROUTE_STATUS.endpointFailed, 'info');
        return false;
    }
}

async function processSelectedFunction(functionEntry, { changing = false } = {}) {
    selectedEndpointFunctionId = Number(functionEntry.id);
    waitingForFunctionId = null;
    endpointCellChoiceMode = false;
    endpointCellOptions = [];
    endpointDragMode = false;
    endPointMode = true;

    const placements = functionEntry.placements ?? [];
    const route = getSelectedEventRoute();
    const isSameFunctionAsCurrentEndpoint = changing
        && route?.end_row != null
        && route?.end_col != null
        && Number(route.end_function_id) === Number(functionEntry.id);

    if (placements.length === 0) {
        waitingForFunctionId = selectedEndpointFunctionId;
        renderRouteOnGrid();
        const message = ROUTE_STATUS.placeFunctionOnGrid(functionEntry.name);
        setStatus(message);
        announceStatus(message);
        return;
    }

    if (isSameFunctionAsCurrentEndpoint) {
        endpointDragMode = true;
        renderRouteOnGrid();
        const message = ROUTE_STATUS.dragEndpointToCell(functionEntry.name);
        setStatus(message);
        announceStatus(message);
        return;
    }

    const isReplacingEndpoint = route?.end_row != null && route?.end_col != null;

    if (placements.length === 1) {
        await saveEndpoint(
            functionEntry.id,
            placements[0].row,
            placements[0].col,
            { isAuto: !isReplacingEndpoint }
        );
        return;
    }

    endpointCellChoiceMode = true;
    endpointCellOptions = placements;
    renderRouteOnGrid();
    setPairedStatus(ROUTE_STATUS.chooseEndpointCell(functionEntry.name, placements));
}

async function activateEndPointMode() {
    if (!selectedEventId || !getSelectedEventRoute()) return;

    clearStartPointModes();

    if (endPointMode) {
        clearEndpointModes();
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
        return;
    }

    endPointMode = true;

    try {
        const data = await fetchEndpointContext();
        assignedFunctions = data.assigned_functions ?? [];
        upsertSelectedRoute(data.route);

        if (assignedFunctions.length === 0) {
            clearEndpointModes();
            announceAndSetStatus(ROUTE_STATUS.noAssignedFunctions, 'info');
            updateStartPointButtonState();
            return;
        }

        populateEndpointFunctionSelect(assignedFunctions);
        updateStartPointButtonState();
        updateRemoveButtonState();

        if (assignedFunctions.length === 1) {
            const route = getSelectedEventRoute();
            const isChanging = route?.end_row != null && route?.end_col != null;
            await processSelectedFunction(assignedFunctions[0], { changing: isChanging });
            return;
        }

        const route = getSelectedEventRoute();
        const message = route?.end_row != null
            ? ROUTE_STATUS.changeEndPointSelectFunction
            : ROUTE_STATUS.selectEndpointFunction;
        announceAndSetStatus(message);
    } catch (error) {
        clearEndpointModes();
        announceAndSetStatus(error.message || ROUTE_STATUS.endpointFailed, 'info');
        updateStartPointButtonState();
    }
}

async function saveStartPoint(row, col, { isAuto = false } = {}) {
    if (!selectedEventId) return false;

    const routeBefore = getSelectedEventRoute();
    const hadStartPoint = Boolean(routeBefore?.start_row != null && routeBefore?.start_col != null);

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
            announceAndSetStatus(data.message || ROUTE_STATUS.invalidCell, 'info');
            return false;
        }

        upsertSelectedRoute(data.route);
        clearStartPointModes();
        clearEndpointModes();
        updateStartPointButtonState();
        renderRouteOnGrid();
        updateRemoveButtonState();
        let status;
        if (hadStartPoint) {
            status = ROUTE_STATUS.startPointChanged(data.route.start_row, data.route.start_col);
        } else if (isAuto) {
            status = ROUTE_STATUS.startPointAutoSet(data.route.start_row, data.route.start_col);
        } else {
            status = ROUTE_STATUS.startPointSet(data.route.start_row, data.route.start_col);
        }
        setPairedStatus(status, 'info');
        return true;
    } catch (error) {
        console.error('Failed to save start point:', error);
        announceAndSetStatus(ROUTE_STATUS.saveFailed, 'info');
        return false;
    }
}

async function removeStartPoint() {
    if (!selectedEventId) return;

    const route = eventRoutes.find((r) => Number(r.event_id) === Number(selectedEventId));
    if (!route) return;

    const { start_row: removedRow, start_col: removedCol } = route;
    invalidateRoutesFetch();
    clearEndpointModes();

    eventRoutes = eventRoutes.filter((r) => Number(r.event_id) !== Number(selectedEventId));
    clearRouteMarkers();
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
            announceAndSetStatus(ROUTE_STATUS.removeFailed, 'info');
            return;
        }

        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        announceAndSetStatus(ROUTE_STATUS.startPointRemoved, 'info');
    } catch (error) {
        console.error('Failed to remove start point:', error);
        await fetchEventRoutes();
        announceAndSetStatus(ROUTE_STATUS.removeFailed, 'info');
    }
}

function isEndpointOptionCell(row, col) {
    return endpointCellOptions.some(
        (option) => Number(option.row) === row && Number(option.col) === col
    );
}

function handleInvalidEndpointClick(cell) {
    let message;

    if (isRoutePointCell(cell) === 'start' || isMainAccessRoadCell(cell)) {
        message = ROUTE_STATUS.invalidEndpointOnStartCell;
    } else if (endpointCellChoiceMode) {
        message = ROUTE_STATUS.invalidEndpointCell;
    } else if (waitingForFunctionId) {
        message = ROUTE_STATUS.placeFunctionOnGrid(getSelectedFunctionName(waitingForFunctionId));
    } else if (endPointMode && assignedFunctions.length > 1 && !selectedEndpointFunctionId) {
        const route = getSelectedEventRoute();
        message = route?.end_row != null
            ? ROUTE_STATUS.changeEndPointSelectFunction
            : ROUTE_STATUS.selectEndpointFunction;
    } else {
        message = ROUTE_STATUS.invalidEndpointClick;
    }

    setPairedStatus(message, 'info');
    renderRouteOnGrid();
    updateStartPointButtonState();
    updateEndpointControlsVisibility();
}

function getRoutePointRemovalImpact(row, col, functionId) {
    if (!selectedEventId) {
        return { start: false, end: false };
    }

    const route = getSelectedEventRoute();
    if (!route) {
        return { start: false, end: false };
    }

    const normalizedRow = Number(row);
    const normalizedCol = Number(col);
    const normalizedFunctionId = functionId != null ? Number(functionId) : null;

    const start = route.start_row != null
        && route.start_col != null
        && Number(route.start_row) === normalizedRow
        && Number(route.start_col) === normalizedCol;

    const end = route.end_row != null
        && route.end_col != null
        && Number(route.end_row) === normalizedRow
        && Number(route.end_col) === normalizedCol
        && (normalizedFunctionId === null
            || Number(route.end_function_id) === normalizedFunctionId);

    return { start, end };
}

function applyRoutePointRemovalState({ start, end }) {
    if (start) {
        clearStartPointModes();
    }

    if (end) {
        endpointDragMode = false;
        endpointCellChoiceMode = false;
        endpointCellOptions = [];

        const route = getSelectedEventRoute();
        if (endPointMode && route?.end_function_id != null) {
            waitingForFunctionId = Number(route.end_function_id);
            selectedEndpointFunctionId = Number(route.end_function_id);
        } else if (endPointMode) {
            clearEndpointModes();
        }
    }
}

export async function handleGridFunctionRemoved(row, col, functionId) {
    if (!routePlannerEnabled) return;

    const removalImpact = getRoutePointRemovalImpact(row, col, functionId);

    try {
        const payload = {
            row: Number(row),
            col: Number(col),
        };
        if (functionId != null && functionId !== '' && !Number.isNaN(Number(functionId))) {
            payload.function_id = Number(functionId);
        }

        const response = await fetch('/event-routes/sync-grid-remove', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            renderRouteOnGrid();
            return;
        }

        const data = await response.json();
        if (!data.success) {
            renderRouteOnGrid();
            return;
        }

        eventRoutes = data.routes ?? [];
        applyRoutePointRemovalState(removalImpact);
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateEndpointControlsVisibility();

        if (removalImpact.end && endPointMode && waitingForFunctionId) {
            setPairedStatus(ROUTE_STATUS.placeFunctionOnGrid(getSelectedFunctionName(waitingForFunctionId)));
            return;
        }

        if (!endPointMode && !startPointMode && !waitingForFunctionId && !waitingForRoadPlacement && !endpointDragMode) {
            updateStatusForSelectedEvent();
        }
    } catch (error) {
        console.error('Failed to sync routes after grid function removal:', error);
        renderRouteOnGrid();
    }
}

export async function handleGridFunctionMoved(oldRow, oldCol, newRow, newCol, functionId) {
    if (!routePlannerEnabled) return;

    try {
        const response = await fetch('/event-routes/sync-grid-move', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                old_row: oldRow != null && oldRow !== '' ? Number(oldRow) : null,
                old_col: oldCol != null && oldCol !== '' ? Number(oldCol) : null,
                new_row: Number(newRow),
                new_col: Number(newCol),
                function_id: Number(functionId),
            }),
        });

        if (!response.ok) {
            renderRouteOnGrid();
            return;
        }

        const data = await response.json();
        if (!data.success) {
            renderRouteOnGrid();
            return;
        }

        eventRoutes = data.routes ?? [];
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateEndpointControlsVisibility();

        if (!endPointMode && !startPointMode && !waitingForFunctionId && !waitingForRoadPlacement && !endpointDragMode) {
            updateStatusForSelectedEvent();
        }
    } catch (error) {
        console.error('Failed to sync routes after grid move:', error);
        renderRouteOnGrid();
    }
}

export function handleGridFunctionPlaced(cell, functionId) {
    if (!routePlannerEnabled || !selectedEventId) return;

    const normalizedFunctionId = Number(functionId);
    if (Number.isNaN(normalizedFunctionId)) return;

    const row = Number(cell.dataset.row);
    const col = Number(cell.dataset.col);

    if (startPointMode && waitingForRoadPlacement) {
        if (!isRoadFunctionId(normalizedFunctionId) || isRoutePointCell(cell) !== null) {
            return;
        }

        const isChange = waitingForRoadPlacementChange;
        saveStartPoint(row, col, { isAuto: !isChange });
        return;
    }

    if (!endPointMode && !waitingForFunctionId && !endpointDragMode) return;

    const activeFunctionId = waitingForFunctionId ?? selectedEndpointFunctionId;
    if (Number(activeFunctionId) !== normalizedFunctionId) return;

    const route = getSelectedEventRoute();

    if (endpointDragMode) {
        if (
            route?.end_row != null
            && route?.end_col != null
            && Number(route.end_row) === row
            && Number(route.end_col) === col
        ) {
            setStatus(ROUTE_STATUS.dragEndpointToCell(getSelectedFunctionName(normalizedFunctionId)));
            announceStatus(ROUTE_STATUS.dragEndpointToCell(getSelectedFunctionName(normalizedFunctionId)));
            return;
        }

        saveEndpoint(normalizedFunctionId, row, col);
        return;
    }

    saveEndpoint(normalizedFunctionId, row, col, {
        isAuto: !(route?.end_row != null && route?.end_col != null),
    });
}

export function handleRouteCellClick(cell) {
    if (!routePlannerEnabled || !selectedEventId) {
        return false;
    }

    const row = Number(cell.dataset.row);
    const col = Number(cell.dataset.col);

    if (endpointCellChoiceMode) {
        if (isEndpointOptionCell(row, col)) {
            saveEndpoint(selectedEndpointFunctionId, row, col);
            return true;
        }

        handleInvalidEndpointClick(cell);
        return true;
    }

    if (endpointDragMode) {
        setPairedStatus(
            ROUTE_STATUS.dragEndpointToCell(getSelectedFunctionName(selectedEndpointFunctionId)),
            'info'
        );
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateEndpointControlsVisibility();
        return true;
    }

    if (endPointMode || waitingForFunctionId) {
        handleInvalidEndpointClick(cell);
        return true;
    }

    if (!startPointMode) {
        return false;
    }

    if (!isMainAccessRoadCell(cell)) {
        announceAndSetStatus(ROUTE_STATUS.invalidCell, 'info');
        renderRouteOnGrid();
        updateStartPointButtonState();
        return true;
    }

    const routePoint = isRoutePointCell(cell);
    if (routePoint === 'start') {
        announceAndSetStatus(ROUTE_STATUS.invalidCell, 'info');
        renderRouteOnGrid();
        updateStartPointButtonState();
        return true;
    }

    if (routePoint === 'end') {
        announceAndSetStatus(ROUTE_STATUS.invalidCell, 'info');
        renderRouteOnGrid();
        updateStartPointButtonState();
        return true;
    }

    saveStartPoint(row, col, {
        isAuto: waitingForRoadPlacement && !waitingForRoadPlacementChange,
    });
    return true;
}

export function syncRoutePlannerEvents(events) {
    if (!routePlannerEnabled) return;
    populateEventSelect(events);
    renderRouteOnGrid();
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
    const setEndBtn = document.getElementById('route-set-end-btn');
    const functionSelect = document.getElementById('route-endpoint-function-select');
    const removeStartBtn = document.getElementById('route-remove-start-btn');

    eventSelect?.addEventListener('change', () => {
        selectedEventId = eventSelect.value ? Number(eventSelect.value) : null;
        clearStartPointModes();
        clearEndpointModes();
        renderRouteOnGrid();
        updateStartPointButtonState();
        updateRemoveButtonState();
        updateStatusForSelectedEvent();
    });

    setStartBtn?.addEventListener('click', () => {
        if (!selectedEventId || endPointMode) return;

        if (startPointMode) {
            clearStartPointModes();
            updateStartPointButtonState();
            renderRouteOnGrid();
            updateStatusForSelectedEvent();
            return;
        }

        startPointMode = true;
        clearEndpointModes();
        updateStartPointButtonState();
        renderRouteOnGrid();

        const route = getSelectedEventRoute();
        const isChanging = Boolean(route?.start_row != null && route?.start_col != null);
        const needsRoadPlacement = isChanging
            ? getSelectableRoadCellsForStartChange().length === 0
            : getRoadCellsOnGrid().length === 0;

        if (needsRoadPlacement) {
            waitingForRoadPlacement = true;
            waitingForRoadPlacementChange = isChanging;
            const status = isChanging
                ? ROUTE_STATUS.placeRoadOnGridToChange(route.start_row, route.start_col)
                : ROUTE_STATUS.placeRoadOnGrid;
            setPairedStatus(status);
            return;
        }

        waitingForRoadPlacement = false;
        waitingForRoadPlacementChange = false;
        const status = isChanging
            ? ROUTE_STATUS.changeStartPoint(route.start_row, route.start_col)
            : ROUTE_STATUS.selectRoadCell;
        setPairedStatus(status);
    });

    setEndBtn?.addEventListener('click', () => {
        activateEndPointMode();
    });

    functionSelect?.addEventListener('change', async () => {
        if (!functionSelect.value || !selectedEventId) return;

        try {
            const data = await fetchEndpointContext();
            assignedFunctions = data.assigned_functions ?? [];
            upsertSelectedRoute(data.route);

            const functionEntry = assignedFunctions.find(
                (fn) => String(fn.id) === functionSelect.value
            );
            if (!functionEntry) return;

            const route = getSelectedEventRoute();
            const isChanging = route?.end_row != null
                && Number(route.end_function_id) === Number(functionEntry.id);

            await processSelectedFunction(functionEntry, { changing: isChanging });
        } catch (error) {
            announceAndSetStatus(error.message || ROUTE_STATUS.endpointFailed, 'info');
        }
    });

    removeStartBtn?.addEventListener('click', () => {
        removeStartPoint();
    });

    fetchEventRoutes();
    setStatus(ROUTE_STATUS.selectEvent);
}
