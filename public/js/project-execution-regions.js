/**
 * Dynamic execution regions: office select + optional beneficiaries per zone.
 */
(function () {
    'use strict';

    function initProjectExecutionRegions(config) {
        const zonesInput = document.getElementById(config.zonesInputId || 'execution_zones');
        const regionsFields = document.getElementById(config.fieldsContainerId || 'execution-regions-fields');
        const regionsCountBadge = document.getElementById(config.countBadgeId || 'execution-regions-count-badge');
        const targetInput = document.querySelector(config.targetBeneficiariesSelector || '[name="target_beneficiaries"]');
        const form = zonesInput?.closest('form');

        if (!zonesInput || !regionsFields) {
            return;
        }

        const offices = Array.isArray(config.offices) ? config.offices : [];
        const savedRegions = Array.isArray(config.savedRegions) ? config.savedRegions : [];

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function officeOptions(selectedValue) {
            let html = '<option value="">— اختر المكتب —</option>';

            offices.forEach((office) => {
                const selected = office === selectedValue ? ' selected' : '';
                html += `<option value="${escapeHtml(office)}"${selected}>${escapeHtml(office)}</option>`;
            });

            return html;
        }

        function renderExecutionRegions() {
            const count = Math.max(0, parseInt(zonesInput.value || '0', 10) || 0);
            regionsFields.innerHTML = '';

            if (regionsCountBadge) {
                regionsCountBadge.textContent = count === 1 ? 'منطقة واحدة' : `${count} منطقة`;
            }

            if (count === 0) {
                return;
            }

            for (let index = 0; index < count; index += 1) {
                const saved = savedRegions[index] || {};
                const name = typeof saved === 'string' ? saved : (saved.name || '');
                const beneficiaries = typeof saved === 'object' && saved !== null && saved.beneficiaries != null
                    ? saved.beneficiaries
                    : '';
                const executionSite = typeof saved === 'object' && saved !== null && saved.execution_site != null
                    ? saved.execution_site
                    : '';

                const col = document.createElement('div');
                col.className = 'col-md-6 col-lg-4 execution-region-field';
                col.innerHTML = `
                    <label class="form-label d-flex align-items-center gap-2" for="execution_regions_${index}_name">
                        <span class="badge bg-label-secondary region-index-badge">${index + 1}</span>
                        <span>مكتب التنفيذ ${index + 1}</span>
                    </label>
                    <select
                        name="execution_regions[${index}][name]"
                        id="execution_regions_${index}_name"
                        class="form-select execution-region-office-select"
                        required
                    >
                        ${officeOptions(name)}
                    </select>
                    <label class="form-label mt-2 mb-1" for="execution_regions_${index}_execution_site">موقع التنفيذ (اختياري)</label>
                    <input
                        type="text"
                        name="execution_regions[${index}][execution_site]"
                        id="execution_regions_${index}_execution_site"
                        class="form-control execution-region-site-input"
                        value="${executionSite === '' ? '' : escapeHtml(executionSite)}"
                        maxlength="500"
                        placeholder="—"
                    >
                    <label class="form-label mt-2 mb-1" for="execution_regions_${index}_beneficiaries">عدد المستفيدين (اختياري)</label>
                    <input
                        type="number"
                        name="execution_regions[${index}][beneficiaries]"
                        id="execution_regions_${index}_beneficiaries"
                        class="form-control execution-region-beneficiaries-input"
                        value="${beneficiaries === '' ? '' : escapeHtml(beneficiaries)}"
                        min="0"
                        placeholder="—"
                    >
                `;

                regionsFields.appendChild(col);
            }
        }

        function beneficiariesTotal() {
            let total = 0;
            let hasAny = false;

            regionsFields.querySelectorAll('.execution-region-beneficiaries-input').forEach((input) => {
                if (input.value === '') {
                    return;
                }

                hasAny = true;
                total += Math.max(0, parseInt(input.value || '0', 10) || 0);
            });

            return hasAny ? total : 0;
        }

        function validateBeforeSubmit(event) {
            const target = Math.max(0, parseInt(targetInput?.value || '0', 10) || 0);
            const total = beneficiariesTotal();

            if (total > 0 && total > target) {
                event.preventDefault();

                if (window.toastr) {
                    window.toastr.warning(
                        `مجموع المستفيدين في المناطق (${total.toLocaleString('en-US')}) يتجاوز الإجمالي (${target.toLocaleString('en-US')}).`,
                        'مناطق التنفيذ'
                    );
                }

                return false;
            }

            return true;
        }

        zonesInput.addEventListener('input', renderExecutionRegions);
        zonesInput.addEventListener('change', renderExecutionRegions);
        form?.addEventListener('submit', validateBeforeSubmit);

        renderExecutionRegions();
    }

    window.initProjectExecutionRegions = initProjectExecutionRegions;
})();
