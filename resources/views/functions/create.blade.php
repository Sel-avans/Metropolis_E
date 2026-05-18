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

        <div class="relative">
            <label class="block text-sm text-white font-medium mb-1">Category</label>

            <input
                type="text"
                id="category-input"
                name="category"
                value="{{ old('category') }}"
                class="w-full border rounded px-3 py-2 text-sm bg-gray-100 cursor-pointer"
                autocomplete="off"
                readonly
                required
            >

            <div id="category-dropdown"
                 class="absolute z-20 w-full bg-white border rounded shadow hidden max-h-48 overflow-y-auto">

                @foreach($categories as $cat)
                    <div class="px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer category-option">
                        {{ $cat }}
                    </div>
                @endforeach

                <div class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-100 cursor-pointer font-medium"
                     id="add-new-category">
                    + Add new category
                </div>
            </div>

        </div>

        <div>
            <label class="block text-sm text-white font-medium mb-1">Icon / Image</label>

            <label class="flex items-center gap-3 px-4 py-2 bg-gray-200 hover:bg-gray-300 
               text-gray-800 text-sm rounded-md cursor-pointer w-fit">
                Choose file
                <input type="file" name="icon" accept="image/*" class="hidden" id="fileInput">
            </label>

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
            const label = document.getElementById('fileName');
            if (label) {
                label.textContent = this.files.length ? this.files[0].name : 'Optional. Max 2MB.';
            }
        });

        const input = document.getElementById('category-input');
        const dropdown = document.getElementById('category-dropdown');
        const addNewBtn = document.getElementById('add-new-category');

        input.addEventListener('click', () => {
            dropdown.classList.remove('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.add('hidden');
            }
        });

        document.querySelectorAll('.category-option').forEach(option => {
            option.addEventListener('click', () => {
                input.value = option.textContent.trim();
                dropdown.classList.add('hidden');
            });
        });

        addNewBtn.addEventListener('click', () => {
            const newCat = prompt("Enter a new category name:");

            if (!newCat) return;

            const newOption = document.createElement('div');
            newOption.className = "px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer category-option";
            newOption.textContent = newCat;

            dropdown.insertBefore(newOption, addNewBtn);

            newOption.addEventListener('click', () => {
                input.value = newCat;
                dropdown.classList.add('hidden');
            });

            input.value = newCat;
            dropdown.classList.add('hidden');
        });
    </script>

</x-app-layout>
