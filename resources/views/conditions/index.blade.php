<x-app-layout>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-5">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold" style="color:#2563eb;">Conditions Management</h1>

    <a href="/grid" class="btn btn-light text-secondary border shadow-sm text-decoration-none">
        ← Back to grid
    </a>

    <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createModal">
        + New rule
    </button>
</div>

    <div class="card shadow-lg border-0" style="border-radius: 14px;">
        <div class="card-header py-3" style="background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 14px 14px 0 0;">
            <h5 class="mb-0 fw-semibold text-white">All adjacency rules</h5>
        </div>

        <div class="card-body p-0">
            <div class="max-h-[70vh] overflow-y-auto">

                <table class="table table-hover align-middle mb-0" style="font-size: 1.1rem;">
                    <thead class="sticky-top" style="background:#f1f5f9;">
                        <tr style="height: 45px;">
                            <th class="ps-4">Function A</th>
                            <th>Function B</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th class="pe-4">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($conditions as $condition)
                            <tr style="height: 55px;">
                                <td class="ps-4 fw-semibold">{{ $condition->functionA->name }}</td>
                                <td class="fw-semibold">{{ $condition->functionB->name }}</td>

                                <td>
                                    <span class="badge px-3 py-2
                                        @if($condition->type === 'bonus') bg-success
                                        @elseif($condition->type === 'penalty') bg-danger
                                        @elseif($condition->type === 'forbidden') bg-dark
                                        @else bg-secondary @endif
                                    ">
                                        {{ ucfirst($condition->type) }}
                                    </span>
                                </td>

                                <td>{{ $condition->value ?? '-' }}</td>

                                <td class="pe-4">
                                    <button class="btn btn-sm btn-outline-warning me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editModal{{ $condition->id }}">
                                        Modify
                                    </button>

                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="confirmDelete('{{ route('conditions.destroy', $condition) }}')">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    {{-- EDIT MODALS --}}
    @foreach($conditions as $condition)
    @php
        $pending = session('pending_data') ?? [];
        $isThisModal = session('edit_id') == $condition->id;
    @endphp

    <div class="modal fade" id="editModal{{ $condition->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="min-height: calc(100% - 3.5rem); display: flex; align-items: center;">
            <form method="POST" action="{{ route('conditions.update', $condition->id) }}" class="edit-form">
                @csrf
                @method('PUT')

                <div class="modal-content shadow-lg" style="border-radius: 14px;">
                    <div class="modal-header" style="background:#2563eb; color:white;">
                        <h5 class="modal-title fw-bold">Modify Rule</h5>
                    </div>

                    @if(session('_last_action') === 'error' && $isThisModal)
                        <div class="alert alert-danger m-3">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="modal-body">

                        {{-- FUNCTION A --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Function A</label>
                            <select name="function_a" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}"
                                        @selected(($pending['function_a'] ?? $condition->function_a) == $f->id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- FUNCTION B --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Function B</label>
                            <select name="function_b" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}"
                                        @selected(($pending['function_b'] ?? $condition->function_b) == $f->id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- TYPE --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <option value="bonus" @selected(($pending['type'] ?? $condition->type) == 'bonus')>Bonus</option>
                                <option value="penalty" @selected(($pending['type'] ?? $condition->type) == 'penalty')>Penalty</option>
                                <option value="forbidden" @selected(($pending['type'] ?? $condition->type) == 'forbidden')>Forbidden</option>
                            </select>
                        </div>

                        {{-- VALUE --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Value</label>
                            <input type="number" name="value" class="form-control"
                                   value="{{ $pending['value'] ?? $condition->value }}">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary save-btn"
                                data-id="{{ $condition->id }}">
                            Save
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>
    @endforeach

    {{-- CREATE MODAL --}}
    @php
        $pending = session('pending_data') ?? [];
    @endphp

    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="min-height: calc(100% - 3.5rem); display: flex; align-items: center;">
            <form method="POST" action="{{ route('conditions.store') }}">
                @csrf

                <div class="modal-content shadow-lg" style="border-radius: 14px;">
                    <div class="modal-header" style="background:#2563eb; color:white;">
                        <h5 class="modal-title fw-bold">Add New Rule</h5>
                    </div>

                    @if(session('_last_action') === 'error' && session('edit_id') === null)
                        <div class="alert alert-danger m-3">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="modal-body">

                        {{-- FUNCTION A --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Function A</label>
                            <select name="function_a" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}"
                                        @selected(($pending['function_a'] ?? '') == $f->id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- FUNCTION B --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Function B</label>
                            <select name="function_b" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}"
                                        @selected(($pending['function_b'] ?? '') == $f->id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- TYPE --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <option value="bonus" @selected(($pending['type'] ?? '') == 'bonus')>Bonus</option>
                                <option value="penalty" @selected(($pending['type'] ?? '') == 'penalty')>Penalty</option>
                                <option value="forbidden" @selected(($pending['type'] ?? '') == 'forbidden')>Forbidden</option>
                            </select>
                        </div>

                        {{-- VALUE --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Value</label>
                            <input type="number" name="value" class="form-control"
                                   value="{{ $pending['value'] ?? '' }}">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Save</button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- DELETE MODAL --}}
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="min-height: calc(100% - 3.5rem); display: flex; align-items: center;">
            <div class="modal-content shadow-lg" style="border-radius: 14px;">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">Delete Rule</h5>
                </div>

                <div class="modal-body">
                    <p class="fw-semibold mb-0">Are you sure you want to delete this rule?</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                    <form id="deleteConfirmForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- SINGLE SCRIPT BLOCK --}}
<script>
function confirmDelete(url) {
    document.getElementById('deleteConfirmForm').action = url;
    new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
}

document.addEventListener('DOMContentLoaded', function () {
    // 1. Zorg dat foutmeldingen de JUISTE modal heropenen
    @if(session('_last_action') === 'error')
        @if(session('edit_id'))
            // Haal specifiek de modal op die de fout veroorzaakte
            var errorModalEl = document.getElementById('editModal' + "{{ session('edit_id') }}");
            if (errorModalEl) {
                var errorModal = bootstrap.Modal.getOrCreateInstance(errorModalEl);
                errorModal.show();
            }
        @else
            var createModalEl = document.getElementById('createModal');
            if (createModalEl) {
                var createModal = bootstrap.Modal.getOrCreateInstance(createModalEl);
                createModal.show();
            }
        @endif

    @endif

    // 2. Behandel de confirm-update logica dynamisch op basis van de sessie-ID
    @if(session('_confirm_update') && session('edit_id'))
        const editId = "{{ session('edit_id') }}";
        const targetModalEl = document.getElementById('editModal' + editId);
        
        if (targetModalEl) {
            const modal = bootstrap.Modal.getOrCreateInstance(targetModalEl);
            modal.show();

            // Korte timeout om de animatie van de modal niet te breken
            setTimeout(() => {
                if (confirm("Are you sure you want to update this rule?")) {
                    let form = targetModalEl.querySelector('form');
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'confirm';
                    input.value = 'yes';
                    form.appendChild(input);
                    form.submit();
                }
            }, 300);
        }
    @endif
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</x-app-layout>