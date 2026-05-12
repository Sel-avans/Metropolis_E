<x-app-layout>
    <h1 class="text-2xl text-teal-500 font-bold mb-6">Modify Function</h1>

    @if($errors->any())
        <div class="mb-4 px-4 py-2 bg-red-100 text-red-800 rounded">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('functions.update', $function) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm text-white font-medium mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name', $function->name) }}"
                class="w-full border rounded px-3 py-2 text-sm" required>
        </div>

        <div>
            <label class="block text-sm text-white font-medium mb-1">Category</label>
            <input list="category-list" name="category" value="{{ old('category', $function->category) }}"
                class="w-full border rounded px-3 py-2 text-sm" required>

            <datalist id="category-list">
                @foreach($categories as $cat)
                    <option value="{{ $cat }}"></option>
                @endforeach
            </datalist>

            <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">
                Choose an existing category or type a new one to create a new category.
            </p>
        </div>

        <div>
            <label class="block text-sm text-white font-medium mb-1">Current Icon</label>

            @if($function->image)
                <img src="{{ asset($function->image) }}" alt="{{ $function->name }}"
                     class="w-16 h-16 object-contain mb-3">
            @else
                <p class="text-xs text-gray-500 dark:text-gray-300 mb-2">No icon set.</p>
            @endif

            <label class="block text-sm text-white font-medium mb-1">New Icon (optional)</label>

            <div class="mt-2 flex items-center gap-2">
                <label
                    for="icon"
                    class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded cursor-pointer hover:bg-blue-700 transition"
                >
                    Choose file
                </label>

                <span id="file-name" class="text-gray-500 dark:text-gray-300 text-xs">
                    No file chosen
                </span>

                <input
                    id="icon"
                    name="icon"
                    type="file"
                    accept="image/*"
                    class="hidden"
                >
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">Leave empty to keep the current icon.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md">
                Save
            </button>

            <a href="{{ route('functions.index') }}"
               class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded-md">
                Cancel
            </a>
        </div>
    </form>

    <script>
        document.getElementById('icon').addEventListener('change', function () {
            const label = document.getElementById('file-name');
            label.textContent = this.files.length
                ? this.files[0].name
                : 'No file chosen';
        });
    </script>

</x-app-layout>
