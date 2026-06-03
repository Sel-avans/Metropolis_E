import {
    getIsPlaying,
    getCurrentTime,
    setCurrentTime,
    syncTimelineUI,
    initSimulationControls
} from './regulation.js';

//Global State for standard speed
export const simulationState = {
    speed: 1,
};


export const setSimulationSpeed = async (speed) => {

    simulationState.speed = parseInt(speed);

    document.getElementById('active-speed-display').innerText = speed + '×';

    // Updates the styling of active button
    document.querySelectorAll('.speed-btn').forEach(btn => {
        const isSelected = parseInt(btn.getAttribute('data-speed')) === speed;
        btn.classList.toggle('bg-teal-600', isSelected);
        btn.classList.toggle('bg-gray-200', !isSelected);
        btn.classList.toggle('dark:bg-gray-700', !isSelected);


        btn.classList.toggle('text-black', isSelected);
        btn.classList.toggle('text-white', false);

        btn.classList.toggle('text-gray-700', !isSelected);
        btn.classList.toggle('dark:text-gray-200', !isSelected);
    });

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
        console.log(`Simulation speed updated to ${speed}x`);
    } catch (error) {
        console.error('Error updating simulation speed:', error);
    }
};

initSimulationControls();

function simulationLoop() {
    if (getIsPlaying()) {

        let time = getCurrentTime();
        if (time < 100) { // MAX_TIME check
            setCurrentTime(time + 1);
            syncTimelineUI();
        } else {
        }
    }
    requestAnimationFrame(simulationLoop);
}
// Code to make function global for inline clicks
window.setSimulationSpeed = setSimulationSpeed;