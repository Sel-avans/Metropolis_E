export async function getNeighborsWithQoL(row, col) {
    try {
        const response = await fetch(`/qol/cell/${row}/${col}`);
        if (!response.ok) {
            throw new Error(`Server error: ${response.status}`);
        }
        return await response.json();
    } catch (err) {
        console.error("Fout bij ophalen hover details in neighbours.js:", err);
        return { categories: {}, total_score: 0 };
    }
}