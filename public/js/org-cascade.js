/**
 * Dependent dropdowns: Center -> Department -> Section
 */
window.initOrgCascade = function (config) {
    const centerSelect = document.getElementById(config.centerId || 'center_id');
    const departmentSelect = document.getElementById(config.departmentId || 'department_id');
    const sectionSelect = document.getElementById(config.sectionId || 'section_id');

    if (!centerSelect || !departmentSelect || !sectionSelect) {
        return;
    }

    const placeholder = config.placeholder || 'إختر القيمة';
    const departmentsUrl = config.departmentsUrl;
    const sectionsUrl = config.sectionsUrl;

    function setSelectOptions(select, items, selectedId) {
        select.innerHTML = '';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = placeholder;
        select.appendChild(emptyOption);

        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;

            if (selectedId !== null && selectedId !== '' && String(item.id) === String(selectedId)) {
                option.selected = true;
            }

            select.appendChild(option);
        });
    }

    function resetSection() {
        setSelectOptions(sectionSelect, [], null);
        sectionSelect.value = '';
    }

    function resetDepartment() {
        setSelectOptions(departmentSelect, [], null);
        departmentSelect.value = '';
        resetSection();
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('fetch failed');
        }

        return response.json();
    }

    async function loadDepartments(centerId, selectedDepartmentId = '', selectedSectionId = '') {
        resetDepartment();

        if (!centerId) {
            return;
        }

        try {
            const url = departmentsUrl.replace('__ID__', encodeURIComponent(centerId));
            const items = await fetchJson(url);

            setSelectOptions(departmentSelect, items, selectedDepartmentId);

            if (selectedDepartmentId && departmentSelect.value) {
                await loadSections(selectedDepartmentId, selectedSectionId);
            }
        } catch (error) {
            resetDepartment();
        }
    }

    async function loadSections(departmentId, selectedSectionId = '') {
        resetSection();

        if (!departmentId) {
            return;
        }

        try {
            const url = sectionsUrl.replace('__ID__', encodeURIComponent(departmentId));
            const items = await fetchJson(url);

            setSelectOptions(sectionSelect, items, selectedSectionId);
        } catch (error) {
            resetSection();
        }
    }

    centerSelect.addEventListener('change', function () {
        loadDepartments(centerSelect.value);
    });

    departmentSelect.addEventListener('change', function () {
        loadSections(departmentSelect.value);
    });

    const initialCenter = config.selectedCenterId || centerSelect.value || '';
    const initialDepartment = config.selectedDepartmentId || '';
    const initialSection = config.selectedSectionId || '';

    if (initialCenter) {
        loadDepartments(initialCenter, initialDepartment, initialSection);
    } else {
        resetDepartment();
    }
};
