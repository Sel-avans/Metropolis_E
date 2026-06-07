// Houdt bij of de preview vergrendeld is door een klik of toetsenbord-focus
let isLocked = false;

// Haalt preview data op van de server met een harde limiet van 1 seconde
async function fetchPreview(functionId) {
    //  een simpele 1-seconde timeout controller
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 1000);

    try {
        const response = await fetch(`/functions/${functionId}/preview`, { signal: controller.signal });
        clearTimeout(timeoutId);
        if (!response.ok) return null;
        return await response.json();
    } catch (error) {
        clearTimeout(timeoutId);
        return { error: true, message: error.name === 'AbortError' ? 'Preview took too long.' : 'Kon data niet laden.' };
    }
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
    if (!panel || !event) return;
    
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
async function openPreview(functionId, event, shouldLock = false) {
    const panel = document.getElementById('library-preview');
    const title = document.getElementById('preview-title');
    const body  = document.getElementById('preview-body');

    if (!panel) return;

    // Bij een click of keyboard focus activeren de interactie-modus
    if (shouldLock) {
        isLocked = true;
        panel.classList.remove('pointer-events-none');
    } else if (!isLocked) {
        panel.classList.add('pointer-events-none');
    }

    // Als er een muis-event is meegegeven, positioneer de popup direct
    if (event) { //  Volg altijd de muis als er een event is (voorkomt wegschieten bij click lock)
        positionPreview(event);
    } else if (shouldLock) {
        // Vaste fallback positie in het midden-links van het scherm bij keyboard focus/click lock
        panel.style.left = '320px';
        panel.style.top = '150px';
    }

    // Toon het paneel en start de fade-in transitie
    panel.classList.remove('hidden');
    // Forceer een reflow zodat de transitie direct werkt
    void panel.offsetWidth;
    panel.classList.remove('opacity-0');
    panel.classList.add('opacity-100');

    body.innerHTML = '<p class="text-gray-400 text-xs">Laden...</p>';

    const data = await fetchPreview(functionId);

    // Toon een foutmelding bij netwerkfouten of als de server er langer dan 1 seconde over deed
    if (!data || data.error) {
        body.innerHTML = `<p class="text-red-400 text-xs">${data?.message || 'Kon data niet laden.'}</p>`;
        return;
    }

    title.textContent = data.name;
    body.innerHTML = renderPreview(data);
    
    
    if (event) { // Herbereken positie op basis van muis na laden om verspringen te voorkomen
        positionPreview(event);
    }
}

// Verbergt het preview paneel met een soepele fade-out
export function closePreview(force = false) {
    const panel = document.getElementById('library-preview');
    if (!panel) return;

    // Voorkom sluiten bij mouseleave als de preview vergrendeld is door een klik
    if (isLocked && !force) return;

    if (force) isLocked = false;

    panel.classList.remove('opacity-100');
    panel.classList.add('opacity-0');

    // Wacht tot de Tailwind transitie (duration-150) klaar is voor we hidden toevoegen
    setTimeout(() => {
        if (panel.classList.contains('opacity-0')) {
            panel.classList.add('hidden');
            panel.classList.add('pointer-events-none');
        }
    }, 150);
}

// Zet hover en click listeners op alle library items
export function initLibraryPreview() {
    const panel   = document.getElementById('library-preview');
    const closeBtn = document.getElementById('preview-close');

    if (!panel) return;

    // Sluit knop activeert een harde forceer-sluiting
    if (closeBtn) {
        closeBtn.addEventListener('click', () => closePreview(true));
    }

    // Listeners op elk library item
    document.querySelectorAll('.library-item').forEach(item => {
        const id = item.dataset.functionId;
        let hoverTimer = null;

        // Maak items bereikbaar met het toetsenbord (Tab-key)
        item.setAttribute('tabindex', '0');

        // Click opent de preview permanent (isLocked = true)
        item.addEventListener('click', (event) => {
            clearTimeout(hoverTimer);
            openPreview(id, event, true);
        });

        // Hover opent de preview na korte vertraging
        item.addEventListener('mouseenter', (event) => {
            if (isLocked) return;
            hoverTimer = setTimeout(() => {
                openPreview(id, event, false);
            }, 300);
        });

        // Volg de muis zolang de gebruiker over het item beweegt (zorgt voor realtime updates bij scrollen/bewegen)
        item.addEventListener('mousemove', (event) => {
            if (!panel.classList.contains('hidden') && !isLocked) {
                positionPreview(event);
            }
        });

        // Verlaat het item: stop de timer en sluit de preview (behalve bij click-lock)
        item.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimer);
            closePreview(false);
        });

        // Toetsenbord focus (toegankelijkheid)
        item.addEventListener('focus', () => {
            openPreview(id, null, true);
        });

        // Toetsenbord verlies van focus
        item.addEventListener('blur', () => {
            closePreview(true);
        });
    });
}