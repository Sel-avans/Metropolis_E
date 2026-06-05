// Haalt preview data op van de server voor een destination
async function fetchPreview(functionId) {
    const response = await fetch(`/functions/${functionId}/preview`);
    if (!response.ok) return null;
    return response.json();
}

// de HTML voor effects en conditions
function renderPreview(data) {
    let html = '';

    // Effects sectie
    if (data.effects && data.effects.length > 0) {
        html += '<p class="font-semibold mb-1 dark:text-white">Effects:</p><ul class="mb-3 space-y-0.5">';
        data.effects.forEach(effect => {
            const sign = effect.value >= 0 ? '+' : '';
            const color = effect.value >= 0 ? 'text-green-600' : 'text-red-500';
            html += `<li class="flex justify-between">
                        <span class="capitalize">${effect.category}</span>
                        <span class="font-bold ${color}">${sign}${effect.value}</span>
                     </li>`;
        });
        html += '</ul>';
    } else {
        html += '<p class="text-gray-400 text-xs mb-3">Geen effects.</p>';
    }

    // Conditions sectie
    if (data.conditions && data.conditions.length > 0) {
        html += '<p class="font-semibold mb-1 dark:text-white">Plaatsingsregels:</p><ul class="space-y-0.5">';
        data.conditions.forEach(condition => {
            const color = condition.type === 'bonus'
                ? 'text-green-600'
                : condition.type === 'penalty'
                    ? 'text-red-500'
                    : 'text-orange-500';
            html += `<li class="${color} capitalize">${condition.type} naast ${condition.with}</li>`;
        });
        html += '</ul>';
    } else {
        html += '<p class="text-gray-400 text-xs">Geen plaatsingsregels.</p>';
    }

    return html;
}

// Hulpfunctie om de popup te positioneren ten opzichte van de muis en schermranden
function positionPreview(event) {
    const panel = document.getElementById('library-preview');
    if (!panel) return;
    
    const offsetMouseX = 20; // Aantal pixels rechts van de muis
    const offsetMouseY = 10; // Standaard aantal pixels onder de muis
    
    // Bereken de gewenste posities op basis van de muis
    let targetLeft = event.clientX + offsetMouseX;
    let targetTop = event.clientY + offsetMouseY;
    
    // Verkrijg de actuele hoogte en breedte van de geopende popup kaart
    const popupHeight = panel.offsetHeight;
    const popupWidth = panel.offsetWidth;
    
    // BOTSINGSDETECTIE ONDERKANT: Als de popup onder het browserscherm zou vallen:
    if (targetTop + popupHeight > window.innerHeight) {
        // Klap de popup omhoog en toon hem BOVEN de muiscursor
        targetTop = event.clientY - popupHeight - 10; 
    }
    
    // BOTSINGSDETECTIE RECHTERKANT (voor smalle schermen):
    if (targetLeft + popupWidth > window.innerWidth) {
        // Toon de popup aan de linkerkant van de muiscursor
        targetLeft = event.clientX - popupWidth - 20;
    }

    // Extra veiligheid: voorkom dat hij eventueel bóven de bovenkant van het scherm schiet
    if (targetTop < 10) {
        targetTop = 10; 
    }

    panel.style.left = `${targetLeft}px`;
    panel.style.top = `${targetTop}px`;
}

//  het preview paneel met de opgehaalde data
async function openPreview(functionId, event) {
    const panel = document.getElementById('library-preview');
    const title = document.getElementById('preview-title');
    const body  = document.getElementById('preview-body');

    if (!panel) return;

    // Als er een muis-event is meegegeven, positioneer de popup direct
    if (event) {
        positionPreview(event);
    }

    // Toon het paneel en start de fade-in transitie
    panel.classList.remove('hidden');
    // Forceer een reflow zodat de transitie direct werkt
    void panel.offsetWidth;
    panel.classList.remove('opacity-0');
    panel.classList.add('opacity-100');

    body.innerHTML = '<p class="text-gray-400 text-xs">Laden...</p>';

    const data = await fetchPreview(functionId);

    if (!data) {
        body.innerHTML = '<p class="text-red-400 text-xs">Kon data niet laden.</p>';
        return;
    }

    title.textContent = data.name;
    body.innerHTML = renderPreview(data);
    
    // Omdat de content (en dus de hoogte) van de popup zojuist is veranderd na het laden,
    // herberekenen we de positie nogmaals voor het geval hij nu wél buiten het scherm valt.
    if (event) {
        positionPreview(event);
    }
}

// Verbergt het preview paneel met een soepele fade-out
function closePreview() {
    const panel = document.getElementById('library-preview');
    if (!panel) return;

    panel.classList.remove('opacity-100');
    panel.classList.add('opacity-0');

    // Wacht tot de Tailwind transitie (duration-150) klaar is voor we hidden toevoegen
    setTimeout(() => {
        if (panel.classList.contains('opacity-0')) {
            panel.classList.add('hidden');
        }
    }, 150);
}

// Zet hover en click listeners op alle library items
export function initLibraryPreview() {
    const panel   = document.getElementById('library-preview');
    const closeBtn = document.getElementById('preview-close');

    if (!panel || !closeBtn) return;

    // Sluit knop
    closeBtn.addEventListener('click', closePreview);

    // Listeners op elk library item
    document.querySelectorAll('.library-item').forEach(item => {
        const id = item.dataset.functionId;
        let hoverTimer = null;

        // Click opent de preview direct op de muispositie
        item.addEventListener('click', (event) => {
            clearTimeout(hoverTimer);
            openPreview(id, event);
        });

        // Hover opent de preview na korte vertraging
        item.addEventListener('mouseenter', (event) => {
            hoverTimer = setTimeout(() => {
                openPreview(id, event);
            }, 300);
        });

        // Volg de muis zolang de gebruiker over het item beweegt (zorgt voor realtime updates bij scrollen/bewegen)
        item.addEventListener('mousemove', (event) => {
            if (!panel.classList.contains('hidden')) {
                positionPreview(event);
            }
        });

        // Verlaat het item: stop de timer en sluit de preview
        item.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimer);
            closePreview();
        });
    });
}