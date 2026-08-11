@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/datatable/jquery.dataTables.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.bootstrap4.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/home-datatable.css') }}">
        <style>
            .raqib-home-panel-toggle {
                width: 2rem;
                height: 2rem;
                padding: 0;
            }

            .raqib-home-panel-chevron {
                transition: transform 0.2s ease;
            }

            .raqib-home-panel-toggle[aria-expanded="false"] .raqib-home-panel-chevron {
                transform: rotate(-90deg);
            }
        </style>
    @endpush

    @push('scripts')
        <script src="{{ asset('js/plugins/datatable/jquery.dataTables.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof $.fn.DataTable === 'undefined') {
                    return;
                }

                const arabicFileJson = "{{ asset('files/Arabic.json') }}";

                $('table.home-dt').each(function () {
                    if ($.fn.DataTable.isDataTable(this)) {
                        return;
                    }

                    $(this).DataTable({
                        serverSide: false,
                        ordering: true,
                        order: [],
                        searching: true,
                        pageLength: 10,
                        lengthMenu: [10, 25, 50, 100],
                        paging: true,
                        autoWidth: false,
                        responsive: false,
                        language: { url: arabicFileJson },
                        columnDefs: [{ targets: 'no-sort', orderable: false }],
                        dom: '<"home-dt-toolbar"l f>rt<"home-dt-footer"ip>',
                    });
                });

                const storageKeys = {
                    pending_approval: 'raqib.home.pending_approval_collapsed',
                    action_items: 'raqib.home.action_items_collapsed',
                    active_projects: 'raqib.home.active_projects_collapsed',
                    pipeline_executions: 'raqib.home.pipeline_executions_collapsed',
                };

                function adjustHomePanelTables(panelName) {
                    const table = document.querySelector('[data-home-panel-table="' + panelName + '"]');
                    if (!table || !$.fn.DataTable.isDataTable(table)) {
                        return;
                    }

                    $(table).DataTable().columns.adjust();
                }

                document.querySelectorAll('.raqib-home-collapsible-card[data-home-panel]').forEach(function (card) {
                    const panelName = card.getAttribute('data-home-panel');
                    const storageKey = storageKeys[panelName];
                    const collapseEl = card.querySelector('.collapse');
                    const toggleBtn = card.querySelector('.raqib-home-panel-toggle');

                    if (!collapseEl || !storageKey || !toggleBtn || typeof bootstrap === 'undefined') {
                        return;
                    }

                    const collapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                    const isCollapsed = localStorage.getItem(storageKey) === '1';

                    if (isCollapsed) {
                        collapse.hide();
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    }

                    collapseEl.addEventListener('hidden.bs.collapse', function () {
                        localStorage.setItem(storageKey, '1');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    });

                    collapseEl.addEventListener('shown.bs.collapse', function () {
                        localStorage.setItem(storageKey, '0');
                        toggleBtn.setAttribute('aria-expanded', 'true');
                        adjustHomePanelTables(panelName);
                    });
                });
            });
        </script>
    @endpush
@endonce
