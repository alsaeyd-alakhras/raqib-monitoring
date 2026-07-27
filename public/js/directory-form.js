(function () {
    'use strict';

    const cfg = window.directoryFormConfig || {};

    function setAbilities(abilities) {
        $('.ability-checkbox').prop('checked', false);
        (abilities || []).forEach(function (ability) {
            $('.ability-checkbox[value="' + ability + '"]').prop('checked', true);
        });
        syncMasterCheckboxes();
    }

    function syncMasterCheckboxes() {
        $('.master-checkbox').each(function () {
            const targetClass = $(this).data('target');
            const $children = $('.' + targetClass);
            const allChecked = $children.length > 0 && $children.filter(':checked').length === $children.length;
            $(this).prop('checked', allChecked);
        });
    }

    function abilitiesForRole(role) {
        if (!role) return [];
        return cfg.roleAbilitiesMap[role] || [];
    }

    function syncSingleRoleOptions() {
        const roleSelect = document.getElementById('directory-role');
        const hint = document.getElementById('monitoring-director-hint');
        if (!roleSelect) {
            return;
        }

        const occupiedDirector = cfg.occupiedMonitoringDirector || null;
        const currentRole = roleSelect.value;
        const monitoringOption = roleSelect.querySelector('option[value="monitoring_director"]');

        if (monitoringOption) {
            const blocked = occupiedDirector && currentRole !== 'monitoring_director';
            monitoringOption.disabled = blocked;
            monitoringOption.hidden = blocked;
        }

        if (hint) {
            if (occupiedDirector && currentRole !== 'monitoring_director') {
                hint.textContent = 'دور مدير الرقابة العامة محجوز لـ «' + occupiedDirector.name + '». يجب إزالة الدور منه أولاً.';
                hint.classList.remove('d-none');
            } else {
                hint.textContent = '';
                hint.classList.add('d-none');
            }
        }
    }

    function updateSectionsVisibility() {
        const role = $('#directory-role').val();
        const needsDept = (cfg.rolesRequiringDepartment || []).includes(role);
        const needsSection = (cfg.rolesRequiringSection || []).includes(role);
        $('#directory-department').closest('.col-md-4').toggle(needsDept || needsSection || !!role);
        $('#directory-section').closest('.col-md-4').toggle(needsSection);
        syncSingleRoleOptions();
    }

    function updateRecordModeUI() {
        const mode = $('input[name="record_mode"]:checked').val() || $('input[name="record_mode"]').val();
        const showPerson = mode !== 'user_only';
        const showAccount = mode !== 'person_only';

        $('#person-section').toggle(showPerson);
        $('#account-section').toggle(showAccount);
        $('#abilities-section').toggle(showAccount);

        if (mode === 'person_only') {
            $('#has-account').prop('checked', false);
        } else {
            $('#has-account').prop('checked', true);
        }
    }

    $('#directory-role').on('change', function () {
        const roleSelect = this;
        const occupiedDirector = cfg.occupiedMonitoringDirector || null;

        if (roleSelect.value === 'monitoring_director' && occupiedDirector) {
            window.alert('يوجد مدير رقابة عامة بالفعل: ' + occupiedDirector.name + '.');
            roleSelect.value = '';
        }

        updateSectionsVisibility();
        setAbilities(abilitiesForRole($(roleSelect).val()));
        $('#apply-role-abilities-flag').val('1');
    });

    $('#btn-apply-role-abilities').on('click', function () {
        const role = $('#directory-role').val();
        const current = $('.ability-checkbox:checked').map(function () { return $(this).val(); }).get();
        const base = abilitiesForRole(role);
        const merged = Array.from(new Set(base.concat(current.filter(a => !abilitiesForRole(role).includes(a) && !base.includes(a)))));
        const extras = current.filter(a => !abilitiesForRole(role).includes(a));
        setAbilities(Array.from(new Set(base.concat(extras))));
    });

    $('#btn-reset-role-abilities').on('click', function () {
        setAbilities(abilitiesForRole($('#directory-role').val()));
        $('#reset-role-abilities-flag').val('1');
    });

    $('input[name="record_mode"]').on('change', updateRecordModeUI);
    $('#has-account').on('change', function () {
        $('#account-fields, #abilities-section').toggle($(this).is(':checked'));
    });

    $('.master-checkbox').on('change', function () {
        const targetClass = $(this).data('target');
        $('.' + targetClass).prop('checked', $(this).prop('checked'));
    });

    $(document).on('change', '.ability-checkbox', syncMasterCheckboxes);

    if (typeof window.initOrgCascade === 'function') {
        window.initOrgCascade({
            centerId: 'directory-center',
            departmentId: 'directory-department',
            sectionId: 'directory-section',
            departmentsUrl: cfg.departmentsByCenterUrl,
            sectionsUrl: cfg.sectionsByDepartmentUrl,
            selectedCenterId: cfg.selectedCenterId,
            selectedDepartmentId: cfg.selectedDepartmentId,
            selectedSectionId: cfg.selectedSectionId,
        });
    }

    updateSectionsVisibility();
    updateRecordModeUI();
    syncMasterCheckboxes();
})();
