import { getIsDay, updateDayNightIndicator, resetDayNightIndicatorState } from './day-night-indicator.js';

export { getIsDay, resetDayNightIndicatorState };

// --- Constanten ---
export const TOTAL_MINUTES = 1440;
export const SIMULATION_DURATION_S = 48; // 48 seconden = 24 hours
export const MINUTES_PER_SECOND = TOTAL_MINUTES / SIMULATION_DURATION_S; // 30 min/sec
export const START_OFFSET_MINUTES = 360; // Simulatie starts at 06:00

// --- Dag/Nacht duur configuratie ---
export const DEFAULT_DAY_HOURS  = 18; // 06:00 – 24:00
export const DEFAULT_NIGHT_HOURS = 6; // 00:00 – 06:00
export const HOURS_MIN = 1;
export const HOURS_MAX = 23;

// Laad opgeslagen dag-uren uit localStorage (of gebruik default)
const _storedDay = parseInt(localStorage.getItem('sim_day_hours'), 10);
let _dayHours = (!isNaN(_storedDay) && _storedDay >= HOURS_MIN && _storedDay <= HOURS_MAX)
    ? _storedDay
    : DEFAULT_DAY_HOURS;

export const getDayHours        = () => _dayHours;
export const getNightHours      = () => 24 - _dayHours;
export const getNightStartMinutes = () => _dayHours * 60;

/**
 * Stel de dag-duur in (1–23 uur). Nacht wordt automatisch 24 - dagHours.
 * Slaat de waarde op in localStorage en past maxTime direct aan.
 */
export const setDayNightDuration = (dayHours) => {
    const clamped = Math.max(HOURS_MIN, Math.min(HOURS_MAX, Math.round(dayHours)));
    _dayHours = clamped;
    localStorage.setItem('sim_day_hours', String(clamped));
    maxTime = fullCycleMode ? TOTAL_MINUTES : getNightStartMinutes();
    if (currentTime > maxTime) {
        currentTime = maxTime;
    }
};

// --- State ---
let isPlaying = false;
let currentTime = 0;      // in minuten (0–1440), 0 = 06:00
let maxTime = getNightStartMinutes();
let fullCycleMode = false;

// --- Getters & Setters ---
export const getIsPlaying   = () => isPlaying;
export const getCurrentTime = () => currentTime;
export const getMaxTime     = () => maxTime;
export const getFullCycleMode = () => fullCycleMode;

export const setIsPlaying = (playing) => {
    isPlaying = Boolean(playing);
};

export const setFullCycleMode = (enabled) => {
    fullCycleMode = Boolean(enabled);
    maxTime = fullCycleMode ? TOTAL_MINUTES : getNightStartMinutes();
    if (currentTime > maxTime) {
        currentTime = maxTime;
    }
};

export const setMaxTime = () => {
    maxTime = fullCycleMode ? TOTAL_MINUTES : getNightStartMinutes();
};

export const setCurrentTime = (time) => {
    currentTime = Math.max(0, Math.min(time, maxTime));
};

export const syncPlayPauseUI = () => {
    const playPauseBtn = document.getElementById('playPauseBtn');
    if (playPauseBtn) {
        if (isPlaying) {
            playPauseBtn.innerHTML = '<span aria-hidden="true">&#x23F8;</span>';
            playPauseBtn.setAttribute('aria-label', 'Pause simulation');
        } else {
            playPauseBtn.innerHTML = '<span aria-hidden="true">&#x25B6;</span>';
            playPauseBtn.setAttribute('aria-label', 'Play simulation');
        }
    }
};

