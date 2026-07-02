@php
    $statusLabels = [
        'draft' => 'مسودة',
        'pending_coordinator' => 'بانتظار المنسق',
        'coordinator_filling' => 'المنسق يعمل',
        'pending_dept_manager' => 'بانتظار مدير الدائرة',
        'pending_monitoring_manager' => 'بانتظار مدير الرقابة العامة',
        'monitoring_in_progress' => 'قيد المراقبة',
        'rejected' => 'مرفوض',
    ];
    $valueLabels = [
        'ready' => 'جاهز',
        'partial' => 'جزئي',
        'not_ready' => 'غير جاهز',
        'not_required' => 'غير مطلوب',
    ];
    $readinessStatusLabels = [
        'stopped' => '🔴 موقوف — يحتاج مراجعة',
        'partially_ready' => '🔶 جاهز جزئياً — يحتاج متابعة',
        'ready' => '✅ جاهز للتنفيذ — موصى بالمتابعة',
    ];
    $canFillCoordinator = auth()->user()?->can('fill_coordinator', 'App\Models\Project');
    $canApproveDept = auth()->user()?->can('approve_department', 'App\Models\Project');
    $canReject = auth()->user()?->can('reject', 'App\Models\Project');
    $canUpdate = auth()->user()?->can('update', 'App\Models\Project');
