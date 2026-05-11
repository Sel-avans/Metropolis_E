<x-app-layout>

    <h1 class="text-2xl font-bold mb-2 dark:text-teal-500">Effects Management</h1>

    <a href="{{ url('/grid') }}"
       class="inline-flex items-center gap-1 mb-4 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition">
        <span class="text-lg">←</span>
        <span>Back</span>
    </a>

    <p class="mb-4 dark:text-white">Manage here how each function effects the QoL-categories.</p>

    <table class="w-full border border-gray-200 rounded-lg overflow-hidden shadow-sm">
        <thead class="bg-gray-400 dark:bg-gray-800 dark:text-white">
            <tr>
                <th class="border-b p-3 text-left font-semibold">Functie</th>

                @foreach($categories as $cat)
                    <th class="border-b p-3 text-center font-semibold capitalize">{{ $cat }}</th>
                @endforeach

                <th class="border-b p-3 text-center font-semibold">Action</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            @foreach($functions as $function)
                <tr data-row="{{ $function->id }}" class="hover:bg-gray-300 hover:dark:bg-indigo-950 transition">
                    <td class="p-3 font-medium flex gap-3 dark:text-white">
                        <img src="{{ asset($function->image) }}" class="w-8 h-8 object-contain pointer-events-none">
                        {{ $function->name }}
                    </td>

                    @foreach($categories as $cat)
                        @php
                            $effect = $function->effects->firstWhere('category', $cat);
                            $value = $effect ? $effect->value : 0;
                        @endphp

                        <td class="p-3 text-center">
                            <div class="flex items-center justify-center gap-3">

                                <button class="minus-btn w-7 h-7 flex items-center justify-center 
                                    bg-red-500 hover:bg-red-600 text-white rounded-md transition shadow-sm"
                                    data-category="{{ $cat }}">–</button>

                                <span class="effect-value w-8 inline-block text-center font-semibold dark:text-white"
                                      data-category="{{ $cat }}"
                                      data-original="{{ $value }}">
                                    {{ $value }}
                                </span>

                                <button class="plus-btn w-7 h-7 flex items-center justify-center 
                                    bg-green-500 hover:bg-green-600 text-white rounded-md transition shadow-sm"
                                    data-category="{{ $cat }}">+</button>

                            </div>
                        </td>
                    @endforeach

                    <td class="p-3 text-center">
                        <button class="save-row-btn px-4 py-1.5 bg-blue-600 hover:bg-blue-700 
                            text-white rounded-md shadow-sm transition"
                            data-id="{{ $function->id }}">
                            Save
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Toasts --}}
    <div id="toast"
         class="fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50">
        ✔ Effecten succesvol opgeslagen!
    </div>

    <div id="toast-nochange"
         class="fixed top-4 right-4 bg-gray-700 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50">
        Geen wijzigingen om op te slaan
    </div>

    <div id="effects-config" data-update-url="{{ route('effects.update') }}" class="hidden"></div>

    {{-- JS --}}
    <script>
        document.addEventListener('click', async function (e) {

            // PLUS
            if (e.target.classList.contains('plus-btn')) {
                const span = e.target.closest('tr')
                    .querySelector(`.effect-value[data-category="${e.target.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value < 5) value++;

                span.textContent = value;
            }

            // MINUS
            if (e.target.classList.contains('minus-btn')) {
                const span = e.target.closest('tr')
                    .querySelector(`.effect-value[data-category="${e.target.dataset.category}"]`);

                let value = Number(span.textContent.trim());
                if (value > -5) value--;

                span.textContent = value;
            }

            // SAVE
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
