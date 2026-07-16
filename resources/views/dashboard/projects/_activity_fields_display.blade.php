@php
    $activity = $activity ?? null;
@endphp

@if (! $activity)
    <div class="alert alert-warning mb-0">لا يوجد نشاط رقابي أساسي مرتبط بهذا المشروع.</div>
@else
    @php
        $activity->loadMissing(['responsiblePerson', 'funder']);
    @endphp

    <div class="row g-3">
        <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <th scope="row" class="text-muted" style="width: 40%;">رمز النشاط</th>
                        <td>{{ $activity->reference_code }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">المسؤول عن النشاط</th>
                        <td>{{ $activity->responsiblePerson?->name ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">التاريخ</th>
                        <td>{{ $activity->activity_date?->format('Y-m-d') ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">الوقت</th>
                        <td>{{ $activity->activity_time ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">نوع النشاط</th>
                        <td>{{ $activity->activity_type ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <th scope="row" class="text-muted" style="width: 40%;">الموضوع</th>
                        <td>{{ $activity->subject ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">ملاحظة النشاط</th>
                        <td>{{ $activity->notes ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">مشكلة ميدانية؟</th>
                        <td>{{ $activity->field_problem ? 'نعم' : 'لا' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">الإجراء المتخذ</th>
                        <td>{{ $activity->action_taken ?: '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-12">
            <hr class="my-1">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <th scope="row" class="text-muted" style="width: 15%;">التنفيذ</th>
                        <td style="width: 18%;">{{ $activity->execution_value !== null ? $activity->execution_value : '—' }}</td>
                        <th scope="row" class="text-muted" style="width: 12%;">الجودة</th>
                        <td style="width: 18%;">{{ $activity->quality_value !== null ? $activity->quality_value : '—' }}</td>
                        <th scope="row" class="text-muted" style="width: 12%;">الإغلاق</th>
                        <td style="width: 18%;">{{ $activity->closure_value !== null ? $activity->closure_value : '—' }}</td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">الخصم</th>
                        <td>{{ $activity->deduction_value !== null ? $activity->deduction_value : '—' }}</td>
                        <th scope="row" class="text-muted">KPI</th>
                        <td colspan="3">
                            @if ($activity->kpi_value !== null)
                                {{ $activity->kpi_value }}
                                @if ($activity->kpi_rating)
                                    <span class="badge bg-label-info">{{ $activity->kpi_rating }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th scope="row" class="text-muted">حالة التحقق</th>
                        <td colspan="5">{{ $activity->verification_status }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
