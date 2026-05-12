export function getNeighborsWithQoL(row, col, effectsTable) {
    const directions = [
        { dr: -1, dc: 0, direction: "up" },
        { dr: 1, dc: 0, direction: "down" },
        { dr: 0, dc: -1, direction: "left" },
        { dr: 0, dc: 1, direction: "right" }
    ];

    const results = [];

    directions.forEach(({ dr, dc, direction }) => {
        const r = Number(row) + dr;
        const c = Number(col) + dc;

        const cell = document.querySelector(
            `.grid-cell[data-row="${r}"][data-col="${c}"]`
        );

        if (!cell) return;

        const img = cell.querySelector("img");
        if (!img) return;

        const functionName = img.alt;
        const qol = effectsTable?.[functionName] ?? 0;

        results.push({
            direction,
            function: functionName,
            qol_effect: qol
        });
    });

    return results;
}