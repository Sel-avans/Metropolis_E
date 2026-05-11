<x-app-layout>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="fw-bold" style="color:#2563eb;">Conditions Management</h1>

        <button class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            + Nieuwe regel
        </button>
    </div>

    <div class="card shadow-lg border-0" style="border-radius: 14px;">
        <div class="card-header py-3" style="background: linear-gradient(90deg, #2563eb, #3b82f6); border-radius: 14px 14px 0 0;">
            <h5 class="mb-0 fw-semibold text-white">Alle adjacency regels</h5>
        </div>

        <div class="card-body p-0">
            <div class="max-h-[70vh] overflow-y-auto">

                {{-- TABEL --}}
                <table class="table table-hover align-middle mb-0" style="font-size: 1.1rem;">
                    <thead class="sticky-top" style="background:#f1f5f9;">
                        <tr style="height: 45px;">
                            <th class="ps-4">Functie A</th>
                            <th>Functie B</th>
                            <th>Type</th>
                            <th>Waarde</th>
                            <th class="pe-4">Acties</th>
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
                                        Bewerken
                                    </button>

                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="confirmDelete('{{ route('conditions.destroy', $condition) }}')">
                                        Verwijderen
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>

    {{-- ALLE EDIT-MODALS BUITEN DE TABEL --}}
    @foreach($conditions as $condition)
    <div class="modal fade" id="editModal{{ $condition->id }}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('conditions.update', $condition->id) }}">
                @csrf
                @method('PUT')

                <div class="modal-content shadow-lg" style="border-radius: 14px;">
                    <div class="modal-header" style="background:#2563eb; color:white;">
                        <h5 class="modal-title fw-bold">Regel bewerken</h5>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Functie A</label>
                            <select name="function_a" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}" @selected($f->id == $condition->function_a)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Functie B</label>
                            <select name="function_b" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}" @selected($f->id == $condition->function_b)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <option value="bonus" @selected($condition->type == 'bonus')>Bonus</option>
                                <option value="penalty" @selected($condition->type == 'penalty')>Penalty</option>
                                <option value="forbidden" @selected($condition->type == 'forbidden')>Forbidden</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Waarde</label>
                            <input type="number" name="value" class="form-control" value="{{ $condition->value }}">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal">Annuleren</button>
                        <button type="submit" class="btn btn-primary">Opslaan</button>
                    </div>
                </div>

            </form>
        </div>
    </div>
    @endforeach

    {{-- CREATE MODAL --}}
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('conditions.store') }}">
                @csrf

                <div class="modal-content shadow-lg" style="border-radius: 14px;">
                    <div class="modal-header" style="background:#2563eb; color:white;">
                        <h5 class="modal-title fw-bold">Nieuwe regel toevoegen</h5>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Functie A</label>
                            <select name="function_a" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Functie B</label>
                            <select name="function_b" class="form-select">
                                @foreach($functions as $f)
                                    <option value="{{ $f->id }}">{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Type</label>
                            <select name="type" class="form-select">
                                <option value="bonus">Bonus</option>
                                <option value="penalty">Penalty</option>
                                <option value="forbidden">Forbidden</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Waarde</label>
                            <input type="number" name="value" class="form-control">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal">Annuleren</button>
                        <button class="btn btn-primary">Toevoegen</button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    {{-- DELETE MODAL --}}
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg" style="border-radius: 14px;">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">Regel verwijderen</h5>
                </div>

                <div class="modal-body">
                    <p class="fw-semibold mb-0">Weet je zeker dat je deze regel wilt verwijderen?</p>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Annuleren</button>

                    <form id="deleteConfirmForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger">Verwijderen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function confirmDelete(url) {
    document.getElementById('deleteConfirmForm').action = url;
    new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</x-app-layout>
