/**
 * Closure docs: require person + file/URL when status is ready; toastr on submit.
 */
(function () {
    'use strict';

    function rowFields(row) {
        return {
            statusSelect: row.querySelector('.checklist-status-select'),
            personInput: row.querySelector('.checklist-person-input'),
            fileInput: row.querySelector('.checklist-file-input'),
            fileWrap: row.querySelector('[data-closure-file-field]'),
            urlInput: row.querySelector('.checklist-attachment-url-input'),
            itemName: row.querySelector('.checklist-col-item')?.textContent?.trim() || 'البند',
        };
    }

    function hasExistingAttachment(fileWrap, urlInput, fileInput) {
        if (fileWrap?.dataset.hasAttachment === '1') {
            return true;
        }

        if (fileInput?.files?.length) {
            return true;
        }

        if (urlInput?.value?.trim()) {
            return true;
        }

        if (window.checklistHasAttachment && fileWrap) {
            return window.checklistHasAttachment(fileWrap);
        }

        return false;
    }

    function syncRow(row) {
        const { statusSelect, personInput, fileInput, fileWrap, urlInput } = rowFields(row);

        if (!statusSelect) {
            return;
        }

        const isReady = statusSelect.value === 'ready';
        const isFileRow = row.hasAttribute('data-has-file-field');

        if (personInput) {
            personInput.required = isReady;
            personInput.classList.toggle('is-required-person', isReady);
        }

        if (fileInput && isFileRow) {
            const needsFile = isReady && !hasExistingAttachment(fileWrap, urlInput, fileInput);
            fileInput.required = false;
            fileInput.classList.toggle('is-required-file', needsFile);
        }
    }

    function isFileRow(row) {
        return row?.hasAttribute('data-has-file-field');
    }

    function validateForm(form) {
        const isClosureDocsForm = form?.hasAttribute('data-closure-docs-form');
        const rows = form.querySelectorAll('tr[data-has-file-field]');
        let firstError = null;

        rows.forEach((row) => {
            if (!isFileRow(row)) {
                return;
            }

            const fields = rowFields(row);

            if (!fields.statusSelect || fields.statusSelect.value !== 'ready') {
                return;
            }

            if (!isClosureDocsForm && row.dataset.fileRowTouched !== '1') {
                return;
            }

            const personName = fields.personInput?.value?.trim() || '';

            if (!personName) {
                firstError = firstError || `اسم الشخص مطلوب للبند «${fields.itemName}» عند اختيار جاهز.`;
            }

            if (!hasExistingAttachment(fields.fileWrap, fields.urlInput, fields.fileInput)) {
                firstError = firstError || `يرجى رفع ملف أو إدخال رابط للبند «${fields.itemName}» قبل الحفظ.`;
            }
        });

        if (firstError) {
            if (window.toastr) {
                window.toastr.warning(firstError);
            }

            const firstInvalidRow = Array.from(rows).find((row) => {
                if (!isFileRow(row)) {
                    return false;
                }

                const fields = rowFields(row);

                if (!isClosureDocsForm && row.dataset.fileRowTouched !== '1') {
                    return false;
                }

                return fields.statusSelect?.value === 'ready'
                    && (
                        !fields.personInput?.value?.trim()
                        || !hasExistingAttachment(fields.fileWrap, fields.urlInput, fields.fileInput)
                    );
            });

            firstInvalidRow?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return false;
        }

        return true;
    }

    function bindFormSubmit(form) {
        if (!form || form.dataset.checklistClosureSubmitBound === '1') {
            return;
        }

        form.dataset.checklistClosureSubmitBound = '1';

        form.addEventListener('submit', (event) => {
            if (!validateForm(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    }

    function markFileRowTouched(row) {
        if (isFileRow(row)) {
            row.dataset.fileRowTouched = '1';
        }
    }

    function bindRoot(root) {
        if (!root || root.dataset.closureDocsBound === '1') {
            return;
        }

        root.dataset.closureDocsBound = '1';

        const rows = root.querySelectorAll('tr[data-has-file-field]');

        rows.forEach((row) => syncRow(row));

        if (root.matches('form')) {
            bindFormSubmit(root);
        } else {
            bindFormSubmit(root.closest('form'));
        }

        root.addEventListener('change', (event) => {
            if (event.target.matches('.checklist-status-select, .checklist-file-input, .checklist-attachment-url-input, .checklist-attachment-type-input')) {
                const row = event.target.closest('tr');

                if (row) {
                    markFileRowTouched(row);
                    syncRow(row);
                }
            }
        });

        root.addEventListener('input', (event) => {
            if (event.target.matches('.checklist-person-input')) {
                const row = event.target.closest('tr');

                if (row) {
                    markFileRowTouched(row);
                }
            }
        });
    }

    function showServerValidationToasts() {
        const container = document.getElementById('checklist-validation-errors');

        if (!container || !window.toastr) {
            return;
        }

        container.querySelectorAll('[data-checklist-error]').forEach((node) => {
            const message = node.textContent?.trim();

            if (message) {
                window.toastr.warning(message);
            }
        });
    }

    window.initChecklistClosureDocs = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-closure-docs-form], [data-checklist-readiness]').forEach(bindRoot);
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.initChecklistClosureDocs(document);
        showServerValidationToasts();
    });
})();
