@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/datatable/jquery.dataTables.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/datatable/dataTables.bootstrap4.css') }}">
        <link rel="stylesheet" href="{{ asset('css/custom2/home-datatable.css') }}">
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
            });
        </script>
    @endpush
@endonce
