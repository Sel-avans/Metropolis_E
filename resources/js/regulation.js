// --- State ---
let isPlaying = false;
let currentTime = 0;
let maxTime = 100; // Dynamische maximale tijd

// --- Getters & Setters ---
export const getIsPlaying = () => isPlaying;
export const getCurrentTime = () => currentTime;
export const getMaxTime = () => maxTime;

// Setter voor de limiet (aan te roepen vanuit grid.js)
export const setMaxTime = (newMax) => {
    maxTime = Math.max(100, newMax); // Zorg voor een minimum van 100
};

// Eén centrale functie om de tijd in te stellen
export const setCurrentTime = (time) => { 
    currentTime = Math.max(0, Math.min(time, maxTime)); 
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
    });

    // Replay
    replayBtn.addEventListener('click', () => {
        setCurrentTime(0);
        isPlaying = true;
        playPauseBtn.innerHTML = '&#x23F8;';
    });

    // Timeline Scrubbing
    timeline.addEventListener('input', (e) => {
        setCurrentTime(parseInt(e.target.value));
    });

    // Forward & Reverse
    forwardBtn.addEventListener('click', () => setCurrentTime(currentTime + 1));
    reverseBtn.addEventListener('click', () => setCurrentTime(currentTime - 1));
};

// --- UI Updates ---
export const syncTimelineUI = () => {
    const timeline = document.getElementById('simulation-timeline');
    const timeDisplay = document.getElementById('simulation-time-left');
    
    if (timeline) {
        timeline.max = getMaxTime();
        timeline.value = getCurrentTime();
    }

    if (timeDisplay) {
        const remaining = Math.max(0, getMaxTime() - getCurrentTime());
        const mins = Math.floor(remaining / 60);
        const secs = Math.floor(remaining % 60);
        
        timeDisplay.textContent = `Time left: ${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }
};

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