/**
 * Closure docs: require person + file when status is ready.
 */
(function () {
    'use strict';

    function rowFields(row) {
        return {
            statusSelect: row.querySelector('.checklist-status-select'),
            personInput: row.querySelector('.checklist-person-input'),
            fileInput: row.querySelector('.checklist-file-input'),
            fileWrap: row.querySelector('[data-closure-file-field]'),
        };
    }

    function hasExistingAttachment(fileWrap) {
        return fileWrap?.dataset.hasAttachment === '1'
            || Boolean(fileWrap?.querySelector('.checklist-file-input')?.files?.length);
    }

    function syncRow(row) {
        const { statusSelect, personInput, fileInput, fileWrap } = rowFields(row);

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
            const needsFile = isReady && !hasExistingAttachment(fileWrap);
            fileInput.required = needsFile;
            fileInput.classList.toggle('is-required-file', needsFile);
        }
    }

    function bindRoot(root) {
        if (!root || root.dataset.closureDocsBound === '1') {
            return;
        }

        root.dataset.closureDocsBound = '1';

        const rows = root.querySelectorAll('tr[data-has-file-field], [data-closure-docs-form] tr');

        rows.forEach((row) => syncRow(row));

        root.addEventListener('change', (event) => {
            if (event.target.matches('.checklist-status-select, .checklist-file-input')) {
                const row = event.target.closest('tr');

                if (row) {
                    syncRow(row);
                }
            }
        });
    }

    window.initChecklistClosureDocs = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-closure-docs-form], [data-checklist-readiness]').forEach(bindRoot);
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.initChecklistClosureDocs(document);
    });
})();
