import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls,
    getMaxTime,
    MINUTES_PER_SECOND,
    getIsDay,
} from './regulation.js';

export const simulationState = { speed: 1 };

// ─── Day / Night indicator ────────────────────────────────────────────────────

let _lastWasDay = null;

function updateDayNightIndicator(simTime) {
    const isDay = getIsDay(simTime);

    // Alleen herrenderen bij overgang
    if (_lastWasDay === isDay) return;
    _lastWasDay = isDay;

    const indicator = document.getElementById('day-night-indicator');
    if (!indicator) return;

    if (isDay) {
        indicator.innerHTML = `
            <span class="dn-icon">☀️</span>
            <span class="dn-label">Day</span>
            <span class="dn-badge">06:00 – 24:00</span>`;
        indicator.className = 'dn-indicator dn-day';
    } else {
        indicator.innerHTML = `
            <span class="dn-icon">🌙</span>
            <span class="dn-label">Night</span>
            <span class="dn-badge">00:00 – 06:00</span>`;
        indicator.className = 'dn-indicator dn-night';
    }

    // Flash-animatie bij wisseling
    indicator.classList.add('dn-transition');
    setTimeout(() => indicator.classList.remove('dn-transition'), 600);
}

// ─── Speed ───────────────────────────────────────────────────────────────────

export const setSimulationSpeed = async (speed) => {
    simulationState.speed = parseInt(speed);
    const display = document.getElementById('active-speed-display');
    if (display) display.innerText = speed + '×';
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

// ─── Loop ────────────────────────────────────────────────────────────────────

let lastTimestamp = 0;
let onTimeUpdateCallback = null;
export const onSimulationTimeUpdate = (cb) => { onTimeUpdateCallback = cb; };

export function simulationLoop(timestamp) {
    if (getIsPlaying()) {
        if (!lastTimestamp) lastTimestamp = timestamp;

        const deltaTime = (timestamp - lastTimestamp) / 1000;
        const maxTime   = getMaxTime();
        const current   = getCurrentTime();

        if (current < maxTime) {
            const increment = MINUTES_PER_SECOND * simulationState.speed * deltaTime;
            setCurrentTime(current + increment);
            syncTimelineUI();
            updateDayNightIndicator(getCurrentTime());
            if (onTimeUpdateCallback) onTimeUpdateCallback(getCurrentTime());
        } else {
            setCurrentTime(maxTime);
            syncTimelineUI();
            updateDayNightIndicator(getCurrentTime());
            if (onTimeUpdateCallback) onTimeUpdateCallback(getCurrentTime());

            const playPauseBtn = document.getElementById('playPauseBtn');
            if (playPauseBtn && getIsPlaying()) playPauseBtn.click();
        }

        lastTimestamp = timestamp;
    } else {
        lastTimestamp = 0;
    }

    requestAnimationFrame(simulationLoop);
}