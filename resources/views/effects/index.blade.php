<x-app-layout>


    <h1 class="text-2xl font-bold mb-2 dark:text-teal-500">Effects Management</h1>

    <a href="{{ url('/grid') }}"
    class="inline-flex items-center gap-1 mb-4 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md shadow transition">
        <span class="text-lg">←</span>
        <span>Terug</span>
    </a>
    
    <p class="mb-4 dark:text-white">Beheer hier hoe elke functie invloed heeft op de QoL‑categorieën.</p>

    {{-- Effects Table --}}
    <table class="w-full border border-gray-200 rounded-lg overflow-hidden shadow-sm">
        <thead class="bg-gray-400 dark:bg-gray-800 dark:text-white">
            <tr>
                <th class="border-b p-3 text-left font-semibold">Functie</th>

                @foreach($categories as $cat)
                    <th class="border-b p-3 text-center font-semibold capitalize">{{ $cat }}</th>
                @endforeach

                <th class="border-b p-3 text-center font-semibold">Actie</th>
            </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">
            @foreach($functions as $function)
                <tr data-row="{{ $function->id }}" data-image="{{ asset($function->image) }}" class="hover:bg-gray-300 hover:dark:bg-indigo-950 transition">
                    <td class="p-3 font-medium flex gap-3 dark:text-white">
                        <img src="{{ asset($function->image) }}" 
                            alt="{{ $function->name }}" 
                            class="w-8 h-8 object-contain pointer-events-none">
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
                            Opslaan
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Toast Notifications --}}
    <div id="toast"
        class="fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50">
        ✔ Effecten succesvol opgeslagen!
    </div>

    <div id="toast-nochange"
        class="fixed top-4 right-4 bg-gray-700 text-white px-4 py-2 rounded-lg shadow-lg opacity-0 pointer-events-none transition-opacity duration-300 z-50">
        Geen wijzigingen om op te slaan
    </div>

    {{-- DOM for Toast --}}
    <div id="effects-config" data-update-url="{{ route('effects.update') }}" class="hidden"></div>

</x-app-layout>