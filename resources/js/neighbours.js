/**
 * neighbours.js
 * Haalt QoL-details op voor een cel inclusief buur-effecten en actieve event modifiers.
 * De active_event_ids worden meegegeven zodat de server de juiste simulatiestaat gebruikt.
 */

let _getActiveEventIds = null;

/**
 * Registreer een callback die de huidige actieve event IDs teruggeeft.
 * Wordt aangeroepen vanuit grid.js zodat neighbours.js de simulatiestaat kent.
 */
export function registerActiveEventIdsProvider(fn) {
    _getActiveEventIds = fn;
}

export async function getNeighborsWithQoL(row, col) {
    const ids = _getActiveEventIds ? _getActiveEventIds() : [];
    const qs  = ids.length ? `?active_event_ids=${ids.join(',')}` : '';

    try {
        const response = await fetch(`/qol/cell/${row}/${col}${qs}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            console.error(`QoL cell fetch failed: ${response.status}`);
            return { categories: {}, total_score: 0, event_modifiers: {} };
        }

        return await response.json();
    } catch (err) {
        console.error('Fout bij ophalen cell QoL:', err);
        return { categories: {}, total_score: 0, event_modifiers: {} };
    }
}