<div class="modal fade" id="checklistAttachmentDeleteModal" tabindex="-1" aria-labelledby="checklistAttachmentDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="checklistAttachmentDeleteModalLabel">تأكيد حذف المرفق</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-2 text-muted small">هل تريد حذف هذا المرفق؟ لا يمكن التراجع عن هذا الإجراء.</p>
                <p class="mb-0 fw-medium text-truncate" id="checklistAttachmentDeleteFileName" title=""></p>
            </div>
            <div class="modal-footer border-0 pt-0 gap-2">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">إلغاء</button>
                <button type="button" class="btn btn-danger" id="checklistAttachmentDeleteConfirmBtn">حذف المرفق</button>
            </div>
            <form action="#" method="post" id="checklistAttachmentDeleteForm" class="d-none">
                @csrf
                <input type="hidden" name="checklist_item_id" id="checklistAttachmentDeleteItemId" value="">
            </form>
        </div>
    </div>
</div>
