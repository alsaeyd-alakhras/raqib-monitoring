<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">بيانات مدير المشروع</h5>
    </div>
    <div class="card-body">
        @if ($canEditPmFields ?? false)
            <form action="{{ route('dashboard.projects.fill-pm-fields', $project) }}" method="post">
                @csrf
                <div class="row">
                    <div class="mb-4 col-md-4">
                        <x-form.textarea
                            name="coordinator_requirements"
                            label="مطلوب من المنسق"
                            :value="$project->coordinator_requirements ?? ''"
                            rows="1"
                        />
                    </div>
                    <div class="mb-4 col-md-4">
                        <x-form.textarea
                            name="project_lifecycle_notes"
                            label="دورة حياة المشروع"
                            :value="$project->project_lifecycle_notes ?? ''"
                            rows="1"
                        />
                    </div>
                    <div class="mb-4 col-md-4">
                        <x-form.textarea
                            name="pm_recommendations"
                            label="التوصيات"
                            :value="$project->pm_recommendations ?? ''"
                            rows="1"
                        />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">حفظ بيانات مدير المشروع</button>
            </form>
        @else
            <div class="row">
                <div class="mb-3 col-md-4">
                    <label class="form-label text-muted mb-1">مطلوب من المنسق</label>
                    <div class="border rounded p-3 bg-light-subtle min-h-80">
                        {!! nl2br(e($project->coordinator_requirements ?: '—')) !!}
                    </div>
                </div>
                <div class="mb-3 col-md-4">
                    <label class="form-label text-muted mb-1">دورة حياة المشروع</label>
                    <div class="border rounded p-3 bg-light-subtle min-h-80">
                        {!! nl2br(e($project->project_lifecycle_notes ?: '—')) !!}
                    </div>
                </div>
                <div class="mb-3 col-md-4">
                    <label class="form-label text-muted mb-1">التوصيات</label>
                    <div class="border rounded p-3 bg-light-subtle min-h-80">
                        {!! nl2br(e($project->pm_recommendations ?: '—')) !!}
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
