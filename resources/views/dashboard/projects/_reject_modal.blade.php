@if ($canRejectThisProject ?? false)
    <div class="modal fade" id="projectRejectModal" tabindex="-1" aria-labelledby="projectRejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="{{ route('dashboard.projects.reject', $project) }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="projectRejectModalLabel">رفض المشروع</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small mb-3">
                            سيتم تغيير حالة المشروع إلى «مرفوض» مع تسجيل السبب ومسؤول النقص.
                        </p>
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">سبب الرفض <span class="text-danger">*</span></label>
                            <textarea
                                name="rejection_reason"
                                id="rejection_reason"
                                class="form-control @error('rejection_reason') is-invalid @enderror"
                                rows="4"
                                required
                                placeholder="اذكر سبب الرفض بوضوح..."
                            >{{ old('rejection_reason') }}</textarea>
                            @error('rejection_reason')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-0">
                            <label for="gap_owner" class="form-label">مسؤولية النقص <span class="text-danger">*</span></label>
                            <select
                                name="gap_owner"
                                id="gap_owner"
                                class="form-select @error('gap_owner') is-invalid @enderror"
                                required
                            >
                                <option value="">إختر من عند من النقص</option>
                                @foreach ($gapOwnerOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('gap_owner') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('gap_owner')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
