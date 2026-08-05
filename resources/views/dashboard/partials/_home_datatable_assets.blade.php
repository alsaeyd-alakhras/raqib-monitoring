@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/datatable/jquery.dataTables.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.bootstrap4.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.dataTables.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/stickyTable.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/datatableIndex.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/datatableIndex2.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/raqib-datatable-sticky.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/home-datatable.css') }}">
    @endpush

    @push('scripts')
        <script src="{{ asset('js/plugins/datatable/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('js/plugins/datatable/dataTables.js') }}"></script>
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
                        scrollY: '360px',
                        scrollCollapse: true,
                        paging: true,
                        autoWidth: false,
                        responsive: false,
                        language: { url: arabicFileJson },
                        columnDefs: [{ targets: 'no-sort', orderable: false }],
                        dom: '<"top d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pt-3"l f>rt<"bottom d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 pb-3"ip>',
                    });
                });
            });
        </script>
    @endpush
@endonce
