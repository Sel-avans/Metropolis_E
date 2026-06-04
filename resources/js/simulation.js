import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls,
    getMaxTime,
    MINUTES_PER_SECOND, // 30 min/sec → 1440 min / 48 sec
} from './regulation.js';

export const simulationState = {
    speed: 1,
};

export const setSimulationSpeed = async (speed) => {
    simulationState.speed = parseInt(speed);
    document.getElementById('active-speed-display').innerText = speed + '×';

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

// Callback die grid.js kan registreren om events te activeren/deactiveren
let onTimeUpdateCallback = null;
export const onSimulationTimeUpdate = (cb) => { onTimeUpdateCallback = cb; };

export function simulationLoop(timestamp) {
    if (getIsPlaying()) {
        if (!lastTimestamp) lastTimestamp = timestamp;

        const deltaTime = (timestamp - lastTimestamp) / 1000; // seconden
        const maxTime   = getMaxTime(); // 1440 minuten
        const current   = getCurrentTime();

        if (current < maxTime) {
            // 24 uur = 48 seconden → 30 minuten per seconde (× speed)
            const increment = MINUTES_PER_SECOND * simulationState.speed * deltaTime;
            setCurrentTime(current + increment);
            syncTimelineUI();

            // Laat grid.js weten wat de huidige simulatietijd is
            if (onTimeUpdateCallback) {
                onTimeUpdateCallback(getCurrentTime());
            }
        } else {
            // Einde van de dag bereikt: stop simulatie
            setCurrentTime(maxTime);
            syncTimelineUI();
            // isPlaying stoppen via playPauseBtn simuleren
            const playPauseBtn = document.getElementById('playPauseBtn');
            if (playPauseBtn) playPauseBtn.click();
        }

        lastTimestamp = timestamp;
    } else {
        lastTimestamp = 0;
    }

    requestAnimationFrame(simulationLoop);
}

window.setSimulationSpeed = setSimulationSpeed;