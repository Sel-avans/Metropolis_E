<x-app-layout>
    {{-- FIX: Wrapped the page in a responsive container so content doesn't touch screen edges on mobile --}}
    <div class="max-w-7xl mx-auto mt-6 lg:mt-10 px-4 sm:px-6 lg:px-8 mb-10">

        <h1 class="text-2xl font-bold mb-2 dark:text-teal-500">Effects Management</h1>

        <a href="{{ url('/grid') }}"
           class="inline-flex items-center gap-1 mb-4 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition">
            <span class="text-lg">←</span>
            <span>Back</span>
        </a>

        <p class="mb-4 dark:text-white text-sm sm:text-base">Manage here how each function effects the QoL-categories.</p>

        {{-- FIX: Added overflow-x-auto to make the table scroll horizontally on small screens --}}
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto shadow-sm bg-white dark:bg-gray-900">
            {{-- FIX: Added min-w-[600px] to prevent the columns from squishing too much --}}
            <table class="w-full min-w-[600px] border-collapse">
                <thead class="bg-gray-400 dark:bg-gray-800 dark:text-white">
                    <tr>
                        <th class="border-b dark:border-gray-700 p-2 sm:p-3 text-left font-semibold text-xs sm:text-sm">Functions</th>

                        @foreach($categories as $cat)
                            <th class="border-b dark:border-gray-700 p-2 sm:p-3 text-center font-semibold capitalize text-xs sm:text-sm">{{ $cat }}</th>
                        @endforeach

                        <th class="border-b dark:border-gray-700 p-2 sm:p-3 text-center font-semibold text-xs sm:text-sm">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($functions as $function)
                        <tr data-row="{{ $function->id }}" class="hover:bg-gray-300 hover:dark:bg-indigo-950/50 transition">
                            <td class="p-2 sm:p-3 font-medium flex items-center gap-2 sm:gap-3 dark:text-white text-xs sm:text-sm">
                                {{-- FIX: Slightly smaller icon on mobile --}}
                                <img src="{{ asset($function->image) }}" alt="{{ $function->name }}" class="w-6 h-6 sm:w-8 sm:h-8 object-contain pointer-events-none">
                                {{ $function->name }}
                            </td>

                            @foreach($categories as $cat)
                                @php
                                    $effect = $function->effects->firstWhere('category', $cat);
                                    $value = $effect ? $effect->value : 0;
                                @endphp

                                <td class="p-2 sm:p-3 text-center">
                                    {{-- FIX: Reduced gap on mobile so the buttons fit better --}}
                                    <div class="flex items-center justify-center gap-1 sm:gap-3">

                                        <button type="button" class="minus-btn w-7 h-7 flex items-center justify-center 
                                            bg-red-500 hover:bg-red-600 text-white rounded-md transition shadow-sm shrink-0"
                                            data-category="{{ $cat }}"
                                            aria-label="Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}"
                                            title="Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}">
                                            <span class="sr-only">Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}</span>–
                                        </button>

                                        <span class="effect-value w-6 sm:w-8 inline-block text-center font-semibold dark:text-white text-xs sm:text-sm"
                                              data-category="{{ $cat }}"
                                              data-original="{{ $value }}">
                                            {{ $value }}
                                        </span>

                                        <button type="button" class="plus-btn w-7 h-7 flex items-center justify-center 
                                            bg-green-500 hover:bg-green-600 text-white rounded-md transition shadow-sm shrink-0"
                                            data-category="{{ $cat }}"
                                            aria-label="Increase {{ ucfirst($cat) }} effect for {{ $function->name }}"
                                            title="Increase {{ ucfirst($cat) }} effect for {{ $function->name }}">
                                            <span class="sr-only">Increase {{ ucfirst($cat) }} effect for {{ $function->name }}</span>+
                                        </button>

                                    </div>
                                </td>
                            @endforeach

                            <td class="p-2 sm:p-3 text-center">
                                <button class="save-row-btn px-3 py-1.5 sm:px-4 bg-blue-600 hover:bg-blue-700 
                                    text-white text-xs sm:text-sm rounded-md shadow-sm transition"
                                    data-id="{{ $function->id }}"
                                    aria-label="Save effects for {{ $function->name }}">
                                    Save
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div id="toast"
             class="fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50 text-sm">
            ✔ Effects succesfully saved!
        </div>

        <div id="toast-nochange"
             class="fixed top-4 right-4 bg-gray-700 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50 text-sm">
            No changes to save
        </div>

        <div id="effects-config" data-update-url="{{ route('effects.update') }}" class="hidden"></div>

    </div>

    <script>
        document.addEventListener('click', async function (e) {

            if (e.target.classList.contains('plus-btn')) {
                const span = e.target.closest('tr')
                    .querySelector(`.effect-value[data-category="${e.target.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value < 5) value++;

                span.textContent = value;
            }

            if (e.target.classList.contains('minus-btn')) {
                const span = e.target.closest('tr')
                    .querySelector(`.effect-value[data-category="${e.target.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value > -5) value--;

                span.textContent = value;
            }

            if (e.target.classList.contains('save-row-btn')) {

                const row = e.target.closest('tr');
                const functionId = e.target.dataset.id;

                let effects = {};
                let hasChanges = false;

                row.querySelectorAll('.effect-value').forEach(span => {
                    const category = span.dataset.category;
                    const original = Number(span.dataset.original);
                    const current = Number(span.textContent.trim());

                    effects[category] = current;

                    if (current !== original) hasChanges = true;
                });

                if (!hasChanges) {
                    const toast = document.getElementById('toast-nochange');
                    toast.style.opacity = 1;
                    setTimeout(() => toast.style.opacity = 0, 1500);
                    return;
                }

                const confirmed = confirm("Are you sure you want to save this effect(s)?");
                if (!confirmed) {

                    row.querySelectorAll('.effect-value').forEach(span => {
                        span.textContent = span.dataset.original;
                    });

                    return;
                }

                const response = await fetch(
                    document.getElementById('effects-config').dataset.updateUrl,
                    {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            function_id: functionId,
                            effects: effects
                        })
                    }
                );

                if (response.ok) {
                    // Update original values
                    row.querySelectorAll('.effect-value').forEach(span => {
                        span.dataset.original = span.textContent.trim();
                    });

                    const toast = document.getElementById('toast');
                    toast.style.opacity = 1;
                    setTimeout(() => toast.style.opacity = 0, 1500);
                }
            }
        });
    </script>
</x-app-layout>