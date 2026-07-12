/**
 * Color-code checklist status selects to match display badges.
 */
(function () {
    'use strict';

    const STATUS_CLASSES = [
        'checklist-st-ready',
        'checklist-st-partial',
        'checklist-st-not-ready',
        'checklist-st-not-required',
    ];

    function statusClassFor(value) {
        return {
            ready: 'checklist-st-ready',
            partial: 'checklist-st-partial',
            not_ready: 'checklist-st-not-ready',
            not_required: 'checklist-st-not-required',
        }[value] || 'checklist-st-not-ready';
    }

    function syncStatusSelect(select) {
        STATUS_CLASSES.forEach((className) => select.classList.remove(className));
        select.classList.add(statusClassFor(select.value));
    }

    function bindRoot(root) {
        if (!root || root.dataset.statusStyleBound === '1') {
            return;
        }

        root.dataset.statusStyleBound = '1';

        root.querySelectorAll('.checklist-status-select').forEach(syncStatusSelect);

        root.addEventListener('change', (event) => {
            if (event.target.matches('.checklist-status-select')) {
                syncStatusSelect(event.target);
            }
        });
    }

    window.initChecklistStatusStyle = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-checklist-readiness], [data-closure-docs-form]').forEach(bindRoot);

        if (scope.matches?.('[data-checklist-readiness], [data-closure-docs-form]')) {
            bindRoot(scope);
        }
    };

    function bootStatusStyle() {
        window.initChecklistStatusStyle(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStatusStyle);
    } else {
        bootStatusStyle();
    }
})();
