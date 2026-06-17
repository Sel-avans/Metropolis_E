const HOVER_DELAY_MS = 1000;
const FETCH_TIMEOUT_MS = 1000;

const PREVIEW_CACHE = new Map();

let hoverTimer = null;
let pinnedFunctionId = null;
let activeFunctionId = null;
let previewRequestId = 0;
let suppressClickAfterDrag = false;
let hoveredItem = null;
let lastPointerEvent = null;

function getPreviewElements() {
    return {
        panel: document.getElementById('library-preview-panel'),
        title: document.getElementById('library-preview-title'),
        category: document.getElementById('library-preview-category'),
        icon: document.getElementById('library-preview-icon'),
        effects: document.getElementById('library-preview-effects'),
        conditions: document.getElementById('library-preview-conditions'),
        status: document.getElementById('library-preview-status'),
        body: document.getElementById('library-preview-body'),
        closeBtn: document.getElementById('library-preview-close'),
    };
}

function isVisibleLibraryItem(item) {
    return item
        && item.classList.contains('library-item')
        && !item.classList.contains('hidden');
}

function setSelectedLibraryItem(functionId) {
    document.querySelectorAll('.library-item').forEach((item) => {
        const selected = Number(item.dataset.functionId) === functionId;
        item.classList.toggle('library-item-selected', selected);
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
    });
}

function setPreviewInteractivity(pinned, preferHover = false) {
    const { panel, closeBtn, body } = getPreviewElements();
    if (!panel) return;

    const usePinnedClass = pinned && !preferHover;
    const useHoverClass = !usePinnedClass;

    panel.classList.toggle('library-preview-pinned', usePinnedClass);
    panel.classList.toggle('library-preview-hover', useHoverClass);
    panel.classList.toggle('pointer-events-none', !pinned);
    panel.classList.toggle('pointer-events-auto', pinned);
    if (closeBtn) closeBtn.classList.toggle('hidden', !pinned);
    if (body) {
        body.classList.toggle('overflow-y-auto', usePinnedClass);
        body.classList.toggle('overflow-hidden', useHoverClass);
    }
}

function resetPreviewLayout() {
    const { panel, body } = getPreviewElements();
    if (!panel) return;

    panel.style.top = '';
    panel.style.bottom = '';
    panel.style.maxHeight = '';
    panel.classList.remove('library-preview-pinned', 'library-preview-hover');
    if (body) {
        body.scrollTop = 0;
        body.classList.remove('overflow-y-auto');
        body.classList.add('overflow-hidden');
    }
}

function applyHoverPreviewLayout(item, pointer) {
    const { panel } = getPreviewElements();
    const column = document.getElementById('library-column');
    if (!panel || !column || !item) return;
    const columnRect = column.getBoundingClientRect();
    const itemRect = item.getBoundingClientRect();
    // compute available area and desired top (use pointer Y if available)
    const availableHeight = column.clientHeight - 16; // 8px padding top/bottom
    const defaultMaxHeight = Math.min(columnRect.height * 0.75, 420);

    // ensure panel has layout to measure its height
    const panelHeight = Math.max(0, panel.getBoundingClientRect().height || panel.offsetHeight || defaultMaxHeight);

    let desiredTop;
    if (pointer && typeof pointer.clientY === 'number') {
        desiredTop = column.scrollTop + (pointer.clientY - columnRect.top) + 4;
    } else {
        desiredTop = column.scrollTop + (itemRect.top - columnRect.top) + itemRect.height + 4;
    }

    const minTop = column.scrollTop + 8;
    const maxTop = Math.max(minTop, column.scrollTop + column.clientHeight - Math.min(panelHeight, defaultMaxHeight) - 8);
    const top = Math.min(Math.max(desiredTop, minTop), maxTop);

    // horizontal: fill the parent column
    panel.style.left = '0px';
    panel.style.right = '0px';
    panel.style.width = '100%';

    panel.style.bottom = 'auto';
    panel.style.top = `${top}px`;
    panel.style.maxHeight = `${Math.min(defaultMaxHeight, availableHeight)}px`;
}

function applyPinnedPreviewLayout() {
    const { panel } = getPreviewElements();
    if (!panel) return;

    // pinned defaults to bottom anchored within the library column
    // pinned anchored at bottom and full width of parent column
    panel.style.left = '0px';
    panel.style.right = '0px';
    panel.style.width = '100%';

    panel.style.top = '';
    panel.style.bottom = '0';
    panel.style.maxHeight = '';
}

function showPreviewPanel(pinned = false, item = null, pointer = null) {
    const { panel } = getPreviewElements();
    if (!panel) return;

    if (pinned) {
        // if a pointer is available (click), prefer placing near pointer immediately
        if (pointer) {
            applyHoverPreviewLayout(item, pointer);
            setPreviewInteractivity(true, true);
        } else {
            applyPinnedPreviewLayout();
            setPreviewInteractivity(true, false);
        }
    } else {
        applyHoverPreviewLayout(item, pointer || lastPointerEvent);
        setPreviewInteractivity(false, false);
    }

    panel.classList.remove('hidden');
    panel.setAttribute('aria-hidden', 'false');
}

