@php
    $initialNotes = $project->monitor_notes ?? [];
    $initialRecommendations = $project->monitor_recommendations ?? [];
    if (empty($initialNotes)) {
        $initialNotes = [''];
    }
    if (empty($initialRecommendations)) {
        $initialRecommendations = [''];
    }
@endphp

@once
    @push('styles')
    <style>
        .monitor-notes-editor-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            overflow: hidden;
            background: #fff;
        }

        .monitor-notes-editor-table thead th {
            background: rgba(67, 89, 113, 0.06);
            font-weight: 600;
            font-size: 0.8125rem;
            padding: 0.625rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.12);
        }

        .monitor-notes-editor-table tbody td {
            padding: 0.5rem 0.875rem;
            border-bottom: 1px solid rgba(67, 89, 113, 0.08);
            vertical-align: middle;
        }

        .monitor-notes-editor-table tbody tr:last-child td {
            border-bottom: none;
        }

        .monitor-notes-editor-table .col-index {
            width: 3rem;
            text-align: center;
            color: rgba(67, 89, 113, 0.55);
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .monitor-notes-editor-table .col-action {
            width: 3.5rem;
            text-align: center;
        }

        .monitor-notes-editor-section-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--bs-primary);
            margin-bottom: 0.5rem;
        }
    </style>
    @endpush
@endonce

<div class="row g-3 mt-3 monitor-notes-editor" id="monitor-notes-editor">
    <div class="col-lg-6">
        <div class="monitor-notes-editor-section-title">الملاحظات الميدانية</div>
        <div class="table-responsive">
            <table class="monitor-notes-editor-table" data-editor-table="notes">
                <thead>
                    <tr>
                        <th class="col-index">#</th>
                        <th>النص</th>
                        <th class="col-action"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($initialNotes as $note)
                        <tr>
                            <td class="col-index row-num">1</td>
                            <td>
                                <input type="text" class="form-control form-control-sm editor-row-input" value="{{ $note }}">
                            </td>
                            <td class="col-action">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="حذف">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-add-row" data-target="notes">
            <i class="bx bx-plus"></i> إضافة ملاحظة
        </button>
    </div>
    <div class="col-lg-6">
        <div class="monitor-notes-editor-section-title">التوصيات</div>
        <div class="table-responsive">
            <table class="monitor-notes-editor-table" data-editor-table="recommendations">
                <thead>
                    <tr>
                        <th class="col-index">#</th>
                        <th>النص</th>
                        <th class="col-action"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($initialRecommendations as $rec)
                        <tr>
                            <td class="col-index row-num">1</td>
                            <td>
                                <input type="text" class="form-control form-control-sm editor-row-input" value="{{ $rec }}">
                            </td>
                            <td class="col-action">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="حذف">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-add-row" data-target="recommendations">
            <i class="bx bx-plus"></i> إضافة توصية
        </button>
    </div>
</div>

<textarea name="monitor_notes_text" id="monitor_notes_text" class="d-none" aria-hidden="true">{{ implode("\n", array_filter($project->monitor_notes ?? [])) }}</textarea>
<textarea name="monitor_recommendations_text" id="monitor_recommendations_text" class="d-none" aria-hidden="true">{{ implode("\n", array_filter($project->monitor_recommendations ?? [])) }}</textarea>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('monitor-notes-editor');
    if (!editor) return;

    const notesHidden = document.getElementById('monitor_notes_text');
    const recsHidden = document.getElementById('monitor_recommendations_text');
    const form = editor.closest('form');

    function renumberRows(tbody) {
        tbody.querySelectorAll('tr').forEach(function (row, index) {
            const numCell = row.querySelector('.row-num');
            if (numCell) numCell.textContent = String(index + 1);
        });
    }

    function collectLines(tableName) {
        const table = editor.querySelector('[data-editor-table="' + tableName + '"] tbody');
        if (!table) return [];
        return Array.from(table.querySelectorAll('.editor-row-input'))
            .map(function (input) { return input.value.trim(); })
            .filter(function (line) { return line.length > 0; });
    }

    function syncHiddenFields() {
        if (notesHidden) {
            notesHidden.value = collectLines('notes').join('\n');
        }
        if (recsHidden) {
            recsHidden.value = collectLines('recommendations').join('\n');
        }
    }

    function createRow() {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="col-index row-num">1</td>' +
            '<td><input type="text" class="form-control form-control-sm editor-row-input" value=""></td>' +
            '<td class="col-action">' +
            '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="حذف">' +
            '<i class="bx bx-trash"></i></button></td>';
        return tr;
    }

    editor.querySelectorAll('.btn-add-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.getAttribute('data-target');
            const tbody = editor.querySelector('[data-editor-table="' + target + '"] tbody');
            if (!tbody) return;
            const row = createRow();
            tbody.appendChild(row);
            renumberRows(tbody);
            row.querySelector('.editor-row-input')?.focus();
            syncHiddenFields();
        });
    });

    editor.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('.btn-remove-row');
        if (!removeBtn) return;
        const tbody = removeBtn.closest('tbody');
        const rows = tbody.querySelectorAll('tr');
        if (rows.length <= 1) {
            rows[0].querySelector('.editor-row-input').value = '';
        } else {
            removeBtn.closest('tr').remove();
        }
        renumberRows(tbody);
        syncHiddenFields();
    });

    editor.addEventListener('input', function (event) {
        if (event.target.classList.contains('editor-row-input')) {
            syncHiddenFields();
        }
    });

    form?.addEventListener('submit', syncHiddenFields);

    editor.querySelectorAll('[data-editor-table]').forEach(function (table) {
        renumberRows(table.querySelector('tbody'));
    });
    syncHiddenFields();
});
</script>
@endpush
