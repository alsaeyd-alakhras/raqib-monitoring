<x-front-layout>
    <form action="{{ route('dashboard.projects.store') }}" method="post" enctype="multipart/form-data" class="col-12">
        @csrf
        @include('dashboard.projects._form')
    </form>

    @if ($canEditProjectDocs ?? false)
        @include('dashboard.projects._checklist_attachment_delete_modal')
        @include('dashboard.projects._checklist_attachment_upload_modal')

        @push('scripts')
            <script src="{{ asset('js/checklist-attachment-ui.js') }}"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.initChecklistAttachmentUi) {
                        window.initChecklistAttachmentUi(document);
                    }
                });
            </script>
        @endpush
    @endif
</x-front-layout>
