/** Simulation minutes at which night begins (24:00 = 1080 min after 06:00 offset). */
export const NIGHT_START_MINUTES = 1080;

/** Full cycle length (06:00 → next 06:00). */
export const CYCLE_LENGTH_MINUTES = 1440;

export const DAY_START_MINUTES = 0;

export function getIsDay(simTime) {
    return simTime < NIGHT_START_MINUTES;
}

let _lastWasDay = 'unset';
let _lastFullCycle = null;

/**
 * Update the day/night badge (single implementation for grid + simulation).
 * @param {number} simTime Simulation minutes (0 = 06:00)
 * @param {boolean} fullCycle When true, night segment is shown; otherwise day-only (06:00–24:00)
 */
export function updateDayNightIndicator(simTime, fullCycle = false) {
    const indicator = document.getElementById('day-night-indicator');
    if (!indicator) return;

    const isDay = fullCycle ? getIsDay(simTime) : true;
    if (_lastWasDay === isDay && _lastFullCycle === fullCycle) return;

    const wasUnset = _lastWasDay === 'unset';
    _lastWasDay = isDay;
    _lastFullCycle = fullCycle;

    indicator.dataset.state = isDay ? 'day' : 'night';
    indicator.dataset.fullCycle = fullCycle ? 'true' : 'false';

    const dayEl   = indicator.querySelector('[data-day]');
    const nightEl = indicator.querySelector('[data-night]');
    if (dayEl)   dayEl.classList.toggle('hidden', !isDay);
    if (nightEl) nightEl.classList.toggle('hidden', isDay);

    const liveEl = document.getElementById('day-night-live');
    if (liveEl) {
        if (!fullCycle) {
            liveEl.textContent = 'Simulation cycle: Day only (06:00 to 24:00)';
        } else {
            liveEl.textContent = isDay
                ? 'Simulation cycle: Day (06:00 to 24:00)'
                : 'Simulation cycle: Night (00:00 to 06:00)';
        }
    }

    if (!wasUnset) {
        indicator.classList.add('scale-110');
        setTimeout(() => indicator.classList.remove('scale-110'), 300);
    }
}

/** Reset cached state (e.g. after full page re-init). */
export function resetDayNightIndicatorState() {
    _lastWasDay = 'unset';
    _lastFullCycle = null;
}
