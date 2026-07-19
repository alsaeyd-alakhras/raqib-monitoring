/**
 * Generic Yajra DataTables driver.
 * Each page defines globals before loading this file:
 * tableId, urlIndex, urlFilters, urlDelete, _token, fields, columnsTable,
 * arabicFileJson, sortConfig, dateFilterField (optional), SUMMABLE_COLUMNS (optional)
 */

function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderTruncatedCode(value) {
    const raw = value === null || value === undefined || value === '' ? '-' : String(value);
    const escaped = escapeHtml(raw);
    if (raw === '-') {
        return escaped;
    }
    return '<span class="code-cell" tabindex="0" data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="' + escaped + '">' + escaped + '</span>';
}

function initRaqibCellPopovers(selector) {
    document.querySelectorAll(selector).forEach(function (el) {
        bootstrap.Popover.getInstance(el)?.dispose();
        new bootstrap.Popover(el);
    });
}

function syncRaqibStickyHeaders(api) {
    if (!document.querySelector('#' + tableId + '.raqib-dt')) {
        return;
    }

    api.columns().every(function (idx) {
        const def = columnsTable[idx];
        if (!def || !def.className) {
            return;
        }

        const stickyClasses = def.className.split(/\s+/).filter(function (cls) {
            return cls.startsWith('sticky-') || cls.startsWith('col-');
        });

        if (stickyClasses.length) {
            $(this.header()).addClass(stickyClasses.join(' '));
        }
    });
}

