@php
    $notesSubject = $activity ?? null;
    $editorId = $editorId ?? 'external-field-notes-editor';

    $linesFromOld = function (?string $key): array {
        $text = old($key);

        if ($text === null || $text === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $text))));
    };

    $storedPositive = is_array($notesSubject->positive_notes ?? null) ? $notesSubject->positive_notes : [];
    $storedNegative = is_array($notesSubject->negative_notes ?? null) ? $notesSubject->negative_notes : [];
    $storedRecommendations = is_array($notesSubject->recommendations ?? null) ? $notesSubject->recommendations : [];

    $initialPositiveNotes = old('positive_notes_text') !== null
        ? $linesFromOld('positive_notes_text')
        : $storedPositive;
    $initialNegativeNotes = old('negative_notes_text') !== null
        ? $linesFromOld('negative_notes_text')
        : $storedNegative;
    $initialRecommendations = old('recommendations_text') !== null
        ? $linesFromOld('recommendations_text')
        : $storedRecommendations;

    if ($initialPositiveNotes === []) {
        $initialPositiveNotes = [''];
    }
    if ($initialNegativeNotes === []) {
        $initialNegativeNotes = [''];
    }
    if ($initialRecommendations === []) {
        $initialRecommendations = [''];
    }

    $positiveHiddenValue = old('positive_notes_text', implode("\n", array_filter($storedPositive)));
    $negativeHiddenValue = old('negative_notes_text', implode("\n", array_filter($storedNegative)));
    $recommendationsHiddenValue = old('recommendations_text', implode("\n", array_filter($storedRecommendations)));
@endphp

@include('dashboard.partials._monitor_notes_editor_styles')

<div class="monitor-notes-editor" id="{{ $editorId }}">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="monitor-notes-editor-group-title">الملاحظات الميدانية</div>
            <div class="monitor-notes-editor-field-notes-block">
                <div>
                    <div class="monitor-notes-editor-subsection-title monitor-notes-editor-subsection-title--positive">ملاحظات إيجابية</div>
                    <div class="table-responsive">
                        <table class="monitor-notes-editor-table" data-editor-table="positive-notes">
                            <thead>
                                <tr>
                                    <th class="col-index">#</th>
                                    <th>النص</th>
                                    <th class="col-action"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($initialPositiveNotes as $note)
                                    <tr>
                                        <td class="col-index row-num">1</td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm editor-row-input" value="{{ $note }}">
                                        </td>
                                        <td class="col-action">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="حذف">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-success mt-2 btn-add-row" data-target="positive-notes">
                        <i class="fa-solid fa-plus me-1"></i> إضافة ملاحظة إيجابية
                    </button>
                </div>

                <div>
                    <div class="monitor-notes-editor-subsection-title monitor-notes-editor-subsection-title--negative">ملاحظات سلبية</div>
                    <div class="table-responsive">
                        <table class="monitor-notes-editor-table" data-editor-table="negative-notes">
                            <thead>
                                <tr>
                                    <th class="col-index">#</th>
                                    <th>النص</th>
                                    <th class="col-action"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($initialNegativeNotes as $note)
                                    <tr>
                                        <td class="col-index row-num">1</td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm editor-row-input" value="{{ $note }}">
                                        </td>
                                        <td class="col-action">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="حذف">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 btn-add-row" data-target="negative-notes">
                        <i class="fa-solid fa-plus me-1"></i> إضافة ملاحظة سلبية
                    </button>
                </div>
            </div>
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
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2 btn-add-row" data-target="recommendations">
                <i class="fa-solid fa-plus me-1"></i> إضافة توصية
            </button>
        </div>
    </div>
</div>

<textarea name="positive_notes_text" id="positive_notes_text" class="d-none" rows="1" aria-hidden="true">{{ $positiveHiddenValue }}</textarea>
<textarea name="negative_notes_text" id="negative_notes_text" class="d-none" rows="1" aria-hidden="true">{{ $negativeHiddenValue }}</textarea>
<textarea name="recommendations_text" id="recommendations_text" class="d-none" rows="1" aria-hidden="true">{{ $recommendationsHiddenValue }}</textarea>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById(@json($editorId));
    if (!editor) return;

    const positiveNotesHidden = document.getElementById('positive_notes_text');
    const negativeNotesHidden = document.getElementById('negative_notes_text');
    const recsHidden = document.getElementById('recommendations_text');
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
        if (positiveNotesHidden) {
            positiveNotesHidden.value = collectLines('positive-notes').join('\n');
        }
        if (negativeNotesHidden) {
            negativeNotesHidden.value = collectLines('negative-notes').join('\n');
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
            '<i class="fa-solid fa-trash"></i></button></td>';
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
