<x-app-layout>
    <h1 class="text-2xl font-bold mb-6">Nieuwe functie aanmaken</h1>

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
            <label class="block text-sm font-medium mb-1">Naam</label>
            <input type="text" name="name" value="{{ old('name') }}"
                class="w-full border rounded px-3 py-2 text-sm" required>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Categorie</label>
            <input list="category-list" name="category" value="{{ old('category') }}"
                class="w-full border rounded px-3 py-2 text-sm" required>
            <datalist id="category-list">
                @foreach($categories as $cat)
                    <option value="{{ $cat }}"></option>
                @endforeach
            </datalist>
            <p class="text-xs text-gray-500 mt-1">
                Kies een bestaande categorie of typ een nieuwe om een nieuwe categorie te maken.
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Icon / Afbeelding</label>
            <input type="file" name="icon" accept="image/*"
                class="w-full text-sm">
            <p class="text-xs text-gray-500 mt-1">Optioneel. Max 2MB.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-md">
                Opslaan
            </button>
            <a href="{{ route('functions.index') }}"
            class="px-3 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded-md">
                Annuleren
            </a>
        </div>
    </form>
</x-app-layout>