export function closePreview(force = false) {
    if (pinnedFunctionId && !force) return;

    const { panel, status } = getPreviewElements();
    if (!panel) return;

    if (force) pinnedFunctionId = null;

    panel.classList.add('hidden');
    panel.setAttribute('aria-hidden', 'true');
    resetPreviewLayout();
    setPreviewInteractivity(false);
    if (status) status.textContent = '';
    activeFunctionId = null;
    hoveredItem = null;
    setSelectedLibraryItem(null);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function effectValueClass(tone) {
    switch (tone) {
        case 'positive': return 'library-preview-value-positive';
        case 'negative': return 'library-preview-value-negative';
        case 'missing': return 'library-preview-value-missing';
        default: return 'library-preview-value-neutral';
    }
}

function renderEffects(effects) {
    const { effects: container } = getPreviewElements();
    if (!container) return;

    const rows = (effects ?? []).map((effect) => `
        <tr class="library-preview-effects-row">
            <th scope="row" class="library-preview-effects-label">${escapeHtml(effect.label)}</th>
            <td class="library-preview-effects-value ${effectValueClass(effect.value_tone)}">
                <span aria-label="${escapeHtml(effect.label)} QoL impact">${escapeHtml(effect.display_value)}</span>
            </td>
        </tr>
    `).join('');

    container.innerHTML = `
        <table class="library-preview-table library-preview-effects-table">
            <caption class="sr-only">QoL effects grouped by category</caption>
            <thead>
                <tr>
                    <th scope="col">Category</th>
                    <th scope="col">QoL impact</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

function conditionRowClass(type) {
    return `library-preview-condition-row library-preview-condition-${type}`;
}

function renderConditions(conditions) {
    const { conditions: container } = getPreviewElements();
    if (!container) return;

    const rows = (conditions ?? []).map((condition) => {
        const partner = condition.partner_name
            ? escapeHtml(condition.partner_name)
            : '<span class="library-preview-value-missing">—</span>';

        return `
            <tr class="${conditionRowClass(condition.type)}">
                <td class="library-preview-condition-type">
                    <span class="library-preview-badge library-preview-badge-${escapeHtml(condition.type)}">
                        ${escapeHtml(condition.type_label ?? condition.display_value)}
                    </span>
                </td>
                <td class="library-preview-condition-partner">${partner}</td>
                <td class="library-preview-condition-description">${escapeHtml(condition.description)}</td>
            </tr>
        `;
    }).join('');

    container.innerHTML = `
        <table class="library-preview-table library-preview-conditions-table">
            <caption class="sr-only">Placement conditions for this destination</caption>
            <thead>
                <tr>
                    <th scope="col">Type</th>
                    <th scope="col">Adjacent to</th>
                    <th scope="col">Rule</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>
    `;
}

function renderPreview(data) {
    const { title, category, icon } = getPreviewElements();
    if (!data?.function) return;

    if (title) title.textContent = data.function.name ?? '—';
    if (category) {
        category.textContent = data.function.category
            ? data.function.category.charAt(0).toUpperCase() + data.function.category.slice(1)
            : '—';
    }
    if (icon) {
        icon.src = data.function.image ?? '';
        icon.alt = data.function.name ?? 'Destination preview';
        icon.classList.toggle('hidden', !data.function.image);
    }

    renderEffects(data.effects);
    renderConditions(data.conditions);
}

async function fetchPreview(functionId) {
    if (PREVIEW_CACHE.has(functionId)) {
        return PREVIEW_CACHE.get(functionId);
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

    try {
        const response = await fetch(`/functions/${functionId}/preview`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
            throw new Error(`Preview request failed (${response.status})`);
        }

        const data = await response.json();
        PREVIEW_CACHE.set(functionId, data);

        return data;
    } catch (error) {
        clearTimeout(timeoutId);

        if (error.name === 'AbortError') {
            throw new Error('Preview took too long to load.');
        }

        throw error;
    }
}

async function openPreview(item, pinned = false, pointerEvent = null) {
    const functionId = Number(item.dataset.functionId);
    const { status } = getPreviewElements();
    const requestId = ++previewRequestId;

    if (pinned) {
        pinnedFunctionId = functionId;
    }

    // record pointer for initial placement, then show panel
    if (pointerEvent) lastPointerEvent = pointerEvent;
    showPreviewPanel(pinned, item, lastPointerEvent);
    if (status) status.textContent = 'Loading preview…';

    try {
        const data = await fetchPreview(functionId);

        if (requestId !== previewRequestId) return;

        activeFunctionId = functionId;
        hoveredItem = item;
        setSelectedLibraryItem(functionId);
        renderPreview(data);
        if (status) status.textContent = '';

        if (pinned) {
            // if pointerEvent present, prefer positioning near click
                if (pointerEvent) {
                    applyHoverPreviewLayout(item, pointerEvent);
                } else {
                    applyPinnedPreviewLayout();
                }
        } else {
            applyHoverPreviewLayout(item, pointerEvent);
        }
    } catch (error) {
        if (requestId !== previewRequestId) return;
        if (status) status.textContent = error.message || 'Could not load preview.';
        console.error('Library preview error:', error);
    }
}

function scheduleHoverPreview(item, pointerEvent = null) {
    clearTimeout(hoverTimer);
    const functionId = Number(item.dataset.functionId);
    lastPointerEvent = pointerEvent;

    fetchPreview(functionId).catch(() => {});

    hoverTimer = setTimeout(() => {
        if (pinnedFunctionId) return;
        openPreview(item, false, lastPointerEvent);
    }, HOVER_DELAY_MS);
}

function cancelHoverPreview() {
    clearTimeout(hoverTimer);
}

function resolveLibraryItem(target) {
    return target?.closest?.('.library-item') ?? null;
}

export function initLibraryPreview() {
    const libraryList = document.getElementById('library-list');
    const libraryColumn = document.getElementById('library-column');
    const { panel, closeBtn } = getPreviewElements();

    if (!libraryList || !libraryColumn || !panel) return;

    document.addEventListener('dragstart', (event) => {
        if (resolveLibraryItem(event.target)) {
            suppressClickAfterDrag = true;
            closePreview(true);
        }
    });

    document.addEventListener('dragend', () => {
        setTimeout(() => {
            suppressClickAfterDrag = false;
        }, 0);
    });

    libraryList.addEventListener('mouseover', (event) => {
        const item = resolveLibraryItem(event.target);
        if (!isVisibleLibraryItem(item)) return;
        if (pinnedFunctionId && Number(item.dataset.functionId) !== pinnedFunctionId) return;

        if (hoveredItem !== item) {
            hoveredItem = item;
            if (!pinnedFunctionId) {
                scheduleHoverPreview(item, event);
            }
        }
    });

    libraryList.addEventListener('mouseout', (event) => {
        const item = resolveLibraryItem(event.target);
        if (!item) return;

        const related = event.relatedTarget;
        if (related && item.contains(related)) return;
        if (related && panel.contains(related)) return;

        if (hoveredItem === item) {
            hoveredItem = null;
            cancelHoverPreview();
            if (!pinnedFunctionId) {
                previewRequestId += 1;
                closePreview(false);
            }
        }
    });

    libraryList.addEventListener('click', (event) => {
        const item = resolveLibraryItem(event.target);
        if (!isVisibleLibraryItem(item) || suppressClickAfterDrag) return;

        event.preventDefault();
        event.stopPropagation();
        openPreview(item, true, event);
    });

    // NB: er is hier bewust GEEN keydown-listener voor Enter/Space op
    // libraryList. Het selecteren/plaatsen van een item met Enter/Space
    // wordt afgehandeld in grid.js. De preview mag alleen openen via
    // klikken, of via de "i"-toets (die in grid.js item.click() aanroept,
    // wat hierboven door de click-listener wordt opgepakt).

    panel.addEventListener('mouseenter', () => {
        cancelHoverPreview();
    });

    panel.addEventListener('mouseleave', () => {
        if (!pinnedFunctionId) {
            previewRequestId += 1;
            closePreview(false);
        }
    });

    libraryColumn.addEventListener('mouseleave', (event) => {
        const related = event.relatedTarget;
        if (related && libraryColumn.contains(related)) return;

        cancelHoverPreview();
        if (!pinnedFunctionId) {
            previewRequestId += 1;
            closePreview(false);
        }
    });

    // Keep preview positioned when the column scrolls or the window resizes
    libraryColumn.addEventListener('scroll', () => {
        if (!panel || panel.classList.contains('hidden')) return;
        if (panel.classList.contains('library-preview-hover')) {
            applyHoverPreviewLayout(hoveredItem, lastPointerEvent);
        } else {
            applyPinnedPreviewLayout();
        }
    }, { passive: true });

    window.addEventListener('resize', () => {
        if (!panel || panel.classList.contains('hidden')) return;
        if (panel.classList.contains('library-preview-hover')) {
            applyHoverPreviewLayout(hoveredItem, lastPointerEvent);
        } else {
            applyPinnedPreviewLayout();
        }
    });

    closeBtn?.addEventListener('click', (event) => {
        event.stopPropagation();
        previewRequestId += 1;
        closePreview(true);
    });

    document.addEventListener('mousedown', (event) => {
        if (!pinnedFunctionId || panel.classList.contains('hidden')) return;

        const target = event.target;
        if (panel.contains(target)) return;
        if (resolveLibraryItem(target)) return;

        previewRequestId += 1;
        closePreview(true);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
            previewRequestId += 1;
            closePreview(true);
        }
    });
}