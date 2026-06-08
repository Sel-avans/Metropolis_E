import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls,
    getMaxTime,
    MINUTES_PER_SECOND,
} from './regulation.js';

export const simulationState = { speed: 1 };

/** Full simulation cycle length in minutes (06:00 → next 06:00). */
export const CYCLE_LENGTH_MINUTES = 1440;

export const setSimulationSpeed = async (speed) => {
    simulationState.speed = parseInt(speed, 10);
    const display = document.getElementById('active-speed-display');
    if (display) display.innerText = speed + '×';

    document.querySelectorAll('.speed-btn').forEach(btn => {
        const selected = parseInt(btn.getAttribute('data-speed'), 10) === simulationState.speed;
        btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });

    syncTimelineUI();

    try {
        const response = await fetch('/api/simulation/speed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ speed: simulationState.speed }),
        });
        if (!response.ok) throw new Error('Failed to update speed');
    } catch (error) {
        console.error('Error updating simulation speed:', error);
    }
};

window.setSimulationSpeed = setSimulationSpeed;

if (!window.__simControlsInitialized) {
    window.__simControlsInitialized = true;
    initSimulationControls();
}

let lastTimestamp = 0;
let onTimeUpdateCallback = null;
let onCycleCompleteCallback = null;

export const onSimulationTimeUpdate = (cb) => { onTimeUpdateCallback = cb; };
export const onSimulationCycleComplete = (cb) => { onCycleCompleteCallback = cb; };

/**
 * Advance simulation time, snap to day/night boundary when crossing, wrap at cycle end.
 * @returns {{ wrapped: boolean }}
 */
export function advanceSimulationTime(deltaTimeSeconds) {
    const maxTime   = getMaxTime();
    const current   = getCurrentTime();
    const increment = MINUTES_PER_SECOND * simulationState.speed * deltaTimeSeconds;
    let next        = current + increment;
    let wrapped     = false;

    if (next >= maxTime) {
        next = next - maxTime;
        wrapped = true;
    }

    setCurrentTime(next);
    syncTimelineUI();

    if (onTimeUpdateCallback) {
        onTimeUpdateCallback(getCurrentTime());
    }

    if (wrapped && onCycleCompleteCallback) {
        onCycleCompleteCallback();
    }

    return { wrapped };
}

export function simulationLoop(timestamp) {
    if (getIsPlaying()) {
        if (!lastTimestamp) lastTimestamp = timestamp;

        const deltaTime = (timestamp - lastTimestamp) / 1000;
        advanceSimulationTime(deltaTime);

        lastTimestamp = timestamp;
    } else {
        lastTimestamp = 0;
    }

    requestAnimationFrame(simulationLoop);
}
