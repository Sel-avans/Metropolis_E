@php
    $selectedCategories = old('category_modifiers', $selectedCategoryModifiers ?? []);
    $selectedFunctionIds = collect(old('city_functions', $selectedCityFunctionIds ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="mt-6 p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-6">
    <h3 class="text-lg font-medium text-gray-900">Event Effects</h3>

    {{-- Effect categories --}}
    <div>
        <label class="block text-gray-700 font-bold mb-2">Effect Categories</label>
        <p class="text-sm text-gray-500 mb-3">Choose at least one category and set its impact (-5 to +5).</p>

        @error('category_modifiers')
            <p class="text-red-500 text-xs italic mb-2">{{ $message }}</p>
        @enderror

        <div class="flex flex-wrap gap-2 mb-3">
            <select id="category_picker"
                    class="shadow border rounded py-2 px-3 text-gray-700 flex-1 min-w-[200px]">
                <option value="">-- Select a category --</option>
                @foreach ($effectCategories as $category)
                    <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                @endforeach
            </select>
            <button type="button" id="add_category_btn"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Add
            </button>
            <button type="button" id="categories_done_btn"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded">
                Done
            </button>
        </div>

        <p id="categories_status" class="text-sm text-gray-500 mb-2 hidden"></p>

        <div id="selected_categories_container" class="space-y-2"></div>
    </div>

    {{-- City functions --}}
    <div id="city_functions_section" class="opacity-50 pointer-events-none">
        <label class="block text-gray-700 font-bold mb-2">City Functions</label>
        <p class="text-sm text-gray-500 mb-3">Choose at least one affected city function.</p>

        @error('city_functions')
            <p class="text-red-500 text-xs italic mb-2">{{ $message }}</p>
        @enderror

        <div class="flex flex-wrap gap-2 mb-3">
            <select id="function_picker"
                    class="shadow border rounded py-2 px-3 text-gray-700 flex-1 min-w-[200px]">
                <option value="">-- Select a city function --</option>
                @foreach ($cityFunctions as $function)
                    <option value="{{ $function->id }}" data-name="{{ $function->name }}">
                        {{ $function->name }}
                    </option>
                @endforeach
            </select>
            <button type="button" id="add_function_btn"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                Add
            </button>
            <button type="button" id="functions_done_btn"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded">
                Done
            </button>
        </div>

        <p id="functions_status" class="text-sm text-gray-500 mb-2 hidden"></p>

        <ul id="selected_functions_container" class="space-y-1 text-sm text-gray-700"></ul>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const categoryPicker = document.getElementById('category_picker');
        const functionPicker = document.getElementById('function_picker');
        const categoriesContainer = document.getElementById('selected_categories_container');
        const functionsContainer = document.getElementById('selected_functions_container');
        const cityFunctionsSection = document.getElementById('city_functions_section');
        const categoriesStatus = document.getElementById('categories_status');
        const functionsStatus = document.getElementById('functions_status');

        const selectedCategories = new Map(Object.entries(@json($selectedCategories)).map(([k, v]) => [k, parseInt(v, 10) || 0]));
        const selectedFunctions = new Map(@json($selectedFunctionIds).map(id => {
            const opt = functionPicker.querySelector(`option[value="${id}"]`);
            return [String(id), opt ? opt.dataset.name : `Function #${id}`];
        }));

        let categoriesDone = selectedCategories.size > 0;
        let functionsDone = selectedFunctions.size > 0;

        function slugify(text) {
            return text.toLowerCase().replace(/[^a-z0-9]+/g, '_');
        }

        function refreshCategoryPicker() {
            Array.from(categoryPicker.options).forEach(opt => {
                if (!opt.value) return;
                opt.hidden = selectedCategories.has(opt.value);
            });
            categoryPicker.value = '';
        }

        function refreshFunctionPicker() {
            Array.from(functionPicker.options).forEach(opt => {
                if (!opt.value) return;
                opt.hidden = selectedFunctions.has(opt.value);
            });
            functionPicker.value = '';
        }

        function renderCategories() {
            categoriesContainer.innerHTML = '';

            selectedCategories.forEach((value, category) => {
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between bg-white p-3 rounded border border-gray-200';
                row.innerHTML = `
                    <span class="font-semibold text-gray-800">${category.charAt(0).toUpperCase() + category.slice(1)}</span>
                    <div class="flex items-center space-x-3">
                        <button type="button" class="cat-minus bg-red-500 hover:bg-red-600 text-white font-bold w-8 h-8 rounded">-</button>
                        <input type="hidden" name="category_modifiers[${category}]" value="${value}" class="cat-value-input">
                        <span class="cat-value-display w-10 text-center font-bold">${value >= 0 ? '+' : ''}${value}</span>
                        <button type="button" class="cat-plus bg-green-500 hover:bg-green-600 text-white font-bold w-8 h-8 rounded">+</button>
                        <button type="button" class="cat-remove text-red-600 hover:text-red-800 text-sm underline ml-2">Remove</button>
                    </div>
                `;

                const hidden = row.querySelector('.cat-value-input');
                const display = row.querySelector('.cat-value-display');

                const setValue = (next) => {
                    const clamped = Math.max(-5, Math.min(5, next));
                    hidden.value = clamped;
                    display.textContent = (clamped >= 0 ? '+' : '') + clamped;
                    selectedCategories.set(category, clamped);
                };

                row.querySelector('.cat-minus').addEventListener('click', () => setValue(parseInt(hidden.value, 10) - 1));
                row.querySelector('.cat-plus').addEventListener('click', () => setValue(parseInt(hidden.value, 10) + 1));
                row.querySelector('.cat-remove').addEventListener('click', () => {
                    selectedCategories.delete(category);
                    categoriesDone = false;
                    updateCategoriesDoneState();
                    renderCategories();
                    refreshCategoryPicker();
                });

                categoriesContainer.appendChild(row);
            });

            refreshCategoryPicker();
        }

        function renderFunctions() {
            functionsContainer.innerHTML = '';

            selectedFunctions.forEach((name, id) => {
                const li = document.createElement('li');
                li.className = 'flex items-center justify-between bg-white p-2 px-3 rounded border border-gray-200';
                li.innerHTML = `
                    <span>${name}</span>
                    <input type="hidden" name="city_functions[]" value="${id}">
                    <button type="button" class="fn-remove text-red-600 hover:text-red-800 text-sm underline">Remove</button>
                `;
                li.querySelector('.fn-remove').addEventListener('click', () => {
                    selectedFunctions.delete(id);
                    functionsDone = false;
                    updateFunctionsDoneState();
                    renderFunctions();
                    refreshFunctionPicker();
                });
                functionsContainer.appendChild(li);
            });

            refreshFunctionPicker();
        }

        function updateCategoriesDoneState() {
            if (categoriesDone && selectedCategories.size > 0) {
                categoriesStatus.textContent = `${selectedCategories.size} categor${selectedCategories.size === 1 ? 'y' : 'ies'} selected.`;
                categoriesStatus.classList.remove('hidden');
                cityFunctionsSection.classList.remove('opacity-50', 'pointer-events-none');
            } else {
                categoriesStatus.classList.add('hidden');
                cityFunctionsSection.classList.add('opacity-50', 'pointer-events-none');
            }
        }

        function updateFunctionsDoneState() {
            if (functionsDone && selectedFunctions.size > 0) {
                functionsStatus.textContent = `${selectedFunctions.size} city function${selectedFunctions.size === 1 ? '' : 's'} selected.`;
                functionsStatus.classList.remove('hidden');
            } else {
                functionsStatus.classList.add('hidden');
            }
        }

        document.getElementById('add_category_btn').addEventListener('click', () => {
            const category = categoryPicker.value;
            if (!category || selectedCategories.has(category)) return;
            selectedCategories.set(category, 0);
            categoriesDone = false;
            updateCategoriesDoneState();
            renderCategories();
        });

        document.getElementById('categories_done_btn').addEventListener('click', () => {
            if (selectedCategories.size === 0) {
                alert('Select at least one effect category.');
                return;
            }
            categoriesDone = true;
            updateCategoriesDoneState();
        });

        document.getElementById('add_function_btn').addEventListener('click', () => {
            const id = functionPicker.value;
            const name = functionPicker.selectedOptions[0]?.dataset.name;
            if (!id || selectedFunctions.has(id)) return;
            selectedFunctions.set(id, name);
            functionsDone = false;
            updateFunctionsDoneState();
            renderFunctions();
        });

        document.getElementById('functions_done_btn').addEventListener('click', () => {
            if (selectedFunctions.size === 0) {
                alert('Select at least one city function.');
                return;
            }
            functionsDone = true;
            updateFunctionsDoneState();
        });

        const eventForm = document.querySelector('form');
        if (eventForm) {
            eventForm.addEventListener('submit', (e) => {
            if (selectedCategories.size === 0) {
                e.preventDefault();
                alert('Select at least one effect category.');
                return;
            }
            if (selectedFunctions.size === 0) {
                e.preventDefault();
                alert('Select at least one city function.');
            }
            });
        }

        renderCategories();
        renderFunctions();
        updateCategoriesDoneState();
        updateFunctionsDoneState();
    });
</script>
