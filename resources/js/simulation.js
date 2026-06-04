import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls,
    getMaxTime // Importeer deze!
} from './regulation.js';

export const simulationState = {
    speed: 1,
};

// Berekening: 0.5 uur simulatie = 1 seconde echte tijd.
// Als 100 units = 24 uur, dan is 1 unit = 0.24 uur.
// Dus 0.5 uur / 0.24 = 2.083 units per seconde.
const SIMULATION_UNITS_PER_SECOND = 2.083;

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

export function simulationLoop(timestamp) {
    if (getIsPlaying()) {
        if (!lastTimestamp) lastTimestamp = timestamp;
        
        const deltaTime = (timestamp - lastTimestamp) / 1000;
        const maxTime = getMaxTime() || 100;
        let currentTime = getCurrentTime();
        
        if (currentTime < maxTime) {
            const increment = SIMULATION_UNITS_PER_SECOND * simulationState.speed * deltaTime;
            setCurrentTime(currentTime + increment);
            syncTimelineUI(); 
        }
        lastTimestamp = timestamp;
    } else {
        lastTimestamp = 0;
    }
    
    requestAnimationFrame(simulationLoop);
};

window.setSimulationSpeed = setSimulationSpeed;