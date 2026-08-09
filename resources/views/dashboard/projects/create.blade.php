<x-front-layout>
    <form id="project-create-form" action="{{ route('dashboard.projects.store') }}" method="post" enctype="multipart/form-data" class="col-12">
        @csrf
        @include('dashboard.projects._form')
    </form>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('project-create-form');
                if (!form) {
                    return;
                }

                form.addEventListener('keydown', function (event) {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    const target = event.target;
                    if (!(target instanceof HTMLElement)) {
                        return;
                    }

                    if (target.tagName === 'TEXTAREA') {
                        return;
                    }

                    if (target.closest('.select2-container')) {
                        return;
                    }

                    if (target.tagName === 'BUTTON' && target.type === 'submit') {
                        return;
                    }

                    if (target.tagName === 'INPUT' && target.type === 'submit') {
                        return;
                    }

                    event.preventDefault();
                });
            });
        </script>
    @endpush

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