@endphp
<x-front-layout>
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $project->project_name }}</h5>
            <span class="badge bg-label-{{ $project->workflow_status === 'rejected' ? 'danger' : 'info' }} fs-6">
                {{ $statusLabels[$project->workflow_status] ?? $project->workflow_status }}
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong>رقم المشروع:</strong> {{ $project->project_number ?? '-' }}</div>
                <div class="col-md-3"><strong>النوع:</strong> {{ $project->project_type ?? '-' }}</div>
                <div class="col-md-3"><strong>الممول:</strong> {{ $project->funder?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>المركز/الدائرة/القسم:</strong> {{ $project->center?->name }} / {{ $project->department?->name }} / {{ $project->section?->name }}</div>
                <div class="col-md-3"><strong>مدير المشروع:</strong> {{ $project->projectManager?->name ?? '-' }}</div>
                @if ($canViewCoordinatorData)
                    <div class="col-md-3"><strong>المنسق:</strong> {{ $project->coordinator?->name ?? '-' }}</div>
                @endif
                <div class="col-md-3"><strong>المراقب:</strong> {{ $project->monitorPerson?->name ?? '-' }}</div>
                <div class="col-md-3"><strong>المستفيدون المستهدفون:</strong> {{ $project->target_beneficiaries ?? '-' }}</div>
            </div>
            <div class="mt-3">
                @can('update', 'App\Models\Project')
                    <a href="{{ route('dashboard.projects.edit', $project) }}" class="btn btn-sm btn-outline-primary">تعديل بيانات المشروع</a>
                @endcan
                @if ($project->primaryMonitoringActivity)
                    <a href="{{ route('dashboard.monitoring-activities.edit', $project->primaryMonitoringActivity) }}" class="btn btn-sm btn-outline-secondary">النشاط الرقابي الأساسي ({{ $project->primaryMonitoringActivity->reference_code }})</a>
                @endif
            </div>
        </div>
    </div>

    {{-- سير العمل: دورة الاعتماد الإدارية --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">سير العمل</h5></div>
        <div class="card-body">
            @if ($project->workflow_status === 'draft' && $canUpdate)
                <form action="{{ route('dashboard.projects.submit-to-coordinator', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال للمنسق</button>
                </form>
            @endif

            @if (in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling']) && $canFillCoordinator)
                <form action="{{ route('dashboard.projects.submit-to-dept-manager', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">إرسال لمدير الدائرة</button>
                </form>
            @endif

            @if ($project->workflow_status === 'pending_dept_manager' && $canApproveDept)
                <form action="{{ route('dashboard.projects.approve-department', $project) }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-primary">موافقة وإرسال لمدير الرقابة العامة</button>
                </form>
            @endif

            @if ($project->workflow_status === 'pending_monitoring_manager' && ($canSetMonitoringInfo || $canAssignMonitor))
                @if ($canSetMonitoringInfo)
                <form action="{{ route('dashboard.projects.set-monitoring-info', $project) }}" method="post" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">طريقة المراقبة</label>
                        <select name="monitoring_method" class="form-select">
                            <option value="">إختر القيمة</option>
                            @foreach ($monitoringMethods as $method)
                                <option value="{{ $method }}" @selected($project->monitoring_method === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">مرحلة المراقبة</label>
                        <select name="monitoring_stage" class="form-select">
                            <option value="">إختر القيمة</option>
                            @foreach ($monitoringStages as $stage)
                                <option value="{{ $stage }}" @selected($project->monitoring_stage === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary">حفظ طريقة/مرحلة المراقبة</button>
                    </div>
                </form>
                @endif

                @if ($canAssignMonitor)
                <form action="{{ route('dashboard.projects.assign-monitor', $project) }}" method="post" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label">تعيين المراقب</label>
                        <select name="monitor_person_id" class="form-select" required>
                            <option value="">إختر القيمة</option>
                            @foreach ($people as $person)
                                <option value="{{ $person->id }}" @selected($project->monitor_person_id == $person->id)>{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ المراقبة</label>
                        <input type="date" name="monitoring_date" class="form-control" value="{{ $project->monitoring_date?->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-success">تعيين وبدء المراقبة</button>
                    </div>
                </form>
                @endif
            @endif

            @if ($project->workflow_status === 'monitoring_in_progress')
                <a href="{{ route('dashboard.projects.monitor-work', $project) }}" class="btn btn-outline-primary">شاشة عمل المراقب</a>
                <span class="ms-3">حالة الجاهزية: {{ $readinessStatusLabels[$project->readiness_status] ?? '-' }}</span>
            @endif

            @if ($project->workflow_status === 'rejected')
                <div class="alert alert-danger">
                    <div><strong>سبب الرفض:</strong> {{ $project->rejection_reason }}</div>
                    <div><strong>مسؤولية النقص:</strong> {{ $project->gap_owner }}</div>
                    <div><strong>رُفض بواسطة:</strong> {{ $project->rejectedByUser?->name ?? '-' }}</div>
                    <div><strong>رُفض بتاريخ:</strong> {{ $project->rejected_at }}</div>
                </div>
                @if ($canReject)
                    <form action="{{ route('dashboard.projects.reroute', $project) }}" method="post" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">إعادة التوجيه إلى</label>
                            <select name="workflow_status" class="form-select" required>
                                <option value="pending_coordinator">بانتظار المنسق</option>
                                <option value="coordinator_filling">المنسق يعمل</option>
                                <option value="pending_dept_manager">بانتظار مدير الدائرة</option>
                                <option value="pending_monitoring_manager">بانتظار مدير الرقابة العامة</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary">إعادة التوجيه</button>
                        </div>
                    </form>
                @endif
            @endif

            @if ($canReject && ! in_array($project->workflow_status, ['rejected', 'monitoring_in_progress']))
                <hr>
                <form action="{{ route('dashboard.projects.reject', $project) }}" method="post" onsubmit="return confirm('هل أنت متأكد من رفض المشروع؟');">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="form-label">سبب الرفض</label>
                            <textarea name="rejection_reason" class="form-control" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">مسؤولية النقص</label>
                            <select name="gap_owner" class="form-select" required>
                                <option value="coordinator">المنسق</option>
                                <option value="dept_manager">مدير الدائرة</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-outline-danger">رفض المشروع</button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- قائمة التحقق — عمود المنسق --}}
    @if ($canViewCoordinatorData)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة التحقق — عمود المنسق</h5>
            <span>نسبة الجاهزية: {{ $project->coordinator_readiness_pct !== null ? $project->coordinator_readiness_pct . '%' : '-' }}</span>
        </div>
        <div class="card-body">
            @if (in_array($project->workflow_status, ['pending_coordinator', 'coordinator_filling']) && $canFillCoordinator)
                <form action="{{ route('dashboard.projects.fill-coordinator', $project) }}" method="post">
                    @csrf
                    @foreach ($groups as $group)
                        <h6 class="mt-3">{{ $group->name }}</h6>
                        <table class="table table-sm table-bordered">
                            <tbody>
                                @foreach ($group->items as $item)
                                    @php $current = $values->get($item->id); @endphp
                                    <tr>
                                        <td class="align-middle">{{ $item->name }}</td>
                                        <td style="max-width:220px">
                                            <select name="checklist[{{ $item->id }}][value]" class="form-select form-select-sm">
                                                <option value="">-</option>
                                                @foreach ($valueLabels as $key => $label)
                                                    <option value="{{ $key }}" @selected($current?->coordinator_value === $key)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        @if ($item->has_person_field)
                                            <td style="max-width:220px">
                                                <input type="text" name="checklist[{{ $item->id }}][person_name]" class="form-control form-control-sm" placeholder="اسم الشخص" value="{{ $current?->person_name }}">
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                    <button type="submit" class="btn btn-primary">حفظ عمود المنسق</button>
                </form>
            @else
                @foreach ($groups as $group)
                    <h6 class="mt-3">{{ $group->name }}</h6>
                    <table class="table table-sm table-bordered">
                        <tbody>
                            @foreach ($group->items as $item)
                                @php $current = $values->get($item->id); @endphp
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $valueLabels[$current?->coordinator_value] ?? '-' }}</td>
                                    @if ($item->has_person_field)
                                        <td>{{ $current?->person_name ?? '-' }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endif
        </div>
    </div>
    @endif

    {{-- قائمة التحقق — عمود المراقب (عرض فقط هنا، التعديل من شاشة المراقب المعزولة) --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">قائمة التحقق — عمود المراقب</h5>
            <span>نسبة الجاهزية: {{ $project->monitor_readiness_pct !== null ? $project->monitor_readiness_pct . '%' : '-' }}</span>
        </div>
        <div class="card-body">
            @foreach ($groups as $group)
                <h6 class="mt-3">{{ $group->name }}</h6>
                <table class="table table-sm table-bordered">
                    <tbody>
                        @foreach ($group->items as $item)
                            @php $current = $values->get($item->id); @endphp
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $valueLabels[$current?->monitor_value] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
            @if ($project->monitor_notes)
                <div><strong>ملاحظات المراقب:</strong>
                    <ul>@foreach ($project->monitor_notes as $note)<li>{{ $note }}</li>@endforeach</ul>
                </div>
            @endif
            @if ($project->monitor_recommendations)
                <div><strong>توصيات المراقب:</strong>
                    <ul>@foreach ($project->monitor_recommendations as $rec)<li>{{ $rec }}</li>@endforeach</ul>
                </div>
            @endif
        </div>
    </div>
</x-front-layout>
