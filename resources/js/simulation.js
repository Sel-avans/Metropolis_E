import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls,
    getMaxTime,
    MINUTES_PER_SECOND,
} from './regulation.js';

export const simulationState = {
    speed: 1,
};

export const setSimulationSpeed = async (speed) => {
    simulationState.speed = parseInt(speed);

    // Update active-speed-display
    const display = document.getElementById('active-speed-display');
    if (display) display.innerText = speed + '×';

    try {
        const response = await fetch('/api/simulation/speed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ speed: simulationState.speed })
        });
        if (!response.ok) throw new Error('Failed to update speed');
    } catch (error) {
        console.error('Error updating simulation speed:', error);
    }
};

initSimulationControls();

let lastTimestamp = 0;

// Callback die grid.js registreert voor tick-updates
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

            if (onTimeUpdateCallback) {
                onTimeUpdateCallback(getCurrentTime());
            }
        } else {
            // Dag voorbij: stop
            setCurrentTime(maxTime);
            syncTimelineUI();
            const playPauseBtn = document.getElementById('playPauseBtn');
            if (playPauseBtn && getIsPlaying()) playPauseBtn.click();
        }

        lastTimestamp = timestamp;
    } else {
        lastTimestamp = 0;
    }

    requestAnimationFrame(simulationLoop);
}

window.setSimulationSpeed = setSimulationSpeed;