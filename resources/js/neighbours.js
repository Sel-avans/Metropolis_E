export async function getNeighborsWithQoL(row, col, activeEventIds = []) {
    try {
        const query = `?active_event_ids=${(activeEventIds || []).join(',')}`;
        const response = await fetch(`/qol/cell/${row}/${col}${query}`, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return await response.json();
    } catch (err) {
        console.error("Fout bij ophalen hover details in neighbours.js:", err);
        return { categories: {}, total_score: 0 };
    }
}
