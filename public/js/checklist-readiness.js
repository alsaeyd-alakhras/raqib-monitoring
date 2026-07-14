/**
 * Dynamic checklist readiness percentages (matches Project::groupReadinessPercent).
 */
(function () {
    function effectiveValue(raw) {
        if (raw === null || raw === undefined || raw === '') {
            return 'not_ready';
        }

        return raw;
    }

    function itemWeight(select) {
        const row = select.closest('tr');
        const raw = effectiveValue(select.value);
        const isFileRow = row?.hasAttribute('data-has-file-field');
        const fileWrap = row?.querySelector('[data-closure-file-field]');
        const hasFile = fileWrap?.dataset.hasAttachment === '1'
            || Boolean(row?.querySelector('.checklist-file-input')?.files?.length)
            || Boolean(row?.querySelector('.checklist-attachment-url-input')?.value?.trim());

        if (isFileRow) {
            if (raw !== 'ready') {
                return 0;
            }

            return hasFile ? 1 : 0;
        }

        if (raw === 'ready') {
            return 1;
        }

        if (raw === 'partial') {
            return 0.5;
        }

        return 0;
    }

    function groupReadinessPercent(selects) {
        const values = Array.from(selects).map((select) => effectiveValue(select.value));
        const total = values.length;

        if (total === 0) {
            return null;
        }

        const notRequired = values.filter((v) => v === 'not_required').length;
        const denominator = total - notRequired;

        if (denominator <= 0) {
            return total > 0 ? 100 : null;
        }

        const weightSum = Array.from(selects)
            .filter((select) => effectiveValue(select.value) !== 'not_required')
            .reduce((sum, select) => sum + itemWeight(select), 0);

        return Math.round((weightSum / denominator) * 10000) / 100;
    }

    function formatPct(pct) {
        if (pct === null || Number.isNaN(pct)) {
            return '—';
        }

        return `${pct}%`;
    }

    function collectGroupValues(groupCard) {
        return Array.from(groupCard.querySelectorAll('select[name*="[value]"]'));
    }

    function updateContainer(container) {
        const groupCards = container.querySelectorAll('.checklist-group-card');
        const groupPercentages = [];

        groupCards.forEach((card) => {
            const pct = groupReadinessPercent(collectGroupValues(card));
            const badge = card.querySelector('.checklist-group-pct');

            if (badge) {
                badge.textContent = formatPct(pct);
            }

            if (pct !== null) {
                groupPercentages.push(pct);
            }
        });

        const overallEl = container.closest('.card')?.querySelector('.checklist-overall-pct')
            || container.querySelector('.checklist-overall-pct');

        if (overallEl) {
            if (groupPercentages.length === 0) {
                overallEl.textContent = '—';
            } else {
                const overall = Math.round((groupPercentages.reduce((a, b) => a + b, 0) / groupPercentages.length) * 100) / 100;
                overallEl.textContent = formatPct(overall);
            }
        }
    }

    function bindContainer(container) {
        if (!container || container.dataset.readinessBound === '1') {
            return;
        }

        container.dataset.readinessBound = '1';

        container.addEventListener('change', (event) => {
            if (event.target.matches('select[name*="[value]"], .checklist-file-input')) {
                updateContainer(container);
            }
        });

        updateContainer(container);
    }

    window.initChecklistReadiness = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-checklist-readiness]').forEach(bindContainer);

        if (scope.matches?.('[data-checklist-readiness]')) {
            bindContainer(scope);
        }
    };

    window.refreshChecklistReadiness = function (root) {
        const scope = root || document;
        const containers = scope.matches?.('[data-checklist-readiness]')
            ? [scope, ...scope.querySelectorAll('[data-checklist-readiness]')]
            : [...scope.querySelectorAll('[data-checklist-readiness]')];

        [...new Set(containers)].forEach((container) => {
            if (container?.dataset?.readinessBound === '1') {
                updateContainer(container);
            } else if (container) {
                bindContainer(container);
            }
        });
    };

    function bootReadiness() {
        window.initChecklistReadiness(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootReadiness);
    } else {
        bootReadiness();
    }
})();
