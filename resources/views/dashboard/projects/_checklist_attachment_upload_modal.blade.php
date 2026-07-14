<div class="modal fade" id="checklistAttachmentUploadModal" tabindex="-1" aria-labelledby="checklistAttachmentUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="checklistAttachmentUploadModalLabel">إضافة مرفق</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="checklistAttachmentUploadItemName"></p>
                <ul class="nav nav-pills nav-fill mb-3" id="checklistAttachmentUploadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="checklist-upload-tab-file" data-bs-toggle="pill" data-bs-target="#checklist-upload-pane-file" type="button" role="tab">
                            <i class="ti ti-upload me-1"></i> رفع ملف
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="checklist-upload-tab-url" data-bs-toggle="pill" data-bs-target="#checklist-upload-pane-url" type="button" role="tab">
                            <i class="ti ti-link me-1"></i> رابط خارجي
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="checklist-upload-pane-file" role="tabpanel">
                        <label for="checklistAttachmentUploadFileInput" class="form-label">اختر ملفاً</label>
                        <input type="file" class="form-control" id="checklistAttachmentUploadFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <div class="form-text">الحد الأقصى 10 ميجابايت — PDF، Word، Excel، صور.</div>
                    </div>
                    <div class="tab-pane fade" id="checklist-upload-pane-url" role="tabpanel">
                        <label for="checklistAttachmentUploadUrlInput" class="form-label">رابط المستند</label>
                        <input type="url" class="form-control" id="checklistAttachmentUploadUrlInput" placeholder="https://example.com/document">
                        <div class="form-text">أدخل رابطاً كاملاً يبدأ بـ http:// أو https://</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="checklistAttachmentUploadConfirmBtn">تأكيد</button>
            </div>
        </div>
    </div>
</div>
