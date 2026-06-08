import { getNightStartMinutes, START_OFFSET_MINUTES, TOTAL_MINUTES, minutesToHHMM } from './regulation.js';

/** Full cycle length (06:00 → next 06:00). */
export const CYCLE_LENGTH_MINUTES = 1440;

export const DAY_START_MINUTES = 0;

/**
 * Berekent de nacht-starttijd als HH:MM string op basis van de geconfigureerde dag-duur.
 * Bijv. 18u dag → nacht start om 24:00, 12u dag → nacht start om 18:00.
 */
function getNightStartLabel() {
    return minutesToHHMM(getNightStartMinutes());
}

export function getIsDay(simTime) {
    return simTime < getNightStartMinutes();
}

let _lastWasDay = 'unset';
let _lastFullCycle = null;

/**
 * Update de day/night badge (single implementation voor grid + simulation).
 * @param {number} simTime Simulation minutes (0 = 06:00)
 * @param {boolean} fullCycle Wanneer true, wordt het nacht-segment getoond; anders dag-only
 */
export function updateDayNightIndicator(simTime, fullCycle = false) {
    const indicator = document.getElementById('day-night-indicator');
    if (!indicator) return;

    const isDay = fullCycle ? getIsDay(simTime) : true;
    const nightStartLabel = getNightStartLabel(); // bijv. "24:00" of "18:00"

    // Update altijd de tijd-labels zodat ze meteen kloppen na een duur-wijziging
    _updateTimelabels(indicator, nightStartLabel);

    if (_lastWasDay === isDay && _lastFullCycle === fullCycle) return;

    const wasUnset = _lastWasDay === 'unset';
    _lastWasDay = isDay;
    _lastFullCycle = fullCycle;

    indicator.dataset.state     = isDay ? 'day' : 'night';
    indicator.dataset.fullCycle = fullCycle ? 'true' : 'false';

    const dayEl   = indicator.querySelector('[data-day]');
    const nightEl = indicator.querySelector('[data-night]');
    if (dayEl)   dayEl.classList.toggle('hidden', !isDay);
    if (nightEl) nightEl.classList.toggle('hidden', isDay);

    const liveEl = document.getElementById('day-night-live');
    if (liveEl) {
        if (!fullCycle) {
            liveEl.textContent = `Simulation cycle: Day only (06:00 to ${nightStartLabel})`;
        } else {
            liveEl.textContent = isDay
                ? `Simulation cycle: Day (06:00 to ${nightStartLabel})`
                : `Simulation cycle: Night (${nightStartLabel} to 06:00)`;
        }
    }

    if (!wasUnset) {
        indicator.classList.add('scale-110');
        setTimeout(() => indicator.classList.remove('scale-110'), 300);
    }
}

/**
 * Werkt de zichtbare tijdlabels bij in de dag/nacht-badge.
 * Wordt ook aangeroepen bij duur-wijziging zodat labels direct kloppen.
 */
function _updateTimelabels(indicator, nightStartLabel) {
    const dayEl   = indicator.querySelector('[data-day]');
    const nightEl = indicator.querySelector('[data-night]');

    if (dayEl) {
        const timeEl = dayEl.querySelector('.opacity-60');
        if (timeEl) timeEl.textContent = `(06:00 – ${nightStartLabel})`;
    }
    if (nightEl) {
        const timeEl = nightEl.querySelector('.opacity-60');
        if (timeEl) timeEl.textContent = `(${nightStartLabel} – 06:00)`;
    }
}

/** Reset cached state (bijv. na full page re-init). */
export function resetDayNightIndicatorState() {
    _lastWasDay   = 'unset';
    _lastFullCycle = null;
}