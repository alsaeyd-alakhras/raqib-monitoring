@once
    @push('styles')
    <style>
        .monitoring-setup-panel .setup-step {
            border: 1px solid rgba(67, 89, 113, 0.12);
            border-radius: 0.5rem;
            padding: 1rem 1.125rem;
            background: #fff;
            height: 100%;
        }

        .monitoring-setup-panel .setup-step + .setup-step {
            margin-top: 0;
        }

        @media (min-width: 992px) {
            .monitoring-setup-panel .setup-step + .setup-step {
                margin-top: 0;
            }
        }

        .monitoring-setup-panel .setup-step-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--bs-primary);
            margin-bottom: 0.875rem;
        }

        .monitoring-setup-panel .setup-step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: rgba(var(--bs-primary-rgb), 0.12);
            color: var(--bs-primary);
            font-size: 0.75rem;
            flex-shrink: 0;
        }

        .monitoring-setup-panel .saved-value-chip {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            margin-inline-start: 0.35rem;
            border-radius: 0.375rem;
            background: rgba(40, 199, 111, 0.12);
            color: #28c76f;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
    @endpush
@endonce

<div class="card mb-4 monitoring-setup-panel">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">إعداد المراقبة</h5>
        <span class="badge bg-label-primary">مدير الرقابة العامة</span>
    </div>
    <div class="card-body">
        @if ($project->monitoring_method || $project->monitoring_stage)
            <div class="d-flex flex-wrap gap-2 mb-3">
                @if ($project->monitoring_method)
                    <span class="badge bg-label-info">الطريقة: {{ $project->monitoring_method }}</span>
                @endif
                @if ($project->monitoring_stage)
                    <span class="badge bg-label-info">المرحلة: {{ $project->monitoring_stage }}</span>
                @endif
            </div>
        @endif

        <div class="row g-3">
            @if ($canSetMonitoringInfo ?? false)
            <div class="col-lg-6">
                <div class="setup-step">
                    <div class="setup-step-title">
                        <span class="setup-step-num">1</span>
                        <span>تحديد طريقة ومرحلة المراقبة</span>
                    </div>
                    <form action="{{ route('dashboard.projects.set-monitoring-info', $project) }}" method="post">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="monitoring_method">طريقة المراقبة</label>
                                <select name="monitoring_method" id="monitoring_method" class="form-select">
                                    <option value="">إختر القيمة</option>
                                    @foreach ($monitoringMethods as $method)
                                        <option value="{{ $method }}" @selected($project->monitoring_method === $method)>{{ $method }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="monitoring_stage">مرحلة المراقبة</label>
                                <select name="monitoring_stage" id="monitoring_stage" class="form-select">
                                    <option value="">إختر القيمة</option>
                                    @foreach ($monitoringStages as $stage)
                                        <option value="{{ $stage }}" @selected($project->monitoring_stage === $stage)>{{ $stage }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> حفظ طريقة/مرحلة المراقبة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            @if ($canAssignMonitor ?? false)
            <div class="col-lg-6">
                <div class="setup-step">
                    <div class="setup-step-title">
                        <span class="setup-step-num">2</span>
                        <span>تعيين المراقب وبدء المراقبة</span>
                    </div>
                    @if (! $project->monitoring_method && ! $project->monitoring_stage)
                        <p class="text-muted small mb-3">يُفضّل حفظ طريقة ومرحلة المراقبة أولاً، ثم تعيين المراقب.</p>
                    @endif
                    <form action="{{ route('dashboard.projects.assign-monitor', $project) }}" method="post">
                        @csrf
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="monitor_person_id">المراقب</label>
                                <select name="monitor_person_id" id="monitor_person_id" class="form-select" required>
                                    <option value="">إختر القيمة</option>
                                    @foreach ($monitors as $person)
                                        <option value="{{ $person->id }}" @selected($project->monitor_person_id == $person->id)>{{ $person->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="monitoring_date">تاريخ المراقبة</label>
                                <input type="date" name="monitoring_date" id="monitoring_date" class="form-control" value="{{ $project->monitoring_date?->format('Y-m-d') }}">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-user-check me-1"></i> تعيين وبدء المراقبة
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>

        @if ($canRejectThisProject ?? false)
            <div class="border-top pt-3 mt-3">
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#projectRejectModal">
                    رفض المشروع
                </button>
            </div>
        @endif
    </div>
</div>
