/**
 * Dynamic execution regions: office, beneficiaries, and per-region coordinator.
 */
(function () {
    'use strict';

    function initProjectExecutionRegions(config) {
        const zonesInput = document.getElementById(config.zonesInputId || 'execution_zones');
        const regionsFields = document.getElementById(config.fieldsContainerId || 'execution-regions-fields');
        const regionsCountBadge = document.getElementById(config.countBadgeId || 'execution-regions-count-badge');
        const targetInput = document.querySelector(config.targetBeneficiariesSelector || '[name="target_beneficiaries"]');
        const managerSelect = document.getElementById(config.projectManagerSelectId || 'project-manager-id');
        const form = zonesInput?.closest('form');

        if (!zonesInput || !regionsFields) {
            return;
        }

        const offices = Array.isArray(config.offices) ? config.offices : [];
        const savedRegions = Array.isArray(config.savedRegions) ? config.savedRegions : [];
        const coordinators = Array.isArray(config.coordinators) ? config.coordinators : [];
        const lockTeamFields = Boolean(config.lockTeamFields);
        const defaultCoordinatorMode = config.defaultCoordinatorMode || 'person';
        let projectManagerId = config.projectManagerId != null ? String(config.projectManagerId) : '';

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

        function coordinatorOptions(selectedValue) {
            let html = '<option value="">— اختر المنسق —</option>';

            coordinators.forEach((person) => {
                const selected = String(person.id) === String(selectedValue) ? ' selected' : '';
                html += `<option value="${escapeHtml(person.id)}"${selected}>${escapeHtml(person.name)}</option>`;
            });

            return html;
        }

        function resolveSavedMode(saved) {
            if (typeof saved !== 'object' || saved === null) {
                return defaultCoordinatorMode;
            }

            if (saved.coordinator_mode) {
                return saved.coordinator_mode;
            }

            if (saved.coordinator_external_name) {
                return 'external';
            }

            if (saved.coordinator_id && projectManagerId && String(saved.coordinator_id) === projectManagerId) {
                return 'self';
            }

            return defaultCoordinatorMode;
        }

        function nominationOptions(selectedValue) {
            const options = [
                ['', '— اختياري —'],
                ['project_manager', 'مدير المشروع'],
                ['coordinator', 'المنسق'],
                ['organization', 'المؤسسة'],
                ['external', 'جهة خارجية'],
            ];

            return options.map(([value, label]) => {
                const selected = String(value) === String(selectedValue || '') ? ' selected' : '';

                return `<option value="${escapeHtml(value)}"${selected}>${escapeHtml(label)}</option>`;
            }).join('');
        }

        function nominationFieldHtml(index, saved, readonly) {
            const selected = saved.nomination_responsibility || '';

            if (readonly) {
                return `
                    <input type="hidden" name="execution_regions[${index}][nomination_responsibility]" value="${escapeHtml(selected)}">
                `;
            }

            return `
                <label class="form-label mt-3 mb-1" for="execution_regions_${index}_nomination_responsibility">مسؤولية ترشيح الأسماء (اختياري)</label>
                <select
                    name="execution_regions[${index}][nomination_responsibility]"
                    id="execution_regions_${index}_nomination_responsibility"
                    class="form-select region-nomination-select"
                >
                    ${nominationOptions(selected)}
                </select>
            `;
        }

        function coordinatorReadonlyHtml(index, saved, mode) {
            let label = '—';

            if (mode === 'self') {
                label = 'مدير المشروع / منسق';
            } else if (mode === 'external') {
                label = `${saved.coordinator_external_name || '—'} — منسق خارجي`;
            } else {
                const match = coordinators.find((person) => String(person.id) === String(saved.coordinator_id));
                label = match ? `${match.name} — منسق من النظام` : 'منسق من النظام';
            }

            return `
                <div class="execution-region-coordinator-panel">
                    <label class="form-label mb-1">المنسق</label>
                    <div class="alert alert-secondary py-2 mb-0 small">${escapeHtml(label)}</div>
                    <input type="hidden" name="execution_regions[${index}][coordinator_mode]" value="${escapeHtml(mode)}">
                    ${saved.coordinator_id ? `<input type="hidden" name="execution_regions[${index}][coordinator_id]" value="${escapeHtml(saved.coordinator_id)}">` : ''}
                    ${saved.coordinator_external_name ? `<input type="hidden" name="execution_regions[${index}][coordinator_external_name]" value="${escapeHtml(saved.coordinator_external_name)}">` : ''}
                    ${nominationFieldHtml(index, saved, true)}
                </div>
            `;
        }

        function coordinatorEditorHtml(index, saved, mode) {
            const externalName = saved.coordinator_external_name || '';
            const coordinatorId = saved.coordinator_id || '';

            return `
                <div class="execution-region-coordinator-panel">
                    <label class="form-label mb-2">المنسق</label>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <div class="form-check">
                            <input class="form-check-input region-coordinator-mode" type="radio" name="execution_regions[${index}][coordinator_mode]" id="execution_regions_${index}_mode_self" value="self"${mode === 'self' ? ' checked' : ''}>
                            <label class="form-check-label" for="execution_regions_${index}_mode_self">مدير المشروع</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input region-coordinator-mode" type="radio" name="execution_regions[${index}][coordinator_mode]" id="execution_regions_${index}_mode_person" value="person"${mode === 'person' ? ' checked' : ''}>
                            <label class="form-check-label" for="execution_regions_${index}_mode_person">منسق من النظام</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input region-coordinator-mode" type="radio" name="execution_regions[${index}][coordinator_mode]" id="execution_regions_${index}_mode_external" value="external"${mode === 'external' ? ' checked' : ''}>
                            <label class="form-check-label" for="execution_regions_${index}_mode_external">منسق خارجي</label>
                        </div>
                    </div>
                    <div class="region-coordinator-person-wrap${mode === 'person' ? '' : ' d-none'}" data-region-index="${index}">
                        <label class="form-label mb-1" for="execution_regions_${index}_coordinator_id">اختر المنسق</label>
                        <select
                            name="execution_regions[${index}][coordinator_id]"
                            id="execution_regions_${index}_coordinator_id"
                            class="form-select region-coordinator-select"
                            ${mode === 'person' ? '' : 'disabled'}
                        >
                            ${coordinatorOptions(coordinatorId)}
                        </select>
                    </div>
                    <div class="region-coordinator-external-wrap${mode === 'external' ? '' : ' d-none'}" data-region-index="${index}">
                        <label class="form-label mb-1" for="execution_regions_${index}_coordinator_external_name">اسم المنسق الخارجي</label>
                        <input
                            type="text"
                            name="execution_regions[${index}][coordinator_external_name]"
                            id="execution_regions_${index}_coordinator_external_name"
                            class="form-control region-coordinator-external-input"
                            value="${externalName === '' ? '' : escapeHtml(externalName)}"
                            maxlength="255"
                            ${mode === 'external' ? '' : 'disabled'}
                        >
                        <div class="form-text">بدون حساب — يعبّئ مدير المشروع نيابةً عنه.</div>
                    </div>
                    <div class="region-coordinator-self-hint alert alert-info py-2 mt-2 mb-0 small${mode === 'self' ? '' : ' d-none'}" data-region-index="${index}">
                        بعد موافقة السكرتاريا، يعبّئ مدير المشروع قائمة المنسق لهذا المسار.
                    </div>
                    ${nominationFieldHtml(index, saved, false)}
                </div>
            `;
        }

        function syncRegionCoordinatorMode(card) {
            const mode = card.querySelector('.region-coordinator-mode:checked')?.value || defaultCoordinatorMode;
            const personWrap = card.querySelector('.region-coordinator-person-wrap');
            const externalWrap = card.querySelector('.region-coordinator-external-wrap');
            const selfHint = card.querySelector('.region-coordinator-self-hint');
            const coordinatorSelect = card.querySelector('.region-coordinator-select');
            const externalInput = card.querySelector('.region-coordinator-external-input');

            personWrap?.classList.toggle('d-none', mode !== 'person');
            externalWrap?.classList.toggle('d-none', mode !== 'external');
            selfHint?.classList.toggle('d-none', mode !== 'self');

            if (coordinatorSelect) {
                coordinatorSelect.disabled = mode !== 'person';
                if (mode !== 'person') {
                    coordinatorSelect.value = '';
                }
            }

            if (externalInput) {
                externalInput.disabled = mode !== 'external';
                if (mode !== 'external') {
                    externalInput.value = '';
                }
            }

            if (mode === 'person' && window.initSearchableSelects && personWrap) {
                window.initSearchableSelects(personWrap);
            }
        }

        function bindCoordinatorToggles(card) {
            card.querySelectorAll('.region-coordinator-mode').forEach((radio) => {
                radio.addEventListener('change', () => syncRegionCoordinatorMode(card));
            });
            syncRegionCoordinatorMode(card);
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
                const mode = resolveSavedMode(saved);

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
                    ${lockTeamFields
                        ? coordinatorReadonlyHtml(index, saved, mode)
                        : coordinatorEditorHtml(index, saved, mode)}
                `;

                regionsFields.appendChild(col);

                if (!lockTeamFields) {
                    bindCoordinatorToggles(col);
                }
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

        if (managerSelect) {
            managerSelect.addEventListener('change', () => {
                projectManagerId = managerSelect.value || '';
            });
        }

        renderExecutionRegions();
    }

    window.initProjectExecutionRegions = initProjectExecutionRegions;
})();
