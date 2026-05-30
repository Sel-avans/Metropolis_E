export const setSimulationSpeed = async (speed) => {
    
    document.getElementById('active-speed-display').innerText = speed + '×';
    
    // Update de knop die actief is qua kleur
    document.querySelectorAll('.speed-btn').forEach(btn => {
        const isSelected = parseInt(btn.getAttribute('data-speed')) === speed;
        btn.classList.toggle('bg-teal-600', isSelected);
        btn.classList.toggle('text-white', isSelected);
        btn.classList.toggle('bg-gray-200', !isSelected);
        btn.classList.toggle('dark:bg-gray-700', !isSelected);
    });

    try {
        const response = await fetch('/api/simulation/speed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ speed: speed })
        });
        if (!response.ok) throw new Error('Failed to update speed');
    } catch (error) {
        console.error('Error updating simulation speed:', error);
    }
};

// Functie globaal beschikbaar voor inline onclicks
window.setSimulationSpeed = setSimulationSpeed;