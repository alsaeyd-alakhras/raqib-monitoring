/**
 * Activity attachment UI: upload modal (file/URL), multi-file, delete with modal confirm.
 */
(function () {
    'use strict';

    let pendingDeleteContext = null;
    let pendingUploadField = null;
    let pendingUrlIndex = 0;

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getUploadModal() {
        return document.getElementById('activityAttachmentUploadModal');
    }

    function getDeleteModal() {
        return document.getElementById('activityAttachmentDeleteModal');
    }

    function getModalInstance(modal) {
        if (!modal || !window.bootstrap?.Modal) {
            return null;
        }

        return window.bootstrap.Modal.getOrCreateInstance(modal);
    }

    function getFileInput(field) {
        return field.querySelector('.activity-file-input');
    }

    function getAttachmentListEl(field) {
        return field.querySelector('.activity-attachment-list');
    }

    function getPendingUrlsContainer(field) {
        return field.querySelector('#activity-pending-urls-container');
    }

    function readSavedAttachments(field) {
        const raw = field.getAttribute('data-saved-attachments');

        if (!raw) {
            return [];
        }

        try {
            const parsed = JSON.parse(raw);

            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function readPendingUrls(field) {
        const container = getPendingUrlsContainer(field);

        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('input[name="activity_attachment_urls[]"]'))
            .map((input) => input.value.trim())
            .filter(Boolean);
    }

    function appendFilesToInput(fileInput, newFile) {
        const dataTransfer = new DataTransfer();
        Array.from(fileInput.files || []).forEach((file) => dataTransfer.items.add(file));
        dataTransfer.items.add(newFile);
        fileInput.files = dataTransfer.files;
    }

    function removePendingFileAtIndex(fileInput, index) {
        const dataTransfer = new DataTransfer();
        Array.from(fileInput.files || []).forEach((file, i) => {
            if (i !== index) {
                dataTransfer.items.add(file);
            }
        });
        fileInput.files = dataTransfer.files;
    }

    function renderAttachmentList(field) {
        const listEl = getAttachmentListEl(field);
        const fileInput = getFileInput(field);

        if (!listEl) {
            return;
        }

        const saved = readSavedAttachments(field);
        const pendingFiles = fileInput?.files ? Array.from(fileInput.files) : [];
        const pendingUrls = readPendingUrls(field);
        let html = '';

        saved.forEach((item) => {
            const icon = item.type === 'url' ? 'ti-external-link' : 'ti-eye';
            const label = item.label || 'مرفق';

            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1" data-saved-id="${escapeHtml(item.id)}">
                    <a href="${escapeHtml(item.url || '#')}" target="_blank" rel="noopener"
                       class="btn btn-sm btn-icon btn-text-primary checklist-file-view-btn" title="عرض">
                        <i class="ti ${icon}"></i>
                    </a>
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(label)}">${escapeHtml(label)}</span>
                    <button type="button" class="btn btn-sm btn-icon btn-text-danger activity-file-delete-btn checklist-file-delete-btn"
                            data-attachment-id="${escapeHtml(item.id)}" title="حذف" aria-label="حذف">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        });

        pendingFiles.forEach((file, index) => {
            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1" data-pending-index="${index}">
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                    <button type="button" class="btn btn-sm btn-icon btn-text-danger activity-file-clear-btn checklist-file-clear-btn"
                            data-pending-index="${index}" title="إلغاء" aria-label="إلغاء">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        });

        pendingUrls.forEach((url, index) => {
            let label = url;
            try {
                label = new URL(url).host || url;
            } catch (error) {
                // keep full url
            }

            html += `
                <span class="checklist-file-chip d-inline-flex align-items-center gap-1 border rounded px-1" data-pending-url-index="${index}">
                    <span class="checklist-file-pending-name text-truncate small" style="max-width:7rem" title="${escapeHtml(url)}">رابط — ${escapeHtml(label)}</span>
                    <button type="button" class="btn btn-sm btn-icon btn-text-danger activity-url-clear-btn"
                            data-pending-url-index="${index}" title="إلغاء" aria-label="إلغاء">
                        <i class="ti ti-trash"></i>
                    </button>
                </span>
            `;
        });

        listEl.innerHTML = html;
    }

    function addPendingUrl(field, url) {
        const container = getPendingUrlsContainer(field);

        if (!container) {
            return;
        }

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'activity_attachment_urls[]';
        input.value = url;
        input.dataset.pendingUrlIndex = String(pendingUrlIndex++);
        container.appendChild(input);
    }

    function removePendingUrl(field, index) {
        const container = getPendingUrlsContainer(field);

        if (!container) {
            return;
        }

        const inputs = container.querySelectorAll('input[name="activity_attachment_urls[]"]');
        inputs.forEach((input, i) => {
            if (i === index) {
                input.remove();
            }
        });
    }

    function openUploadModal(field) {
        pendingUploadField = field;
        const modal = getUploadModal();
        const modalFileInput = document.getElementById('activityAttachmentUploadFileInput');
        const modalUrlInput = document.getElementById('activityAttachmentUploadUrlInput');

        if (modalFileInput) {
            modalFileInput.value = '';
        }
        if (modalUrlInput) {
            modalUrlInput.value = '';
        }

        getModalInstance(modal)?.show();
    }

    function confirmUpload() {
        if (!pendingUploadField) {
            return;
        }

        const field = pendingUploadField;
        const modal = getUploadModal();
        const isUrlTab = Boolean(modal?.querySelector('#activity-upload-tab-url.active'));
        const fileInput = getFileInput(field);
        const modalFileInput = document.getElementById('activityAttachmentUploadFileInput');
        const modalUrlInput = document.getElementById('activityAttachmentUploadUrlInput');

        if (isUrlTab) {
            const url = modalUrlInput?.value?.trim() || '';

            if (!url) {
                window.toastr?.warning('يرجى إدخال رابط صالح.');

                return;
            }

            try {
                const parsed = new URL(url);
                if (!['http:', 'https:'].includes(parsed.protocol)) {
                    throw new Error('invalid protocol');
                }
            } catch (error) {
                window.toastr?.warning('يرجى إدخال رابط يبدأ بـ http:// أو https://');

                return;
            }

            addPendingUrl(field, url);
            renderAttachmentList(field);
            pendingUploadField = null;
            getModalInstance(modal)?.hide();

            return;
        }

        const selectedFiles = modalFileInput?.files;

        if (!selectedFiles?.length) {
            window.toastr?.warning('يرجى اختيار ملف للرفع.');

            return;
        }

        if (fileInput) {
            for (let i = 0; i < selectedFiles.length; i += 1) {
                appendFilesToInput(fileInput, selectedFiles[i]);
            }
        }

        renderAttachmentList(field);
        pendingUploadField = null;
        getModalInstance(modal)?.hide();
    }

    function openDeleteConfirmModal(trigger, mode) {
        const field = trigger?.closest('[data-activity-attachment-field]');
        const modal = getDeleteModal();
        const nameEl = document.getElementById('activityAttachmentDeleteFileName');
        const form = document.getElementById('activityAttachmentDeleteForm');
        const attachmentInput = document.getElementById('activityAttachmentDeleteAttachmentId');

        if (!modal || !field || !nameEl) {
            return;
        }

        let fileName = 'مرفق';
        const attachmentId = trigger.dataset.attachmentId || '';

        if (mode === 'pending-file') {
            const fileInput = getFileInput(field);
            fileName = fileInput?.files?.[Number(trigger.dataset.pendingIndex)]?.name || 'ملف';
        } else if (mode === 'pending-url') {
            const urls = readPendingUrls(field);
            fileName = urls[Number(trigger.dataset.pendingUrlIndex)] || 'رابط';
        } else {
            fileName = trigger.closest('.checklist-file-chip')?.querySelector('.checklist-file-pending-name')?.textContent?.trim() || 'مرفق';
        }

        nameEl.textContent = fileName;
        nameEl.title = fileName;

        pendingDeleteContext = {
            mode,
            field,
            attachmentId,
            pendingIndex: trigger.dataset.pendingIndex,
            pendingUrlIndex: trigger.dataset.pendingUrlIndex,
        };

        if (mode === 'saved' && form && attachmentInput) {
            form.action = field.dataset.deleteUrl || '#';
            attachmentInput.value = attachmentId;
        }

        getModalInstance(modal)?.show();
    }

    function confirmDelete() {
        if (!pendingDeleteContext) {
            return;
        }

        const { mode, field, pendingIndex, pendingUrlIndex } = pendingDeleteContext;
        const form = document.getElementById('activityAttachmentDeleteForm');
        const modalInstance = getModalInstance(getDeleteModal());

        if (mode === 'pending-file') {
            const fileInput = getFileInput(field);
            if (fileInput && pendingIndex !== undefined) {
                removePendingFileAtIndex(fileInput, Number(pendingIndex));
            }
            renderAttachmentList(field);
        } else if (mode === 'pending-url') {
            if (pendingUrlIndex !== undefined) {
                removePendingUrl(field, Number(pendingUrlIndex));
            }
            renderAttachmentList(field);
        } else if (mode === 'saved' && form && field.dataset.deleteUrl) {
            form.submit();

            return;
        }

        pendingDeleteContext = null;
        modalInstance?.hide();
    }

    function bindEvents() {
        document.addEventListener('click', (event) => {
            const uploadBtn = event.target.closest('.activity-file-upload-btn');
            if (uploadBtn) {
                const field = uploadBtn.closest('[data-activity-attachment-field]');
                if (field) {
                    event.preventDefault();
                    openUploadModal(field);
                }

                return;
            }

            const deleteBtn = event.target.closest('.activity-file-delete-btn');
            if (deleteBtn) {
                event.preventDefault();
                openDeleteConfirmModal(deleteBtn, 'saved');

                return;
            }

            const clearFileBtn = event.target.closest('.activity-file-clear-btn');
            if (clearFileBtn) {
                event.preventDefault();
                openDeleteConfirmModal(clearFileBtn, 'pending-file');

                return;
            }

            const clearUrlBtn = event.target.closest('.activity-url-clear-btn');
            if (clearUrlBtn) {
                event.preventDefault();
                openDeleteConfirmModal(clearUrlBtn, 'pending-url');
            }
        });

        document.getElementById('activityAttachmentUploadConfirmBtn')?.addEventListener('click', confirmUpload);
        document.getElementById('activityAttachmentDeleteConfirmBtn')?.addEventListener('click', confirmDelete);
    }

    window.initActivityAttachmentUi = function initActivityAttachmentUi() {
        document.querySelectorAll('[data-activity-attachment-field]').forEach((field) => {
            renderAttachmentList(field);
        });
        bindEvents();
    };

    document.addEventListener('DOMContentLoaded', () => {
        if (document.querySelector('[data-activity-attachment-field]')) {
            window.initActivityAttachmentUi();
        }
    });
})();
