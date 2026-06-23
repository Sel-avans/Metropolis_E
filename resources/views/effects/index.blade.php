<x-app-layout>
    <div class="max-w-7xl mx-auto mt-6 lg:mt-10 px-4 sm:px-6 lg:px-8 mb-10">

        <h1 class="text-2xl font-bold mb-2 dark:text-teal-500">Effects Management</h1>

        <a href="{{ url('/grid') }}"
           class="inline-flex items-center gap-2 mb-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-colors">
            <span class="text-lg leading-none">&larr;</span>
            <span>Back</span>
        </a>

        <p class="mb-6 text-gray-600 dark:text-gray-300 text-sm sm:text-base">
            Manage here how each function effects the QoL-categories.
        </p>

        <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-x-auto shadow-sm bg-white dark:bg-gray-900">
            <table class="w-full min-w-[700px] border-collapse">
                <thead class="bg-gray-100 dark:bg-gray-800 dark:text-white border-b dark:border-gray-700">
                    <tr>
                        <th class="p-3 sm:p-4 text-left font-semibold text-xs sm:text-sm text-gray-700 dark:text-gray-200 w-1/4">Functions</th>

                        @foreach($categories as $cat)
                            <th class="p-3 sm:p-4 text-center font-semibold capitalize text-xs sm:text-sm text-gray-700 dark:text-gray-200">{{ $cat }}</th>
                        @endforeach

                        <th class="p-3 sm:p-4 text-center font-semibold text-xs sm:text-sm text-gray-700 dark:text-gray-200 w-24">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($functions as $function)
                        <tr data-row="{{ $function->id }}" class="hover:bg-gray-50 hover:dark:bg-indigo-950/30 transition-colors">
                            <td class="p-3 sm:p-4 font-medium flex items-center gap-3 dark:text-white text-xs sm:text-sm">
                                <img src="{{ asset($function->image) }}" alt="{{ $function->name }}" class="w-8 h-8 object-contain pointer-events-none drop-shadow-sm">
                                <span class="truncate">{{ $function->name }}</span>
                            </td>

                            @foreach($categories as $cat)
                                @php
                                    $effect = $function->effects->firstWhere('category', $cat);
                                    $value = $effect ? $effect->value : 0;
                                @endphp

                                <td class="p-2 sm:p-3 text-center">
                                    <div class="flex items-center justify-center gap-2">

                                    <button type="button" class="minus-btn w-8 h-8 flex items-center justify-center 
    bg-red-500 hover:bg-red-600 text-white rounded-md transition-all shadow-sm active:scale-95 shrink-0"
    data-category="{{ $cat }}"
    aria-label="Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}"
    title="Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}">
    <span class="sr-only">Decrease {{ ucfirst($cat) }} effect for {{ $function->name }}</span>
    <span class="font-bold text-lg leading-none pointer-events-none">&minus;</span>
</button>

                                        <span class="effect-value w-8 inline-block text-center font-bold text-gray-800 dark:text-white text-sm sm:text-base"
                                              data-category="{{ $cat }}"
                                              data-original="{{ $value }}">
                                            {{ $value }}
                                        </span>

                                        <button type="button" class="plus-btn w-8 h-8 flex items-center justify-center 
    bg-green-500 hover:bg-green-600 text-white rounded-md transition-all shadow-sm active:scale-95 shrink-0"
    data-category="{{ $cat }}"
    aria-label="Increase {{ ucfirst($cat) }} effect for {{ $function->name }}"
    title="Increase {{ ucfirst($cat) }} effect for {{ $function->name }}">
    <span class="sr-only">Increase {{ ucfirst($cat) }} effect for {{ $function->name }}</span>
    <span class="font-bold text-lg leading-none pointer-events-none">&plus;</span>
</button>
                                    </div>
                                </td>
                            @endforeach

                            <td class="p-3 sm:p-4 text-center">
                                <button class="save-row-btn w-full px-3 py-2 bg-blue-600 hover:bg-blue-700 
                                    text-white font-medium text-xs sm:text-sm rounded-md shadow-sm transition-colors active:scale-95"
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

        {{-- Verbeterde Toasts --}}
        <div id="toast"
             class="fixed bottom-4 right-4 sm:top-4 sm:bottom-auto bg-green-600 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 pointer-events-none transition-opacity duration-300 z-50 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            Effects successfully saved!
        </div>

        <div id="toast-nochange"
             class="fixed bottom-4 right-4 sm:top-4 sm:bottom-auto bg-gray-800 text-white px-6 py-3 rounded-lg shadow-xl opacity-0 pointer-events-none transition-opacity duration-300 z-50 text-sm font-medium flex items-center gap-2">
             <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            No changes to save.
        </div>

        <div id="effects-config" data-update-url="{{ route('effects.update') }}" class="hidden"></div>

    </div>

    <script>
        document.addEventListener('click', async function (e) {

            // Fix: Check if the click was on the button OR inside the button (like the icon)
            const plusBtn = e.target.closest('.plus-btn');
            const minusBtn = e.target.closest('.minus-btn');
            const saveBtn = e.target.closest('.save-row-btn');

            if (plusBtn) {
                const span = plusBtn.closest('tr')
                    .querySelector(`.effect-value[data-category="${plusBtn.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value < 5) value++;

                span.textContent = value;
            }

            if (minusBtn) {
                const span = minusBtn.closest('tr')
                    .querySelector(`.effect-value[data-category="${minusBtn.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value > -5) value--;

                span.textContent = value;
            }

            if (saveBtn) {
                const row = saveBtn.closest('tr');
                const functionId = saveBtn.dataset.id;

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
                    setTimeout(() => toast.style.opacity = 0, 2000);
                    return;
                }

                const confirmed = confirm("Are you sure you want to save this effect(s)?");
                if (!confirmed) {
                    row.querySelectorAll('.effect-value').forEach(span => {
                        span.textContent = span.dataset.original;
                    });
                    return;
                }

                // Added Basic Error Handling
                try {
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
                        setTimeout(() => toast.style.opacity = 0, 2000);
                    } else {
                        alert("Something went wrong saving the effects. Please try again.");
                    }
                } catch (error) {
                    console.error("Error saving effects:", error);
                    alert("Network error. Please check your connection.");
                }
            }
        });
    </script>
</x-app-layout>