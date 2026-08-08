/**
 * Project financial fields: admin expense % ↔ amount sync, net amount and ILS execution amount auto-calc.
 */
(function () {
    'use strict';

    function parseNumber(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }

        const normalized = String(value)
            .replace(/,/g, '')
            .replace(/[\u0660-\u0669]/g, (d) => String(d.charCodeAt(0) - 0x0660))
            .replace(/[\u06F0-\u06F9]/g, (d) => String(d.charCodeAt(0) - 0x06F0));

        const parsed = parseFloat(normalized);

        return Number.isFinite(parsed) ? parsed : null;
    }

    function formatNumber(value, decimals) {
        if (value === null || !Number.isFinite(value)) {
            return '';
        }

        return Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
            useGrouping: false,
        });
    }

    function initProjectFinancialFields(config) {
        const budgetInput = document.querySelector('[name="project_budget"]');
        const pctInput = document.getElementById('admin_expense_pct');
        const revenueInput = document.querySelector('[name="revenue_amount"]');
        const netInput = document.querySelector('[name="net_amount"]');
        const currencySelect = document.querySelector('[name="currency_id"]');
        const exchangeInput = document.querySelector('[name="exchange_rate"]');
        const executionInput = document.querySelector('[name="execution_amount_ils"]');

        if (!budgetInput || !netInput || !exchangeInput || !executionInput) {
            return;
        }

        const rates = config.rates || {};
        const manual = {
            net: false,
            execution: false,
            exchange: false,
        };
        let lastSource = 'amount';
        let syncing = false;

        function markManual(input, key) {
            input?.addEventListener('input', () => {
                manual[key] = true;
            });
        }

        markManual(netInput, 'net');
        markManual(executionInput, 'execution');
        markManual(exchangeInput, 'exchange');

        function applyExchangeRateFromCurrency() {
            if (manual.exchange || !currencySelect) {
                return;
            }

            const rate = rates[currencySelect.value];

            if (rate !== undefined && rate !== null) {
                exchangeInput.value = formatNumber(parseFloat(rate), 6);
            }
        }

        function updatePctFromRevenue(budget, revenue) {
            if (!pctInput) {
                return;
            }

            if (budget === null || budget <= 0) {
                pctInput.value = '';
                return;
            }

            const revenueValue = revenue ?? 0;
            pctInput.value = formatNumber((revenueValue / budget) * 100, 2);
        }

        function updateRevenueFromPct(budget, pct) {
            if (!revenueInput || budget === null || budget <= 0 || pct === null) {
                return;
            }

            revenueInput.value = formatNumber((budget * pct) / 100, 2);
        }

        function recalculateDerived() {
            const budget = parseNumber(budgetInput.value) ?? 0;
            const revenue = parseNumber(revenueInput?.value) ?? 0;
            const exchangeRate = parseNumber(exchangeInput.value) ?? 1;

            if (!manual.net) {
                netInput.value = formatNumber(budget - revenue, 2);
            }

            const netAmount = parseNumber(netInput.value);

            if (!manual.execution && netAmount !== null) {
                executionInput.value = formatNumber(netAmount * exchangeRate, 2);
            }
        }

        function syncFromPct() {
            if (syncing || !pctInput) {
                return;
            }

            syncing = true;
            lastSource = 'pct';

            const budget = parseNumber(budgetInput.value);
            const pct = parseNumber(pctInput.value);

            if (budget !== null && budget > 0 && pct !== null) {
                updateRevenueFromPct(budget, pct);
            }

            manual.net = false;
            manual.execution = false;
            recalculateDerived();
            syncing = false;
        }

        function syncFromRevenue() {
            if (syncing) {
                return;
            }

            syncing = true;
            lastSource = 'amount';

            const budget = parseNumber(budgetInput.value);
            const revenue = parseNumber(revenueInput?.value);

            updatePctFromRevenue(budget, revenue);
            manual.net = false;
            manual.execution = false;
            recalculateDerived();
            syncing = false;
        }

        function syncFromBudget() {
            if (syncing) {
                return;
            }

            syncing = true;

            const budget = parseNumber(budgetInput.value);

            if (lastSource === 'pct' && pctInput) {
                const pct = parseNumber(pctInput.value);

                if (budget !== null && budget > 0 && pct !== null) {
                    updateRevenueFromPct(budget, pct);
                }
            } else {
                const revenue = parseNumber(revenueInput?.value);
                updatePctFromRevenue(budget, revenue);
            }

            manual.net = false;
            manual.execution = false;
            recalculateDerived();
            syncing = false;
        }

        budgetInput.addEventListener('input', syncFromBudget);

        pctInput?.addEventListener('input', syncFromPct);

        revenueInput?.addEventListener('input', syncFromRevenue);

        exchangeInput.addEventListener('input', () => {
            if (!manual.exchange) {
                manual.execution = false;
            }

            recalculateDerived();
        });

        currencySelect?.addEventListener('change', () => {
            manual.exchange = false;
            manual.execution = false;
            applyExchangeRateFromCurrency();
            recalculateDerived();
        });

        applyExchangeRateFromCurrency();

        if (pctInput && pctInput.value !== '') {
            lastSource = 'pct';
        }

        syncFromBudget();
    }

    window.initProjectFinancialFields = initProjectFinancialFields;
})();
