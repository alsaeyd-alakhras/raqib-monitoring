/**
 * Require person name when checklist item status is ready or partial.
 */
(function () {
    'use strict';

    const REQUIRED_STATUSES = ['ready', 'partial'];

    function personInputForStatusSelect(statusSelect) {
        const row = statusSelect.closest('tr');

        if (!row) {
            return null;
        }

        return row.querySelector('input[name*="[person_name]"]');
    }

    function syncPersonInput(statusSelect) {
        const personInput = personInputForStatusSelect(statusSelect);
        const row = statusSelect.closest('tr');

        if (!personInput) {
            return;
        }

        const isFileRow = row?.hasAttribute('data-has-file-field');
        const isRequired = isFileRow
            ? statusSelect.value === 'ready'
            : REQUIRED_STATUSES.includes(statusSelect.value);

        personInput.required = isRequired;
        personInput.classList.toggle('is-required-person', isRequired);
    }

    function bindContainer(container) {
        if (!container || container.dataset.personRequiredBound === '1') {
            return;
        }

        container.dataset.personRequiredBound = '1';

        container.querySelectorAll('select[name*="[value]"]').forEach((statusSelect) => {
            syncPersonInput(statusSelect);
        });

        container.addEventListener('change', (event) => {
            if (event.target.matches('select[name*="[value]"]')) {
                syncPersonInput(event.target);
            }
        });
    }

    window.initChecklistPersonRequired = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-checklist-readiness]').forEach(bindContainer);
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.initChecklistPersonRequired(document);
    });
})();
