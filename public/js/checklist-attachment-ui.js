/**
 * Icon-based checklist attachment UI: upload, view, delete with modal confirm.
 */
(function () {
    'use strict';

    let pendingDeleteContext = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getModal() {
        return document.getElementById('checklistAttachmentDeleteModal');
    }

    function getModalInstance() {
        const modal = getModal();

        if (!modal || !window.bootstrap?.Modal) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modal);
    }

    function renderActions(fileField, state) {
        const actions = fileField.querySelector('.checklist-file-actions');

        if (!actions) {
            return;
        }

        if (state === 'saved') {
            const url = fileField.dataset.attachmentUrl || '#';
            const name = fileField.dataset.attachmentName || 'مرفق';

            actions.innerHTML = `
                <a href="${escapeHtml(url)}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                   title="عرض المرفق">
                    <i class="ti ti-eye"></i>
                </a>
                <button type="button"
                        class="btn btn-sm btn-icon btn-text-danger checklist-file-delete-btn"
                        title="حذف المرفق"
                        aria-label="حذف المرفق">
                    <i class="ti ti-trash"></i>
                </button>
            `;

            fileField.dataset.hasAttachment = '1';
            fileField.dataset.attachmentName = name;
            return;
        }

        if (state === 'pending') {
            const fileName = fileField.dataset.pendingFileName || 'ملف محدد';

            actions.innerHTML = `
                <span class="checklist-file-pending-name text-truncate" title="${escapeHtml(fileName)}">${escapeHtml(fileName)}</span>
                <button type="button"
                        class="btn btn-sm btn-icon btn-text-danger checklist-file-clear-btn"
                        title="إلغاء الاختيار"
                        aria-label="إلغاء الاختيار">
                    <i class="ti ti-trash"></i>
                </button>
            `;

            fileField.dataset.hasAttachment = '0';
            return;
        }

        actions.innerHTML = `
            <button type="button"
                    class="btn btn-sm btn-icon btn-text-secondary checklist-file-upload-btn"
                    title="رفع ملف"
                    aria-label="رفع ملف">
                <i class="ti ti-upload"></i>
            </button>
        `;

        fileField.dataset.hasAttachment = '0';
        delete fileField.dataset.pendingFileName;
    }

    function syncFileField(fileField) {
        const fileInput = fileField.querySelector('.checklist-file-input');

        if (!fileInput) {
            return;
        }

        if (fileInput.files?.length) {
            fileField.dataset.pendingFileName = fileInput.files[0].name;
            renderActions(fileField, 'pending');
            return;
        }

        if (fileField.dataset.hasAttachment === '1' && fileField.dataset.attachmentUrl) {
            renderActions(fileField, 'saved');
            return;
        }

        renderActions(fileField, 'empty');
    }

    function openDeleteConfirmModal(trigger, mode) {
        const modal = getModal();
        const fileField = trigger?.closest('[data-closure-file-field]');
        const nameEl = document.getElementById('checklistAttachmentDeleteFileName');
        const form = document.getElementById('checklistAttachmentDeleteForm');
        const itemInput = document.getElementById('checklistAttachmentDeleteItemId');
        const modalInstance = getModalInstance();

        if (!modal || !fileField || !nameEl || !modalInstance) {
            return;
        }

        const fileName = mode === 'pending'
            ? (fileField.dataset.pendingFileName || 'الملف المحدد')
            : (fileField.dataset.attachmentName || 'مرفق');

        nameEl.textContent = fileName;
        nameEl.title = fileName;

        pendingDeleteContext = {
            mode,
            fileField,
            fileInput: fileField.querySelector('.checklist-file-input'),
            deleteUrl: fileField.dataset.deleteUrl || '',
            itemId: fileField.dataset.itemId || '',
        };

        if (mode === 'saved' && form && itemInput) {
            form.action = pendingDeleteContext.deleteUrl || '#';
            itemInput.value = pendingDeleteContext.itemId;
        }

        modalInstance.show();
    }

    function confirmDelete() {
        if (!pendingDeleteContext) {
            return;
        }

        const { mode, fileField, fileInput } = pendingDeleteContext;
        const form = document.getElementById('checklistAttachmentDeleteForm');
        const modalInstance = getModalInstance();

        if (mode === 'pending') {
            if (fileInput) {
                fileInput.value = '';
                syncFileField(fileField);
                fileInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            pendingDeleteContext = null;
            modalInstance?.hide();
            return;
        }

        if (mode === 'saved' && form && pendingDeleteContext.deleteUrl) {
            form.submit();
            return;
        }

        pendingDeleteContext = null;
        modalInstance?.hide();
    }

    function bindDeleteModal() {
        const modal = getModal();
        const confirmBtn = document.getElementById('checklistAttachmentDeleteConfirmBtn');

        if (!modal || modal.dataset.bound === '1') {
            return;
        }

        modal.dataset.bound = '1';

        confirmBtn?.addEventListener('click', confirmDelete);

        modal.addEventListener('hidden.bs.modal', function () {
            pendingDeleteContext = null;
        });
    }

    function bindRoot(root) {
        if (!root || root.dataset.attachmentUiBound === '1') {
            return;
        }

        root.dataset.attachmentUiBound = '1';

        root.querySelectorAll('[data-closure-file-field]').forEach(syncFileField);

        root.addEventListener('click', function (event) {
            const uploadBtn = event.target.closest('.checklist-file-upload-btn');

            if (uploadBtn) {
                event.preventDefault();
                const fileField = uploadBtn.closest('[data-closure-file-field]');
                const fileInput = fileField?.querySelector('.checklist-file-input');
                fileInput?.click();
                return;
            }

            const deleteBtn = event.target.closest('.checklist-file-delete-btn');

            if (deleteBtn) {
                event.preventDefault();
                event.stopPropagation();
                openDeleteConfirmModal(deleteBtn, 'saved');
                return;
            }

            const clearBtn = event.target.closest('.checklist-file-clear-btn');

            if (clearBtn) {
                event.preventDefault();
                event.stopPropagation();
                openDeleteConfirmModal(clearBtn, 'pending');
            }
        });

        root.addEventListener('change', function (event) {
            if (!event.target.matches('.checklist-file-input')) {
                return;
            }

            const fileField = event.target.closest('[data-closure-file-field]');

            if (fileField) {
                syncFileField(fileField);
            }
        });
    }

    window.initChecklistAttachmentUi = function (root) {
        bindDeleteModal();

        const scope = root || document;
        scope.querySelectorAll('[data-closure-docs-form], [data-checklist-readiness], form[enctype="multipart/form-data"]').forEach(bindRoot);

        if (scope.matches?.('[data-closure-docs-form], [data-checklist-readiness], form[enctype="multipart/form-data"]')) {
            bindRoot(scope);
        }
    };

    function bootAttachmentUi() {
        window.initChecklistAttachmentUi(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAttachmentUi);
    } else {
        bootAttachmentUi();
    }
})();
