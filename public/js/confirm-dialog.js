/**
 * Unified confirmation dialog using Bootstrap 5 Modal.
 * - Forms with data-confirm attribute are intercepted on submit.
 * - window.confirmAction({ title, message, variant, onConfirm }) for programmatic use.
 */
(function () {
    'use strict';

    const VARIANTS = {
        danger: { btnClass: 'btn-danger', iconClass: 'text-danger', icon: 'fa-trash-alt' },
        primary: { btnClass: 'btn-primary', iconClass: 'text-primary', icon: 'fa-check' },
        warning: { btnClass: 'btn-warning', iconClass: 'text-warning', icon: 'fa-exclamation-triangle' },
    };

    let pendingForm = null;
    let pendingCallback = null;

    function getModal() {
        return document.getElementById('appConfirmModal');
    }

    function getModalInstance() {
        const el = getModal();
        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function applyVariant(variant) {
        const config = VARIANTS[variant] || VARIANTS.primary;
        const confirmBtn = document.getElementById('appConfirmModalConfirmBtn');
        const iconWrap = document.querySelector('#appConfirmModal .app-confirm-icon');

        if (confirmBtn) {
            confirmBtn.className = 'btn ' + config.btnClass;
        }
        if (iconWrap) {
            iconWrap.className = 'app-confirm-icon ' + config.iconClass;
            iconWrap.innerHTML = '<i class="fas ' + config.icon + '"></i>';
        }
    }

    function showModal(options) {
        const titleEl = document.getElementById('appConfirmModalTitle');
        const messageEl = document.getElementById('appConfirmModalMessage');

        if (titleEl) {
            titleEl.textContent = options.title || 'تأكيد الإجراء';
        }
        if (messageEl) {
            messageEl.textContent = options.message || 'هل أنت متأكد من تنفيذ هذا الإجراء؟';
        }

        applyVariant(options.variant || 'primary');

        const instance = getModalInstance();
        if (instance) {
            instance.show();
        }
    }

    window.confirmAction = function (options) {
        pendingForm = null;
        pendingCallback = typeof options.onConfirm === 'function' ? options.onConfirm : null;
        showModal(options);
    };

    function initFormInterceptors() {
        document.addEventListener('submit', function (e) {
            const form = e.target.closest('form[data-confirm]');
            if (!form || form.dataset.confirmed === '1') {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            pendingForm = form;
            pendingCallback = null;

            showModal({
                title: form.dataset.confirmTitle || 'تأكيد الإجراء',
                message: form.dataset.confirm || 'هل أنت متأكد من تنفيذ هذا الإجراء؟',
                variant: form.dataset.confirmVariant || 'primary',
            });
        }, true);
    }

    function initConfirmButton() {
        const confirmBtn = document.getElementById('appConfirmModalConfirmBtn');
        if (!confirmBtn) {
            return;
        }

        confirmBtn.addEventListener('click', function () {
            const instance = getModalInstance();

            if (pendingCallback) {
                pendingCallback();
                pendingCallback = null;
            } else if (pendingForm) {
                pendingForm.dataset.confirmed = '1';
                if (typeof pendingForm.requestSubmit === 'function') {
                    pendingForm.requestSubmit();
                } else {
                    pendingForm.submit();
                }
                pendingForm.dataset.confirmed = '';
                pendingForm = null;
            }

            if (instance) {
                instance.hide();
            }
        });
    }

    function initModalReset() {
        const modal = getModal();
        if (!modal) {
            return;
        }

        modal.addEventListener('hidden.bs.modal', function () {
            pendingForm = null;
            pendingCallback = null;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initFormInterceptors();
            initConfirmButton();
            initModalReset();
        });
    } else {
        initFormInterceptors();
        initConfirmButton();
        initModalReset();
    }
})();
