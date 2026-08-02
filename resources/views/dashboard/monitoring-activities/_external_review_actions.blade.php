@if ($canDirectorReviewExternal ?? false)
    <div class="card mb-4 border-warning">
        <div class="card-header bg-label-warning">
            <h5 class="mb-0">مراجعة مدير الرقابة — نشاط خارجي</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">يمكنك اعتماد النشاط أو إرجاعه للمراقب أو رفضه نهائياً.</p>
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('dashboard.external-activities.approve', $activity) }}" method="post" class="d-inline"
                    data-confirm="اعتماد هذا النشاط الخارجي؟" data-confirm-title="تأكيد الاعتماد" data-confirm-variant="success">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bx bx-check"></i> اعتماد النشاط
                    </button>
                </form>
                <button type="button" class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#externalReturnModal">
                    <i class="bx bx-undo"></i> إرجاع للمراقب
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#externalRejectModal">
                    <i class="bx bx-x"></i> رفض نهائي
                </button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="externalReturnModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('dashboard.external-activities.return', $activity) }}" method="post">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">إرجاع للمراقب</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">سبب الإرجاع</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مسؤولية النقص</label>
                            <select name="gap_owner" class="form-select" required>
                                <option value="monitor">المراقب</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-warning">إرجاع للمراقب</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="externalRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('dashboard.external-activities.reject', $activity) }}" method="post"
                    data-confirm="رفض هذا النشاط نهائياً؟" data-confirm-title="تأكيد الرفض" data-confirm-variant="danger">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">رفض نهائي</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">سبب الرفض</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مسؤولية النقص</label>
                            <select name="gap_owner" class="form-select" required>
                                <option value="monitor">المراقب</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-danger">رفض نهائي</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
