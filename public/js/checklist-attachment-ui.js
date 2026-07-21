/**
 * Icon-based checklist attachment UI: upload modal (file/URL), multi-file, view, delete with modal confirm.
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

    function getAttachmentListEl(fileField) {
        return fileField.querySelector('.checklist-file-attachment-list');
    }

    function readSavedAttachments(fileField) {
        const raw = fileField.getAttribute('data-saved-attachments');

        if (raw) {
            try {
                const parsed = JSON.parse(raw);

                if (Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (error) {
                // fall through to DOM scrape
            }
        }

        return scrapeSavedAttachmentsFromDom(fileField);
    }

    function scrapeSavedAttachmentsFromDom(fileField) {
        const chips = fileField.querySelectorAll('.checklist-file-chip[data-saved-id]');

        return Array.from(chips).map((chip) => {
            const link = chip.querySelector('.checklist-file-view-btn');
            const labelEl = chip.querySelector('.checklist-file-pending-name');

            return {
                id: chip.dataset.savedId || chip.querySelector('[data-attachment-id]')?.dataset.attachmentId || '',
                type: (link?.href || '').startsWith('http') && !link?.href.includes('/storage/') ? 'url' : 'file',
                url: link?.getAttribute('href') || '',
                label: labelEl?.textContent?.trim() || 'مرفق',
            };
        }).filter((item) => item.id && item.url);
    }

    function hasPendingOrSavedAttachment(fileField) {
        const fileInput = getFileInput(fileField);

        if (fileInput?.files?.length) {
            return true;
        }

        if (readSavedAttachments(fileField).length) {
            return true;
        }

        const urlInput = getUrlInput(fileField);
        const typeInput = getTypeInput(fileField);

        return typeInput?.value === 'url' && Boolean(urlInput?.value?.trim());
    }

    function renderAttachmentList(fileField) {
        const listEl = getAttachmentListEl(fileField);
        const uploadBtn = fileField.querySelector('.checklist-file-upload-btn');

        if (!listEl) {
            return;
        }

        const saved = readSavedAttachments(fileField);
        const fileInput = getFileInput(fileField);
        const pendingFiles = fileInput?.files ? Array.from(fileInput.files) : [];
        const typeInput = getTypeInput(fileField);
        const urlInput = getUrlInput(fileField);
        const pendingUrl = typeInput?.value === 'url' ? urlInput?.value?.trim() : '';

        let html = '';

        saved.forEach((item) => {
            const icon = item.type === 'url' ? 'ti-external-link' : 'ti-eye';
            const label = item.label || 'مرفق';

            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1"
                      data-saved-id="${escapeHtml(item.id)}">
                    <a href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener"
                       class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn"
                       title="عرض">
                        <i class="ti ${icon}"></i>
                    </a>
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(label)}">${escapeHtml(label)}</span>
                    <button type="button"
                            class="btn btn-sm btn-icon btn-text-danger checklist-file-delete-btn"
                            data-attachment-id="${escapeHtml(item.id)}"
                            title="حذف"
                            aria-label="حذف">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        });

        pendingFiles.forEach((file, index) => {
            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1"
                      data-pending-index="${index}">
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                    <button type="button"
                            class="btn btn-sm btn-icon btn-text-danger checklist-file-clear-btn"
                            data-pending-index="${index}"
                            title="إلغاء"
                            aria-label="إلغاء">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        });

        if (pendingUrl && !saved.length) {
            const urlLabel = pendingUrl.length > 40 ? pendingUrl.slice(0, 37) + '...' : pendingUrl;

            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1">
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(pendingUrl)}">${escapeHtml(urlLabel)}</span>
                    <button type="button"
                            class="btn btn-sm btn-icon btn-text-danger checklist-file-clear-btn"
                            data-pending-url="1"
                            title="إلغاء الرابط"
                            aria-label="إلغاء الرابط">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        }

        listEl.innerHTML = html;

        const hasAny = saved.length > 0 || pendingFiles.length > 0 || Boolean(pendingUrl);
        fileField.dataset.hasAttachment = hasAny ? '1' : '0';

        if (uploadBtn) {
            uploadBtn.classList.toggle('d-none', typeInput?.value === 'url' && Boolean(pendingUrl));
        }
    }

    function syncFileField(fileField) {
        renderAttachmentList(fileField);
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

    function appendFilesToInput(fileInput, newFile) {
        const dataTransfer = new DataTransfer();
        const existing = fileInput.files ? Array.from(fileInput.files) : [];

        existing.forEach((file) => dataTransfer.items.add(file));
        dataTransfer.items.add(newFile);
        fileInput.files = dataTransfer.files;
    }

    function removePendingFileAtIndex(fileInput, index) {
        const dataTransfer = new DataTransfer();
        const existing = fileInput.files ? Array.from(fileInput.files) : [];

        existing.forEach((file, i) => {
            if (i !== index) {
                dataTransfer.items.add(file);
            }
        });

        fileInput.files = dataTransfer.files;
    }

    function confirmUpload() {
        if (!pendingUploadContext) {
            return;
        }

        const { fileField } = pendingUploadContext;
        const modal = getUploadModal();
        const modalInstance = getModalInstance(modal);
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

        const selectedFiles = modalFileInput?.files;

        if (!selectedFiles?.length) {
            if (window.toastr) {
                window.toastr.warning('يرجى اختيار ملف للرفع.');
            }
            return;
        }

        if (fileInput) {
            for (let i = 0; i < selectedFiles.length; i += 1) {
                appendFilesToInput(fileInput, selectedFiles[i]);
            }
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
        const attachmentInput = document.getElementById('checklistAttachmentDeleteAttachmentId');
        const modalInstance = getModalInstance(modal);

        if (!modal || !fileField || !nameEl || !modalInstance) {
            return;
        }

        let fileName = 'مرفق';
        let attachmentId = trigger.dataset.attachmentId || '';

        if (mode === 'pending') {
            const pendingIndex = trigger.dataset.pendingIndex;
            if (pendingIndex !== undefined) {
                const fileInput = getFileInput(fileField);
                fileName = fileInput?.files?.[pendingIndex]?.name || 'ملف محدد';
            } else {
                fileName = getUrlInput(fileField)?.value?.trim() || 'الرابط';
            }
        } else {
            fileName = trigger.closest('.checklist-file-chip')?.querySelector('.checklist-file-pending-name')?.textContent?.trim() || 'مرفق';
        }

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
            attachmentId,
            pendingIndex: trigger.dataset.pendingIndex,
            pendingUrl: trigger.dataset.pendingUrl === '1',
        };

        if (mode === 'saved' && form && itemInput) {
            form.action = pendingDeleteContext.deleteUrl || '#';
            itemInput.value = pendingDeleteContext.itemId;
            if (attachmentInput) {
                attachmentInput.value = attachmentId;
            }
        }

        modalInstance.show();
    }

    function confirmDelete() {
        if (!pendingDeleteContext) {
            return;
        }

        const { mode, fileField, fileInput, typeInput, urlInput, pendingIndex, pendingUrl } = pendingDeleteContext;
        const form = document.getElementById('checklistAttachmentDeleteForm');
        const modalInstance = getModalInstance(getDeleteModal());

        if (mode === 'pending') {
            if (pendingUrl) {
                if (typeInput) {
                    typeInput.value = 'file';
                }
                if (urlInput) {
                    urlInput.value = '';
                }
            } else if (fileInput && pendingIndex !== undefined) {
                removePendingFileAtIndex(fileInput, Number(pendingIndex));
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