$(document).ready(function () {
    const dateField = typeof dateFilterField !== 'undefined' ? dateFilterField : 'distributed_at';

    function getActiveColumnFilters() {
        const filters = {};

        $('input[type="checkbox"]:checked').each(function () {
            const className = $(this).attr('class') || '';
            const value = $(this).val();

            if (value === 'الكل' || value === 'all' || value === 'All') {
                return;
            }

            const fieldMatch = className.match(/(\w+)-checkbox/);
            if (fieldMatch) {
                const fieldName = fieldMatch[1];
                if (!filters[fieldName]) {
                    filters[fieldName] = [];
                }
                filters[fieldName].push(value);
            }
        });

        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        if (fromDate || toDate) {
            filters[dateField] = {};
            if (fromDate) {
                filters[dateField].from = fromDate;
            }
            if (toDate) {
                filters[dateField].to = toDate;
            }
        }

        return filters;
    }

    function getActiveColumnFiltersExcept(excludeColumnIndex) {
        const filters = {};

        $('input[type="checkbox"]:checked').each(function () {
            const className = $(this).attr('class') || '';
            const value = $(this).val();

            if (value === 'الكل' || value === 'all' || value === 'All') {
                return;
            }

            const fieldMatch = className.match(/(\w+)-checkbox/);
            if (fieldMatch) {
                const fieldName = fieldMatch[1];
                const fieldIndex = fields.indexOf(fieldName);
                if (fieldIndex === excludeColumnIndex) {
                    return;
                }

                if (!filters[fieldName]) {
                    filters[fieldName] = [];
                }
                filters[fieldName].push(value);
            }
        });

        return filters;
    }

    const table = $('#' + tableId).DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        paging: true,
        pageLength: $('#advanced-pagination').length ? $('#advanced-pagination').val() : 15,
        searching: true,
        info: true,
        lengthChange: true,
        language: {
            url: arabicFileJson,
            paginate: {
                first: 'الأولى',
                last: 'الأخيرة',
                next: 'التالي',
                previous: 'السابق',
            },
            info: 'عرض _START_ إلى _END_ من أصل _TOTAL_ عنصر',
            infoEmpty: 'عرض 0 إلى 0 من أصل 0 عنصر',
            infoFiltered: '(تصفية من _MAX_ عنصر إجمالي)',
            lengthMenu: 'عرض _MENU_ عنصر',
            zeroRecords: 'لا توجد سجلات مطابقة',
            emptyTable: 'لا توجد بيانات متاحة في الجدول',
            processing: 'جاري المعالجة...',
        },
        ajax: {
            url: urlIndex,
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
                if ($('#type').length) {
                    d.type = $('#type').val();
                }
                if ($('#year').length) {
                    d.year = $('#year').val();
                }
                d.column_filters = getActiveColumnFilters();

                if (typeof sortConfig !== 'undefined' && sortConfig.enabled && currentSortColumn && currentSortDirection) {
                    d.sort_column = currentSortColumn;
                    d.sort_direction = currentSortDirection;
                }

                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('project_id')) {
                    d.project_id = urlParams.get('project_id');
                }
                if (urlParams.has('office_id')) {
                    d.office_id = urlParams.get('office_id');
                }
                if (urlParams.has('family_id')) {
                    d.family_id = urlParams.get('family_id');
                }
            },
            dataSrc: function (json) {
                if (typeof SUMMABLE_COLUMNS !== 'undefined' && SUMMABLE_COLUMNS.enabled && json.totals) {
                    updateFooterTotals(json.totals);
                }
                return json.data;
            },
            error: function (xhr, status, error) {
                console.error('AJAX error:', status, error);
            },
        },
        columns: columnsTable,
        columnDefs: [
            { targets: 0, searchable: false, orderable: false },
        ],
        dom:
            '<"top"<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>>' +
            '<"table-responsive"t>' +
            '<"bottom"<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>>',
        rowCallback: typeof rowCallbackFn === 'function' ? rowCallbackFn : undefined,
        drawCallback: function () {
            if (typeof drawCallbackFn === 'function') {
                drawCallbackFn();
            }
            initRaqibCellPopovers('#' + tableId + ' .code-cell[data-bs-toggle="popover"]');
        },
        initComplete: function () {
            syncRaqibStickyHeaders(this.api());
        },
    });

    if (typeof onTableReady === 'function') {
        onTableReady(table);
    }

    $(document).on('change', '#advanced-pagination', function () {
        table.page.len($(this).val()).draw();
    });

    if ($('#year').length) {
        $('#year').on('change', function () {
            table.ajax.reload();
        });
    }

    function updateFooterTotals(totals) {
        if (typeof SUMMABLE_COLUMNS === 'undefined' || !SUMMABLE_COLUMNS.enabled || !totals) {
            return;
        }

        Object.keys(SUMMABLE_COLUMNS.columns).forEach(function (columnName) {
            const config = SUMMABLE_COLUMNS.columns[columnName];
            const footerCell = $('#tfoot-' + columnName);

            if (footerCell.length && Object.prototype.hasOwnProperty.call(totals, columnName)) {
                let value = totals[columnName];
                if (config.format === 'currency' || config.format === 'number') {
                    value = new Intl.NumberFormat('en-US', {
                        minimumFractionDigits: config.format === 'currency' ? 2 : 0,
                        maximumFractionDigits: 2,
                    }).format(value);
                }
                footerCell.text(value);
            }
        });
    }

    function updatePaginationButtons() {
        $('.dt-paging-button.previous').html('<i class="fas fa-chevron-right"></i>');
        $('.dt-paging-button.next').html('<i class="fas fa-chevron-left"></i>');
    }

    function populateFilterOptions(columnIndex, container, name) {
        const columnName = fields[columnIndex];
        const activeFilters = getActiveColumnFiltersExcept(columnIndex);
        const urlParams = new URLSearchParams(window.location.search);
        const ajaxData = {
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val(),
            active_filters: activeFilters,
        };

        if ($('#type').length) {
            ajaxData.type = $('#type').val();
        }
        if ($('#year').length) {
            ajaxData.year = $('#year').val();
        }
        if (urlParams.has('project_id')) {
            ajaxData.project_id = urlParams.get('project_id');
        }
        if (urlParams.has('office_id')) {
            ajaxData.office_id = urlParams.get('office_id');
        }
        if (urlParams.has('family_id')) {
            ajaxData.family_id = urlParams.get('family_id');
        }

        $.ajax({
            url: urlFilters.replace(':column', columnName),
            data: ajaxData,
            success: function (uniqueValues) {
                uniqueValues.sort();
                const checkboxList = $(container);
                checkboxList.empty();

                uniqueValues.forEach(function (value) {
                    checkboxList.append(
                        '<label style="display: block;">' +
                            '<input type="checkbox" value="' + value + '" class="' + name + '-checkbox"> ' + value +
                        '</label>'
                    );
                });
            },
            error: function (xhr) {
                console.error('خطأ في تحميل خيارات الفلترة', xhr);
            },
        });
    }

    function updateFilterButtonsStyle() {
        for (let i = 0; i < fields.length; i++) {
            const field = fields[i];
            if (field === '#' || field === 'edit' || field === 'delete' || field === 'actions' || field === 'view' || field === 'verification') {
                continue;
            }

            let hasActiveFilter = false;

            $('.' + field + '-checkbox:checked').each(function () {
                const value = $(this).val();
                if (value !== 'الكل' && value !== 'all' && value !== 'All') {
                    hasActiveFilter = true;
                    return false;
                }
            });

            if (field === dateField && ($('#from_date').val() || $('#to_date').val())) {
                hasActiveFilter = true;
            }

            const btnId = '#btn-filter-' + i;
            if (hasActiveFilter) {
                $(btnId).removeClass('btn-secondary').addClass('btn-success');
                $(btnId + ' i').removeClass('fa-solid fa-filter').addClass('fa-brands fa-get-pocket');
            } else {
                $(btnId).removeClass('btn-success').addClass('btn-secondary');
                $(btnId + ' i').removeClass('fa-brands fa-get-pocket').addClass('fa-solid fa-filter');
            }
        }
    }

    function hasActiveFilters() {
        let hasFilters = false;

        $('input[type="checkbox"]:checked').each(function () {
            const value = $(this).val();
            if (value !== 'الكل' && value !== 'all' && value !== 'All') {
                hasFilters = true;
                return false;
            }
        });

        if ($('#from_date').val() || $('#to_date').val()) {
            hasFilters = true;
        }

        return hasFilters;
    }

    function updateClearFilterButton() {
        if ($('#filterBtnClear').length) {
            if (hasActiveFilters()) {
                $('#filterBtnClear').removeClass('d-none');
            } else {
                $('#filterBtnClear').addClass('d-none');
            }
        }
    }

    function updateSortButtonsUI() {
        if (typeof sortConfig === 'undefined' || !sortConfig.enabled) {
            return;
        }

        $('.btn-sort').each(function () {
            const field = $(this).data('sort-field');
            const icon = $(this).find('i');
            icon.removeClass('fa-sort fa-sort-up fa-sort-down text-muted text-primary');
            if (currentSortColumn === field) {
                icon.addClass(currentSortDirection === 'asc' ? 'fa-sort-up text-primary' : 'fa-sort-down text-primary');
            } else {
                icon.addClass('fa-sort text-muted');
            }
        });
    }

    $(document).on('show.bs.dropdown', '.enhanced-filter-dropdown', function () {
        const button = $(this).find('.btn-filter');
        const btnId = button.attr('id');
        const index = parseInt(btnId.replace('btn-filter-', ''), 10);
        const field = fields[index];

        if (field === '#' || field === 'edit' || field === 'delete' || field === 'actions' || field === 'view' || field === 'verification') {
            return;
        }

        let hasActiveFilter = false;
        $('.' + field + '-checkbox:checked').each(function () {
            const value = $(this).val();
            if (value !== 'الكل' && value !== 'all' && value !== 'All') {
                hasActiveFilter = true;
                return false;
            }
        });

        if (!hasActiveFilter) {
            populateFilterOptions(index, '.checkbox-list-' + index, field);
        }
    });

    table.on('draw', function () {
        updateFilterButtonsStyle();
        updatePaginationButtons();
        updateClearFilterButton();
    });

    $(document).on('click', '.filter-apply-btn-checkbox', function () {
        table.ajax.reload();
        updateClearFilterButton();
        updateFilterButtonsStyle();
    });

    $(document).on('click', '#filter-date-btn', function () {
        table.ajax.reload();
        updateClearFilterButton();
        updateFilterButtonsStyle();
    });

    $(document).on('click', '#filter-date-clear-btn', function () {
        $('#from_date').val('');
        $('#to_date').val('');
        table.ajax.reload();
        updateClearFilterButton();
        updateFilterButtonsStyle();
    });

    $(document).on('click', '.btn-sort', function () {
        if (typeof sortConfig === 'undefined' || !sortConfig.enabled) {
            return;
        }

        const field = $(this).data('sort-field');
        if (!field) {
            return;
        }

        if (currentSortColumn === field) {
            if (currentSortDirection === 'asc') {
                currentSortDirection = 'desc';
            } else {
                currentSortColumn = '';
                currentSortDirection = '';
            }
        } else {
            currentSortColumn = field;
            currentSortDirection = 'asc';
        }

        updateSortButtonsUI();
        table.ajax.reload();
    });

    $(document).on('click', '#filterBtnClear', function () {
        $('input[type="checkbox"]').prop('checked', false);
        $('#from_date').val('');
        $('#to_date').val('');
        if ($('#type').length) {
            $('#type').val('');
        }
        table.columns().search('');
        currentSortColumn = '';
        currentSortDirection = '';
        updateSortButtonsUI();
        table.ajax.reload(null, false);
        $('#filterBtnClear').addClass('d-none');
        updateFilterButtonsStyle();
    });

    $(document).on('click', '#refreshData', function () {
        table.ajax.reload();
    });

    $(document).on('input', '.search-checkbox', function () {
        const searchValue = $(this).val().toLowerCase();
        const tdIndex = $(this).data('index');
        $('.checkbox-list-' + tdIndex + ' label').each(function () {
            const labelText = $(this).text().toLowerCase();
            const checkbox = $(this).find('input');
            if (labelText.indexOf(searchValue) !== -1) {
                $(this).show();
            } else {
                $(this).hide();
                if (checkbox.prop('checked')) {
                    checkbox.prop('checked', false);
                }
            }
        });
    });

    $(document).on('change', '.all-checkbox', function () {
        const index = $(this).data('index');
        $('.checkbox-list-' + index + ' input[type="checkbox"]:visible').prop('checked', $(this).prop('checked'));
    });

    $('th .dropdown-menu .checkbox-list-box').on('click', function (e) {
        e.stopPropagation();
    });

    let deleteItemId = null;

    $(document).on('click', '.delete_row', function () {
        deleteItemId = $(this).attr('data-id');
        if (!deleteItemId) {
            return;
        }

        if (typeof window.confirmAction === 'function') {
            window.confirmAction({
                title: 'تأكيد الحذف',
                message: 'هل أنت متأكد من حذف هذا العنصر؟ لا يمكن التراجع عن هذا الإجراء.',
                variant: 'danger',
                onConfirm: function () {
                    if (deleteItemId) {
                        deleteRow(deleteItemId);
                        deleteItemId = null;
                    }
                },
            });
        } else if ($('#deleteConfirmModal').length) {
            $('#deleteConfirmModal').modal('show');
        }
    });

    $(document).on('click', '#confirmDeleteBtn', function () {
        if (deleteItemId) {
            deleteRow(deleteItemId);
            $('#deleteConfirmModal').modal('hide');
            deleteItemId = null;
        }
    });

    function deleteRow(id) {
        const url = typeof buildDeleteUrl === 'function'
            ? buildDeleteUrl(id)
            : urlDelete.replace(':id', id);
        $.ajax({
            url: url,
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            data: { _token: _token },
            success: function () {
                toastr.success('تم حذف العنصر بنجاح');
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                let message = 'هنالك خطأ في عملية الحذف.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                toastr.error(message);
            },
        });
    }

    updateClearFilterButton();
});
