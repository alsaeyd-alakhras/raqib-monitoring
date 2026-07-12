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
    const allSectionsUrl = config.allSectionsUrl || null;
    const showAllSections = Boolean(config.showAllSections && allSectionsUrl);

    function setSelectOptions(select, items, selectedId) {
        if (window.destroySearchableSelect && select.classList.contains('select2-searchable')) {
            window.destroySearchableSelect(select);
        }

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

        if (select.classList.contains('select2-searchable') && window.initSearchableSelects) {
            window.initSearchableSelects(select.parentElement || document);
        }
    }

    function appendSectionOption(select, item, selectedId, labelPrefix) {
        const option = document.createElement('option');
        option.value = String(item.id);

        const departmentSuffix = item.department_name ? ` (${item.department_name})` : '';
        option.textContent = labelPrefix
            ? `${labelPrefix}${item.name}${departmentSuffix}`
            : `${item.name}${departmentSuffix}`;

        if (selectedId !== null && selectedId !== '' && String(item.id) === String(selectedId)) {
            option.selected = true;
        }

        select.appendChild(option);
    }

    function setGroupedSectionOptions(departmentSections, otherSections, selectedSectionId) {
        if (window.destroySearchableSelect && sectionSelect.classList.contains('select2-searchable')) {
            window.destroySearchableSelect(sectionSelect);
        }

        sectionSelect.innerHTML = '';

        const emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.textContent = placeholder;
        sectionSelect.appendChild(emptyOption);

        if (departmentSections.length > 0) {
            const departmentGroup = document.createElement('optgroup');
            departmentGroup.label = 'أقسام الدائرة المختارة';

            departmentSections.forEach((item) => {
                appendSectionOption(departmentGroup, item, selectedSectionId, '');
            });

            sectionSelect.appendChild(departmentGroup);
        }

        if (otherSections.length > 0) {
            const otherGroup = document.createElement('optgroup');
            otherGroup.label = 'باقي الأقسام';

            otherSections.forEach((item) => {
                appendSectionOption(otherGroup, item, selectedSectionId, '');
            });

            sectionSelect.appendChild(otherGroup);
        }

        if (sectionSelect.classList.contains('select2-searchable') && window.initSearchableSelects) {
            window.initSearchableSelects(sectionSelect.parentElement || document);
        }
    }

    function resetSection() {
        if (showAllSections) {
            setGroupedSectionOptions([], [], null);
        } else {
            setSelectOptions(sectionSelect, [], null);
        }

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
            const urlTemplate = showAllSections ? allSectionsUrl : sectionsUrl;
            const url = urlTemplate.replace('__ID__', encodeURIComponent(departmentId));
            const payload = await fetchJson(url);

            if (showAllSections) {
                setGroupedSectionOptions(
                    payload.department_sections || [],
                    payload.other_sections || [],
                    selectedSectionId
                );
            } else {
                setSelectOptions(sectionSelect, payload, selectedSectionId);
            }
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
