/**
 * regulation.js - Controller for simulation controls
 */

// --- State ---
let isPlaying = false;
let currentTime = 0;
const MAX_TIME = 100;

// --- Getters & Setters ---
export const getIsPlaying = () => isPlaying;
export const getCurrentTime = () => currentTime;
export const setCurrentTime = (time) => { 
    currentTime = Math.max(0, Math.min(time, MAX_TIME)); 
};

// --- Initialisatie van knoppen en UI ---
export const initSimulationControls = () => {
    const playPauseBtn = document.getElementById('playPauseBtn');
    const replayBtn = document.getElementById('replayBtn');
    const timeline = document.getElementById('simulation-timeline');
    const forwardBtn = document.getElementById('forwardBtn');
    const reverseBtn = document.getElementById('reverseBtn');

    if (!playPauseBtn || !replayBtn || !timeline) {
        console.error("Simulation control elements not found in DOM");
        return;
    }

    // Toggle Play/Pause
    playPauseBtn.addEventListener('click', () => {
        isPlaying = !isPlaying;
        playPauseBtn.innerHTML = isPlaying ? '&#x23F8;' : '&#x25B6;';
        console.log(`Simulation status changed to: ${isPlaying ? 'PLAYING' : 'PAUSED'}`);
    });

    // Replay (Replay from t=0)
    replayBtn.addEventListener('click', () => {
        currentTime = 0;
        timeline.value = 0;
        isPlaying = true;
        playPauseBtn.innerHTML = '&#x23F8;';
        updateEventsUI([]);
        console.log("Replaying simulation from start");
    });

    // Timeline Scrubbing
    timeline.addEventListener('input', (e) => {
        currentTime = parseInt(e.target.value);
        console.log(`Timeline scrubbed to: ${currentTime}`);
    });

    // Forward
    forwardBtn.addEventListener('click', () => {
        if (currentTime < MAX_TIME) {
            currentTime++;
            timeline.value = currentTime;
            console.log("Forwarding simulation");
        }
    });

    // Reverse
    reverseBtn.addEventListener('click', () => {
        if (currentTime > 0) {
            currentTime--;
            timeline.value = currentTime;
            console.log("Reversing simulation");
        }
    });
};

// --- UI Updates ---
export const updateEventsUI = (activeEvents) => {
    const list = document.getElementById('active-events-list');
    
    if (!list) return;

    if (!activeEvents || activeEvents.length === 0) {
        list.innerHTML = '<li id="no-events-msg">No active events</li>';
        return;
    }

    list.innerHTML = '';
    activeEvents.forEach(event => {
        const li = document.createElement('li');
        li.className = "text-sky-600 dark:text-sky-400 font-medium";
        li.innerText = event;
        list.appendChild(li);
    });
};


export const syncTimelineUI = () => {
    const timeline = document.getElementById('simulation-timeline');
    if (timeline) {
        const time = getCurrentTime(); // Zorg dat deze functie de laatst bijgewerkte tijd teruggeeft
        timeline.value = time;
        
        // DEBUG: Kijk of de waarde in de console verandert
        console.log("Huidige tijd:", time); 
    }
};
