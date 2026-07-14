/**
 * Project financial fields: net amount and ILS execution amount auto-calc with manual override.
 */
(function () {
    'use strict';

    function parseNumber(value) {
        if (value === '' || value === null || value === undefined) {
            return null;
        }

        const parsed = parseFloat(String(value).replace(/,/g, ''));

        return Number.isFinite(parsed) ? parsed : null;
    }

    function formatNumber(value, decimals) {
        if (value === null || !Number.isFinite(value)) {
            return '';
        }

        return value.toFixed(decimals);
    }

    function initProjectFinancialFields(config) {
        const budgetInput = document.querySelector('[name="project_budget"]');
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

        budgetInput.addEventListener('input', () => {
            manual.net = false;
            manual.execution = false;
            recalculateDerived();
        });

        revenueInput?.addEventListener('input', () => {
            manual.net = false;
            manual.execution = false;
            recalculateDerived();
        });

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
        recalculateDerived();
    }

    window.initProjectFinancialFields = initProjectFinancialFields;
})();
