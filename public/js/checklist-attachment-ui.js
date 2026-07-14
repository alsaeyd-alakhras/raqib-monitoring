/**
 * Icon-based checklist attachment UI: upload modal (file/URL), view, delete with modal confirm.
 */
(function () {
    'use strict';

    let pendingDeleteContext = null;
    let pendingUploadContext = null;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getDeleteModal() {
        return document.getElementById('checklistAttachmentDeleteModal');
    }

    function getUploadModal() {
        return document.getElementById('checklistAttachmentUploadModal');
    }

    function getModalInstance(modal) {
        if (!modal || !window.bootstrap?.Modal) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modal);
    }

    function getTypeInput(fileField) {
        return fileField.querySelector('.checklist-attachment-type-input');
    }

    function getUrlInput(fileField) {
        return fileField.querySelector('.checklist-attachment-url-input');
    }

    function getFileInput(fileField) {
        return fileField.querySelector('.checklist-file-input');
    }

    function hasPendingOrSavedAttachment(fileField) {
        const fileInput = getFileInput(fileField);

        if (fileInput?.files?.length) {
            return true;
        }

        if (fileField.dataset.hasAttachment === '1') {
            return true;
        }

        const urlInput = getUrlInput(fileField);

        return Boolean(urlInput?.value?.trim());
    }

    function renderActions(fileField, state) {
        const actions = fileField.querySelector('.checklist-file-actions');

        if (!actions) {
            return;
        }

        if (state === 'saved-file' || state === 'saved-url') {
            const url = fileField.dataset.attachmentUrl || '#';
            const name = fileField.dataset.attachmentName || 'مرفق';
            const icon = state === 'saved-url' ? 'ti-external-link' : 'ti-eye';

            actions.innerHTML = `
                <a href="${escapeHtml(url)}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                   title="عرض المرفق">
                    <i class="ti ${icon}"></i>
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

        if (state === 'pending-file') {
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

        if (state === 'pending-url') {
            const urlLabel = fileField.dataset.pendingUrlLabel || 'رابط خارجي';

            actions.innerHTML = `
                <span class="checklist-file-pending-name text-truncate" title="${escapeHtml(urlLabel)}">${escapeHtml(urlLabel)}</span>
                <button type="button"
                        class="btn btn-sm btn-icon btn-text-danger checklist-file-clear-btn"
                        title="إلغاء الرابط"
                        aria-label="إلغاء الرابط">
                    <i class="ti ti-trash"></i>
                </button>
            `;

            fileField.dataset.hasAttachment = '0';
            return;
        }

        actions.innerHTML = `
            <button type="button"
                    class="btn btn-sm btn-icon btn-text-secondary checklist-file-upload-btn"
                    title="إضافة مرفق"
                    aria-label="إضافة مرفق">
                <i class="ti ti-upload"></i>
            </button>
        `;

        fileField.dataset.hasAttachment = '0';
        delete fileField.dataset.pendingFileName;
        delete fileField.dataset.pendingUrlLabel;
    }

    function syncFileField(fileField) {
        const fileInput = getFileInput(fileField);
        const typeInput = getTypeInput(fileField);
        const urlInput = getUrlInput(fileField);

        if (!fileInput) {
            return;
        }

        if (fileInput.files?.length) {
            fileField.dataset.pendingFileName = fileInput.files[0].name;
            if (typeInput) {
                typeInput.value = 'file';
            }
            if (urlInput) {
                urlInput.value = '';
            }
            renderActions(fileField, 'pending-file');
            return;
        }

        const pendingUrl = urlInput?.value?.trim();
        const pendingType = typeInput?.value || 'file';

        if (pendingUrl && pendingType === 'url' && fileField.dataset.hasAttachment !== '1') {
            fileField.dataset.pendingUrlLabel = pendingUrl.length > 40 ? pendingUrl.slice(0, 37) + '...' : pendingUrl;
            renderActions(fileField, 'pending-url');
            return;
        }

        if (fileField.dataset.hasAttachment === '1' && fileField.dataset.attachmentUrl) {
            const savedState = fileField.dataset.attachmentType === 'url' ? 'saved-url' : 'saved-file';
            renderActions(fileField, savedState);
            return;
        }

        renderActions(fileField, 'empty');
    }

    function openUploadModal(trigger) {
        const modal = getUploadModal();
        const fileField = trigger?.closest('[data-closure-file-field]');
        const itemNameEl = document.getElementById('checklistAttachmentUploadItemName');
        const fileInput = document.getElementById('checklistAttachmentUploadFileInput');
        const urlInput = document.getElementById('checklistAttachmentUploadUrlInput');
        const modalInstance = getModalInstance(modal);

        if (!modal || !fileField || !modalInstance) {
            return;
        }

        const row = fileField.closest('tr');
        const itemName = row?.querySelector('.checklist-col-item')?.textContent?.trim() || 'البند';

        if (itemNameEl) {
            itemNameEl.textContent = itemName;
        }

        if (fileInput) {
            fileInput.value = '';
        }

        if (urlInput) {
            urlInput.value = getUrlInput(fileField)?.value || '';
        }

        pendingUploadContext = { fileField };

        modalInstance.show();
    }

    function confirmUpload() {
        if (!pendingUploadContext) {
            return;
        }

        const { fileField } = pendingUploadContext;
        const modal = getUploadModal();
        const modalInstance = getModalInstance(modal);
        const activeTab = modal?.querySelector('#checklist-upload-tab-url.active, #checklist-upload-pane-url.active');
        const isUrlTab = Boolean(modal?.querySelector('#checklist-upload-tab-url.active'));
        const fileInput = getFileInput(fileField);
        const typeInput = getTypeInput(fileField);
        const urlInput = getUrlInput(fileField);
        const modalFileInput = document.getElementById('checklistAttachmentUploadFileInput');
        const modalUrlInput = document.getElementById('checklistAttachmentUploadUrlInput');

        if (isUrlTab) {
            const url = modalUrlInput?.value?.trim() || '';

            if (!url) {
                if (window.toastr) {
                    window.toastr.warning('يرجى إدخال رابط صالح.');
                }
                return;
            }

            try {
                const parsed = new URL(url);
                if (!['http:', 'https:'].includes(parsed.protocol)) {
                    throw new Error('invalid protocol');
                }
            } catch (error) {
                if (window.toastr) {
                    window.toastr.warning('يرجى إدخال رابط يبدأ بـ http:// أو https://');
                }
                return;
            }

            if (fileInput) {
                fileInput.value = '';
            }
            if (typeInput) {
                typeInput.value = 'url';
            }
            if (urlInput) {
                urlInput.value = url;
            }

            syncFileField(fileField);
            fileField.dispatchEvent(new Event('change', { bubbles: true }));
            pendingUploadContext = null;
            modalInstance?.hide();
            return;
        }

        const selectedFile = modalFileInput?.files?.[0];

        if (!selectedFile) {
            if (window.toastr) {
                window.toastr.warning('يرجى اختيار ملف للرفع.');
            }
            return;
        }

        if (fileInput && modalFileInput?.files?.length) {
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(selectedFile);
            fileInput.files = dataTransfer.files;
        }

        if (typeInput) {
            typeInput.value = 'file';
        }
        if (urlInput) {
            urlInput.value = '';
        }

        syncFileField(fileField);
        fileInput?.dispatchEvent(new Event('change', { bubbles: true }));
        pendingUploadContext = null;
        modalInstance?.hide();
    }

    function openDeleteConfirmModal(trigger, mode) {
        const modal = getDeleteModal();
        const fileField = trigger?.closest('[data-closure-file-field]');
        const nameEl = document.getElementById('checklistAttachmentDeleteFileName');
        const form = document.getElementById('checklistAttachmentDeleteForm');
        const itemInput = document.getElementById('checklistAttachmentDeleteItemId');
        const modalInstance = getModalInstance(modal);

        if (!modal || !fileField || !nameEl || !modalInstance) {
            return;
        }

        const fileName = mode === 'pending'
            ? (fileField.dataset.pendingFileName || fileField.dataset.pendingUrlLabel || 'المرفق المحدد')
            : (fileField.dataset.attachmentName || 'مرفق');

        nameEl.textContent = fileName;
        nameEl.title = fileName;

        pendingDeleteContext = {
            mode,
            fileField,
            fileInput: getFileInput(fileField),
            typeInput: getTypeInput(fileField),
            urlInput: getUrlInput(fileField),
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

        const { mode, fileField, fileInput, typeInput, urlInput } = pendingDeleteContext;
        const form = document.getElementById('checklistAttachmentDeleteForm');
        const modalInstance = getModalInstance(getDeleteModal());

        if (mode === 'pending') {
            if (fileInput) {
                fileInput.value = '';
            }
            if (typeInput) {
                typeInput.value = 'file';
            }
            if (urlInput) {
                urlInput.value = '';
            }
            syncFileField(fileField);
            fileField.dispatchEvent(new Event('change', { bubbles: true }));

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
        const modal = getDeleteModal();
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

    function bindUploadModal() {
        const modal = getUploadModal();
        const confirmBtn = document.getElementById('checklistAttachmentUploadConfirmBtn');

        if (!modal || modal.dataset.bound === '1') {
            return;
        }

        modal.dataset.bound = '1';

        confirmBtn?.addEventListener('click', confirmUpload);

        modal.addEventListener('hidden.bs.modal', function () {
            pendingUploadContext = null;
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
                openUploadModal(uploadBtn);
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
            if (!event.target.matches('.checklist-file-input, .checklist-attachment-url-input, .checklist-attachment-type-input')) {
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
        bindUploadModal();

        const scope = root || document;
        scope.querySelectorAll('[data-closure-docs-form], [data-checklist-readiness], form[enctype="multipart/form-data"]').forEach(bindRoot);

        if (scope.matches?.('[data-closure-docs-form], [data-checklist-readiness], form[enctype="multipart/form-data"]')) {
            bindRoot(scope);
        }
    };

    window.checklistHasAttachment = hasPendingOrSavedAttachment;

    function bootAttachmentUi() {
        window.initChecklistAttachmentUi(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootAttachmentUi);
    } else {
        bootAttachmentUi();
    }
})();
