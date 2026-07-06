<div class="modal fade" id="appConfirmModal" tabindex="-1" aria-labelledby="appConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title d-flex align-items-center gap-2" id="appConfirmModalLabel">
                    <span class="app-confirm-icon text-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </span>
                    <span id="appConfirmModalTitle">تأكيد الإجراء</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="mb-0 text-body" id="appConfirmModalMessage"></p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-start gap-2">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="appConfirmModalConfirmBtn">
                    <i class="fas fa-check me-1"></i> تأكيد
                </button>
            </div>
        </div>
    </div>
</div>
