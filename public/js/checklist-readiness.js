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

    function groupReadinessPercent(values) {
        const total = values.length;

        if (total === 0) {
            return null;
        }

        const notRequired = values.filter((v) => v === 'not_required').length;
        const denominator = total - notRequired;

        if (denominator <= 0) {
            return total > 0 ? 100 : null;
        }

        const ready = values.filter((v) => v === 'ready').length;
        const partial = values.filter((v) => v === 'partial').length;

        return Math.round(((ready + 0.5 * partial) / denominator) * 10000) / 100;
    }

    function formatPct(pct) {
        if (pct === null || Number.isNaN(pct)) {
            return '—';
        }

        return `${pct}%`;
    }

    function collectGroupValues(groupCard) {
        return Array.from(groupCard.querySelectorAll('select[name*="[value]"]')).map((select) =>
            effectiveValue(select.value)
        );
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
            if (event.target.matches('select[name*="[value]"]')) {
                updateContainer(container);
            }
        });

        updateContainer(container);
    }

    window.initChecklistReadiness = function (root) {
        const scope = root || document;
        scope.querySelectorAll('[data-checklist-readiness]').forEach(bindContainer);
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.initChecklistReadiness(document);
    });
})();
