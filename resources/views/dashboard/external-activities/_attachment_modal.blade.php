<div class="modal fade" id="activityAttachmentUploadModal" tabindex="-1" aria-labelledby="activityAttachmentUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="activityAttachmentUploadModalLabel">إضافة مرفق</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-pills nav-fill mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="activity-upload-tab-file" data-bs-toggle="pill" data-bs-target="#activity-upload-pane-file" type="button" role="tab">
                            <i class="ti ti-upload me-1"></i> رفع ملف
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-upload-tab-url" data-bs-toggle="pill" data-bs-target="#activity-upload-pane-url" type="button" role="tab">
                            <i class="ti ti-link me-1"></i> رابط خارجي
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="activity-upload-pane-file" role="tabpanel">
                        <label for="activityAttachmentUploadFileInput" class="form-label">اختر ملفاً أو أكثر</label>
                        <input type="file" class="form-control" id="activityAttachmentUploadFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" multiple>
                        <div class="form-text">الحد الأقصى 10 ميجابايت لكل ملف.</div>
                    </div>
                    <div class="tab-pane fade" id="activity-upload-pane-url" role="tabpanel">
                        <label for="activityAttachmentUploadUrlInput" class="form-label">رابط المستند</label>
                        <input type="url" class="form-control" id="activityAttachmentUploadUrlInput" placeholder="https://example.com/document">
                        <div class="form-text">أدخل رابطاً كاملاً يبدأ بـ http:// أو https://</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-primary" id="activityAttachmentUploadConfirmBtn">تأكيد</button>
            </div>
        </div>
    </div>
</div>
