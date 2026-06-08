const SEARCH_DEBOUNCE_MS = 300;

export function initLibraryFilter() {
    const searchInput = document.getElementById('library-search');
    const noResultsEl = document.getElementById('library-no-results');
    const libraryList = document.getElementById('library-list');

    if (!searchInput || !libraryList) {
        return;
    }

    const items = Array.from(libraryList.querySelectorAll('.library-item'));
    const categoryGroups = Array.from(libraryList.querySelectorAll('.library-category-group'));

    if (items.length === 0) {
        return;
    }

    let debounceTimer = null;

    function nameMatches(item, query) {
        if (!query) {
            return true;
        }

        const name = (item.dataset.functionName || '').toLowerCase();
        return name.includes(query.toLowerCase());
    }

    function applySearch() {
        const nameQuery = searchInput.value.trim();
        let visibleCount = 0;

        items.forEach((item) => {
            const visible = nameMatches(item, nameQuery);
            item.classList.toggle('hidden', !visible);

            if (visible) {
                visibleCount++;
            }
        });

        categoryGroups.forEach((group) => {
            const hasVisibleItem = group.querySelector('.library-item:not(.hidden)') !== null;
            group.classList.toggle('hidden', !hasVisibleItem);
        });

        if (noResultsEl) {
            noResultsEl.classList.toggle('hidden', visibleCount > 0);
        }
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(applySearch, SEARCH_DEBOUNCE_MS);
    }

    searchInput.addEventListener('input', scheduleSearch);
    applySearch();
}
