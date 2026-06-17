<x-app-layout>
    <div class="max-w-5xl mx-auto mt-6 lg:mt-10 px-2 sm:px-6 lg:px-8 mb-10">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-bold dark:text-teal-500">Function Management</h1>

            <a href="/grid"           
                class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium rounded-md shadow transition">
                ← Back to grid
            </a>
        </div>

        <div class="mb-4">
            <a href="{{ route('functions.create') }}"
            class="inline-block px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md shadow transition">
                + New function
            </a>
        </div>

        {{-- FIX: overflow-x-auto behouden voor de zekerheid, maar we hebben min-w-[500px] verwijderd zodat hij op het scherm past --}}
        <div class="shadow rounded-lg border dark:border-gray-700 overflow-x-auto bg-white dark:bg-gray-900">
            <table class="w-full border-collapse">
                <thead class="bg-gray-400 dark:bg-gray-800 dark:text-white border-b dark:border-gray-700">
                    <tr class="h-12 sm:h-16">
                        {{-- FIX: Kleinere padding (p-1) en kleinere tekst (text-xs) op mobiel --}}
                        <th class="p-1 sm:p-3 text-center font-semibold text-xs sm:text-base">Icon</th>
                        <th class="p-1 sm:p-3 text-left font-semibold text-xs sm:text-base">Name</th>
                        <th class="p-1 sm:p-3 text-left font-semibold text-xs sm:text-base">Category</th>
                        <th class="p-1 sm:p-3 text-center font-semibold text-xs sm:text-base">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($functions as $function)
                    <tr class="border-b dark:border-gray-700 hover:bg-gray-100 hover:dark:bg-indigo-950/50 transition">
                        <td class="p-1 sm:p-3 text-center align-middle">
                            {{-- FIX: Icoontje is w-8 h-8 op mobiel, zodat hij minder ruimte vreet --}}
                            <img src="{{ asset($function->image) }}"
                                alt="{{ $function->name }}"
                                class="w-8 h-8 sm:w-16 sm:h-16 object-contain mx-auto">
                        </td>

                        <td class="p-1 sm:p-3 text-left text-gray-900 dark:text-white align-middle font-medium text-xs sm:text-base break-words">
                            {{ $function->name }}
                        </td>

                        <td class="p-1 sm:p-3 text-left align-middle text-gray-600 dark:text-gray-300 text-xs sm:text-base break-words">
                            {{ ucfirst($function->category) }}
                        </td>

                        <td class="p-1 sm:p-3 text-center align-middle">
                            {{-- FIX: flex-col zet de knoppen op mobiel onder elkaar! flex-row zet ze op desktop weer naast elkaar --}}
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2">

                                <a href="{{ route('functions.edit', $function) }}"
                                class="w-full sm:w-auto px-2 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-[10px] sm:text-xs font-semibold rounded shadow transition text-center">
                                    Modify
                                </a>

                                <form action="{{ route('functions.destroy', $function) }}" method="POST"
                                    onsubmit="return confirm('Are you sure you want to delete this function?');" class="m-0 w-full sm:w-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="w-full sm:w-auto px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-[10px] sm:text-xs font-semibold rounded shadow transition text-center">
                                        Delete
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