// Hulpfunctie: simulatieminuten (0–1440) → weergavetijd als "HH:MM"
// 0 → "06:00", 360 → "12:00", 1080 → "24:00" → "00:00", 1440 → "06:00"
export const minutesToHHMM = (minutes) => {
    const total = (minutes + START_OFFSET_MINUTES) % TOTAL_MINUTES;
    const h = Math.floor(total / 60);
    const m = Math.floor(total % 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
};

// Hulpfunctie: "HH:MM" datetime string → simulatieminuten (0–1440)
// Compenseert voor de 6:00 startoffset
export const datetimeToSimMinutes = (datetimeStr) => {
    const date = new Date(datetimeStr);
    const h = date.getHours();
    const m = date.getMinutes();
    const totalMinutes = h * 60 + m;
    return ((totalMinutes - START_OFFSET_MINUTES) + TOTAL_MINUTES) % TOTAL_MINUTES;
};

// Visuele update van de actieve speed-knop
const updateSpeedUI = (selectedSpeed) => {
    document.querySelectorAll('.speed-btn').forEach(btn => {
        const speed = parseInt(btn.getAttribute('data-speed'));
        const isSelected = speed === selectedSpeed;

        btn.classList.toggle('bg-teal-600',        isSelected);
        btn.classList.toggle('text-black',         isSelected);
        btn.classList.toggle('bg-gray-200',        !isSelected);
        btn.classList.toggle('dark:bg-gray-700',   !isSelected);
        btn.classList.toggle('text-gray-700',      !isSelected);
        btn.classList.toggle('dark:text-gray-200', !isSelected);

        btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });
};

// --- Initialisatie knoppen ---
export const initSimulationControls = () => {
    const playPauseBtn = document.getElementById('playPauseBtn');
    const replayBtn    = document.getElementById('replayBtn');
    const timeline     = document.getElementById('simulation-timeline');
    const forwardBtn   = document.getElementById('forwardBtn');
    const reverseBtn   = document.getElementById('reverseBtn');

    if (!playPauseBtn || !replayBtn || !timeline) {
        console.error("Simulation control elements not found in DOM");
        return;
    }

    timeline.max   = maxTime;
    timeline.value = 0;

    playPauseBtn.addEventListener('click', () => {
        isPlaying = !isPlaying;
        syncPlayPauseUI();
    });

    replayBtn.addEventListener('click', () => {
        setCurrentTime(0);
        isPlaying = false;
        syncPlayPauseUI();
        syncTimelineUI();
    });

    timeline.addEventListener('input', (e) => {
        setCurrentTime(parseInt(e.target.value));
        syncTimelineUI();
    });

    forwardBtn.addEventListener('click', () => {
        setCurrentTime(currentTime + MINUTES_PER_SECOND);
        syncTimelineUI();
    });

    reverseBtn.addEventListener('click', () => {
        setCurrentTime(currentTime - MINUTES_PER_SECOND);
        syncTimelineUI();
    });

    document.querySelectorAll('.speed-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const speed = parseInt(btn.getAttribute('data-speed'));
            updateSpeedUI(speed);
        });
    });

    updateSpeedUI(1);
};

// --- UI sync ---
export const syncTimelineUI = () => {
    const timeline    = document.getElementById('simulation-timeline');
    const timeDisplay = document.getElementById('simulation-time-display');
    const endLabel    = document.getElementById('timeline-end-label');
    const timeLabel   = minutesToHHMM(currentTime);

    if (timeline) {
        timeline.max   = maxTime;
        timeline.value = currentTime;

        timeline.setAttribute('aria-valuemax', String(maxTime));
        timeline.setAttribute('aria-valuenow', Math.round(currentTime));
        timeline.setAttribute('aria-valuetext', timeLabel);
    }

    if (timeDisplay) {
        timeDisplay.textContent = timeLabel;
    }

    if (endLabel) {
        // Eindlabel toont de dynamische nacht-starttijd of 06:00 bij full cycle
        endLabel.textContent = fullCycleMode ? '06:00' : minutesToHHMM(getNightStartMinutes());
    }

    updateDayNightIndicator(currentTime, fullCycleMode);
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

// --- Day / Night ---
export const DAY_START_MINUTES = 0; // 06:00