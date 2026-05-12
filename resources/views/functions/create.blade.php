<x-app-layout>
    <h1 class="text-2xl text-teal-500 font-bold mb-6">Create New Function</h1>

    @if($errors->any())
        <div class="mb-4 px-4 py-2 bg-red-100 text-red-800 rounded">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('functions.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
        @csrf

        <div>
            <label class="block text-sm text-white font-medium mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name') }}"
                class="w-full border rounded px-3 py-2 text-sm" required>
        </div>

        <div>
            <label class="block text-sm text-white font-medium mb-1">Categorie</label>
            <input list="category-list" name="category" value="{{ old('category') }}"
                class="w-full border rounded px-3 py-2 text-sm" required>
            <datalist id="category-list">
                @foreach($categories as $cat)
                    <option value="{{ $cat }}"></option>
                @endforeach
            </datalist>
            <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">
                Choose an existing categorie or type a new one to create a new categorie.
            </p>
        </div>

        <div>
            <label class="block text-sm text-white font-medium mb-1">Icon / Image</label>

            <label class="flex items-center gap-3 px-4 py-2 bg-gray-200 hover:bg-gray-300 
               text-gray-800 text-sm rounded-md cursor-pointer w-fit">
                Choose file
            <input type="file" name="icon" accept="image/*" class="hidden" id="fileInput">
            <p class="text-xs text-gray-500 dark:text-gray-300 mt-1">Optional. Max 2MB.</p>
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
document.getElementById('fileInput').addEventListener('change', function() {
    document.getElementById('fileName').textContent =
        this.files.length ? this.files[0].name : 'Optional. Max 2MB.';
});
</script>
</x-app-layout>
