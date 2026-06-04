// --- Constanten ---
export const TOTAL_MINUTES = 1440;        // 24 uur in minuten
export const SIMULATION_DURATION_S = 48; // 48 seconden echte tijd = 1 dag
export const MINUTES_PER_SECOND = TOTAL_MINUTES / SIMULATION_DURATION_S; // = 30 min/sec

// --- State ---
let isPlaying = false;
let currentTime = 0;      // in minuten (0–1440)
let maxTime = TOTAL_MINUTES;

// --- Getters & Setters ---
export const getIsPlaying = () => isPlaying;
export const getCurrentTime = () => currentTime;
export const getMaxTime = () => maxTime;

export const setMaxTime = (newMax) => {
    maxTime = TOTAL_MINUTES; // Altijd een volledige dag, ongeacht events
};

export const setCurrentTime = (time) => {
    currentTime = Math.max(0, Math.min(time, maxTime));
};

// Functie om de actieve knop visueel te updaten
const updateSpeedUI = (selectedSpeed) => {
    document.querySelectorAll('.speed-btn').forEach(btn => {
        const speed = parseInt(btn.getAttribute('data-speed'));
        const isSelected = speed === selectedSpeed;

        // Reset/Set voor de actieve knop (Teal)
        btn.classList.toggle('bg-teal-600', isSelected);
        btn.classList.toggle('text-black', isSelected);

        // Reset/Set voor de inactieve knoppen (Gray)
        btn.classList.toggle('bg-gray-200', !isSelected);
        btn.classList.toggle('dark:bg-gray-700', !isSelected);
        btn.classList.toggle('text-gray-700', !isSelected);
        btn.classList.toggle('dark:text-gray-200', !isSelected);
    });
};

// Hulpfunctie: minuten → "HH:MM"
export const minutesToHHMM = (minutes, offsetMinutes = 360) => {
    // We voegen 360 minuten (6 uur) toe aan de tijd
    const totalMinutesWithOffset = (minutes + offsetMinutes) % TOTAL_MINUTES;
    const h = Math.floor(totalMinutesWithOffset / 60);
    const m = Math.floor(totalMinutesWithOffset % 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
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

    // Zet timeline max op 1440 minuten bij initialisatie
    timeline.max = TOTAL_MINUTES;
    timeline.value = 0;

    // Toggle Play/Pause
    playPauseBtn.addEventListener('click', () => {
        isPlaying = !isPlaying;
        playPauseBtn.innerHTML = isPlaying ? '&#x23F8;' : '&#x25B6;';
    });

    // Replay: reset naar begin
    replayBtn.addEventListener('click', () => {
        setCurrentTime(0);
        isPlaying = false;
        playPauseBtn.innerHTML = '&#x23F8;';
        syncTimelineUI();
    });

    // Timeline scrubbing (in minuten)
    timeline.addEventListener('input', (e) => {
        setCurrentTime(parseInt(e.target.value));
        syncTimelineUI();
    });

    // Forward: +30 minuten (= 1 seconde simulatietijd)
    forwardBtn.addEventListener('click', () => {
        setCurrentTime(currentTime + MINUTES_PER_SECOND);
        syncTimelineUI();
    });

    // Reverse: -30 minuten
    reverseBtn.addEventListener('click', () => {
        setCurrentTime(currentTime - MINUTES_PER_SECOND);
        syncTimelineUI();
    });

    const speedButtons = document.querySelectorAll('.speed-btn');
    speedButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const speed = parseInt(btn.getAttribute('data-speed'));

            // 1. Update de visuele UI
            updateSpeedUI(speed);

            // 2. Pas je simulatie-snelheid aan
            // (Bijv: setSimulationSpeed(speed));
            console.log(`Snelheid is nu: ${speed}x`);
        });
    });

    // Optioneel: zet de 1x knop standaard op 'active' bij start
    updateSpeedUI(1);
};

// --- UI Updates ---
export const syncTimelineUI = () => {
    const timeline = document.getElementById('simulation-timeline');
    const timeDisplay = document.getElementById('simulation-time-left');
    const timeNow = document.getElementById('simulation-time-now');

    if (timeline) {
        timeline.max = TOTAL_MINUTES;
        timeline.value = currentTime;
    }

    // Huidig tijdstip tonen
    if (timeNow) {
        timeNow.textContent = minutesToHHMM(currentTime);
    }

    // Tijd display
    if (timeDisplay) {
        timeDisplay.textContent = `Time: ${minutesToHHMM(currentTime)}`;
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