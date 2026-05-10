<x-app-layout>
    <div class="max-w-5xl mx-auto mt-10">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Function Management</h1>

        <a href="/grid"           
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-md shadow">
                ← Terug naar grid
            </a>
        </div>

        <div class="mb-4">
            <a href="{{ route('functions.create') }}"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md shadow">
                + Nieuwe functie
            </a>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-gray-100 border-b">
                    <tr class="h-16">
                        <th class="p-3 text-center font-semibold">Icon</th>
                        <th class="p-3 text-left font-semibold">Naam</th>
                        <th class="p-3 text-left font-semibold">Categorie</th>
                        <th class="p-3 text-center font-semibold">Acties</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($functions as $function)
                    <tr class="h-20 border-b hover:bg-gray-50 transition">
                        <td class="p-3 text-center align-middle">
                            <img src="{{ asset($function->image) }}"
                                class="w-16 h-16 object-contain mx-auto">
                        </td>

                        <td class="p-3 text-left align-middle font-medium">
                            {{ $function->name }}
                        </td>

                        <td class="p-3 text-left align-middle text-gray-700">
                            {{ $function->category }}
                        </td>

                        <td class="p-3 text-center align-middle">
                            <div class="flex items-center justify-center gap-4">

                                <a href="{{ route('functions.edit', $function) }}"
                                class="px-3 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-semibold rounded-md shadow">
                                    Bewerken
                                </a>

                                <form action="{{ route('functions.destroy', $function) }}" method="POST"
                                    onsubmit="return confirm('Weet je zeker dat je deze functie wilt verwijderen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-md shadow">
                                        Verwijderen
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

    </div>
</x-app-layout>
