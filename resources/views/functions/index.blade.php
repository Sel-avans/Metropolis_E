<x-app-layout>
    <div class="max-w-5xl mx-auto mt-10">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold dark:text-teal-500">Function Management</h1>

            <a href="{{ url('/grid') }}"
                class="inline-flex items-center gap-1 mb-4 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md transition">
                <span class="text-lg">←</span>
                <span>Back to grid</span>
            </a>
        </div>

        <div class="mb-4">
            <a href="{{ route('functions.create') }}"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium text-sm rounded-md shadow">
                + New function
            </a>
        </div>

        <div class="shadow rounded-lg border dark:border-white overflow-hidden">
            <table class="w-full border-collapse">
                <thead class="bg-gray-400 dark:bg-gray-800 dark:text-white border-b">
                    <tr class="h-16">
                        <th class="p-3 text-center font-semibold">Icon</th>
                        <th class="p-3 text-left font-semibold">Name</th>
                        <th class="p-3 text-left font-semibold">Category</th>
                        <th class="p-3 text-center font-semibold">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($functions as $function)
                        <tr class="h-20 border-b hover:bg-gray-300 hover:dark:bg-indigo-950 transition">
                            <td class="p-3 text-center align-middle">
                                <img src="{{ asset($function->image) }}" alt="{{ $function->name }}"
                                    class="w-16 h-16 object-contain mx-auto">
                            </td>

                            <td class="p-3 text-left text-white align-middle font-medium">
                                {{ $function->name }}
                            </td>

                            <td class="p-3 text-left align-middle text-gray-300">
                                {{ ucfirst($function->category) }}
                            </td>

                            <td class="p-3 text-center align-middle">
                                <div class="flex items-center justify-center gap-4">

                                    <a href="{{ route('functions.edit', $function) }}"
                                        class="px-3 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-semibold rounded-md shadow">
                                        Modify
                                    </a>

                                    <form action="{{ route('functions.destroy', $function) }}" method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this function?');">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-md shadow">
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