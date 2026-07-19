(function () {
    'use strict';

    const cfg = window.orgStructureConfig;
    if (!cfg) return;

    let treeData = [];
    let selectedNode = null;
    let centersFlat = [];
    let departmentsFlat = [];

    const $tree = document.getElementById('org-tree');
    const $detail = document.getElementById('org-detail-panel');
    const modalEl = document.getElementById('orgModal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;

    const typeLabels = {
        center: 'مركز',
        department: 'دائرة',
        section: 'قسم',
    };

    function urlFromTemplate(template, type, id) {
        return template.replace('__TYPE__', type).replace('__ID__', String(id));
    }

    async function fetchJson(url, options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        };
        if (options.method && options.method !== 'GET') {
            headers['X-CSRF-TOKEN'] = cfg.csrf;
        }
        const res = await fetch(url, { ...options, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.message || 'حدث خطأ.');
        }
        return data;
    }

    function flattenTree(centers) {
        centersFlat = centers.map(c => ({ id: c.id, name: c.name }));
        departmentsFlat = [];
        centers.forEach(c => {
            (c.children || []).forEach(d => {
                departmentsFlat.push({ id: d.id, name: d.name, center_id: d.center_id });
            });
        });
    }

    function renderTree(centers) {
        treeData = centers;
        flattenTree(centers);

        if (!centers.length) {
            $tree.innerHTML = '<p class="text-muted text-center py-4">لا توجد عناصر. أضف مركزاً للبدء.</p>';
            return;
        }

        $tree.innerHTML = '<ul>' + centers.map(renderCenterNode).join('') + '</ul>';
        bindTreeEvents();
    }

    function renderCenterNode(center) {
        const expanded = selectedNode?.type === 'center' && selectedNode.id === center.id ? 'expanded' : '';
        const active = isActiveNode('center', center.id) ? 'active' : '';
        const depts = center.children || [];
        const hasChildren = depts.length > 0;

        return `<li data-type="center" data-id="${center.id}">
            <div class="org-node-row ${active}" data-select="center" data-id="${center.id}">
                <span class="org-toggle" data-toggle="center" data-id="${center.id}">${hasChildren ? '<i class="fa-solid fa-chevron-down"></i>' : ''}</span>
                <span class="badge bg-label-primary org-type-badge">مركز</span>
                <span class="flex-grow-1">${escapeHtml(center.name)}</span>
                <span class="badge bg-label-secondary">${center.children_count ?? depts.length}</span>
            </div>
            ${hasChildren ? `<ul class="org-children">${depts.map(renderDepartmentNode).join('')}</ul>` : ''}
        </li>`;
    }

    function renderDepartmentNode(dept) {
        const active = isActiveNode('department', dept.id) ? 'active' : '';
        const sections = dept.children || [];
        const hasChildren = sections.length > 0;

        return `<li data-type="department" data-id="${dept.id}">
            <div class="org-node-row ${active}" data-select="department" data-id="${dept.id}">
                <span class="org-toggle">${hasChildren ? '<i class="fa-solid fa-chevron-down"></i>' : ''}</span>
                <span class="badge bg-label-info org-type-badge">دائرة</span>
                <span class="flex-grow-1">${escapeHtml(dept.name)}</span>
                <span class="badge bg-label-secondary">${dept.children_count ?? sections.length}</span>
            </div>
            ${hasChildren ? `<ul class="org-children">${sections.map(renderSectionNode).join('')}</ul>` : ''}
        </li>`;
    }

    function renderSectionNode(section) {
        const active = isActiveNode('section', section.id) ? 'active' : '';

        return `<li data-type="section" data-id="${section.id}">
            <div class="org-node-row ${active}" data-select="section" data-id="${section.id}">
                <span class="org-toggle"></span>
                <span class="badge bg-label-success org-type-badge">قسم</span>
                <span class="flex-grow-1">${escapeHtml(section.name)}</span>
            </div>
        </li>`;
    }

    function isActiveNode(type, id) {
        return selectedNode && selectedNode.type === type && Number(selectedNode.id) === Number(id);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function bindTreeEvents() {
        $tree.querySelectorAll('[data-select]').forEach(el => {
            el.addEventListener('click', e => {
                e.stopPropagation();
                selectNode(el.dataset.select, el.dataset.id);
            });
        });
    }

    async function loadTree() {
        $tree.innerHTML = '<div class="text-center text-muted py-4"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        try {
            const data = await fetchJson(cfg.treeUrl);
            renderTree(data.centers || []);
            if (selectedNode) {
                await loadNodeDetail(selectedNode.type, selectedNode.id);
            }
        } catch (err) {
            $tree.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(err.message)}</div>`;
        }
    }

    async function selectNode(type, id) {
        selectedNode = { type, id: Number(id) };
        $tree.querySelectorAll('.org-node-row').forEach(r => r.classList.remove('active'));
        const row = $tree.querySelector(`[data-select="${type}"][data-id="${id}"]`);
        if (row) row.classList.add('active');
        await loadNodeDetail(type, id);
    }

    async function loadNodeDetail(type, id) {
        $detail.innerHTML = '<div class="text-center py-5"><i class="fa-solid fa-spinner fa-spin"></i></div>';
        try {
            const data = await fetchJson(urlFromTemplate(cfg.nodeUrlTemplate, type, id));
            renderDetail(data);
        } catch (err) {
            $detail.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.message)}</div>`;
        }
    }

    function renderDetail(data) {
        const usage = data.usage || {};
        const usageHtml = Object.entries(usage)
            .filter(([, v]) => v > 0)
            .map(([k, v]) => `<li>${usageLabel(k)}: <strong>${v}</strong></li>`)
            .join('') || '<li class="text-muted">لا يوجد استخدام مباشر</li>';

        const canUpdate = canManageType(data.type);
        const canDelete = canManageType(data.type);
        const canAddChild =
            (data.type === 'center' && cfg.canManageDepartments) ||
            (data.type === 'department' && cfg.canManageSections);

        let parentHtml = '';
        if (data.type === 'department') {
            parentHtml = `<p class="mb-1"><span class="text-muted">المركز:</span> ${escapeHtml(data.center_name || '—')}</p>`;
        } else if (data.type === 'section') {
            parentHtml = `<p class="mb-1"><span class="text-muted">الدائرة:</span> ${escapeHtml(data.department_name || '—')}</p>
                <p class="mb-1"><span class="text-muted">المركز:</span> ${escapeHtml(data.center_name || '—')}</p>`;
        }

        $detail.innerHTML = `
            <div>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="badge bg-label-primary">${typeLabels[data.type]}</span>
                    <h4 class="mb-0">${escapeHtml(data.name)}</h4>
                </div>
                ${parentHtml}
                <p class="mb-1"><span class="text-muted">عدد الأبناء:</span> ${data.children_count ?? 0}</p>
                <hr>
                <h6>الاستخدام في النظام</h6>
                <ul class="mb-4">${usageHtml}</ul>
                <div class="d-flex flex-wrap gap-2">
                    ${canAddChild ? `<button type="button" class="btn btn-primary btn-sm" data-action="add-child"><i class="fa-solid fa-plus"></i> إضافة ${data.type === 'center' ? 'دائرة' : 'قسم'}</button>` : ''}
                    ${canUpdate ? `<button type="button" class="btn btn-label-primary btn-sm" data-action="edit"><i class="fa-solid fa-pen"></i> تعديل</button>` : ''}
                    ${canDelete ? `<button type="button" class="btn btn-label-danger btn-sm" data-action="delete"><i class="fa-solid fa-trash"></i> حذف</button>` : ''}
                </div>
            </div>`;

        $detail.querySelector('[data-action="add-child"]')?.addEventListener('click', () => openAddChildModal(data));
        $detail.querySelector('[data-action="edit"]')?.addEventListener('click', () => openEditModal(data));
        $detail.querySelector('[data-action="delete"]')?.addEventListener('click', () => deleteNode(data));
    }

    function usageLabel(key) {
        return { projects: 'مشاريع', activities: 'نشاطات رقابية', people: 'أشخاص' }[key] || key;
    }

    function canManageType(type) {
        if (type === 'center') return cfg.canManageCenters;
        if (type === 'department') return cfg.canManageDepartments;
        if (type === 'section') return cfg.canManageSections;
        return false;
    }

    function openAddCenterModal() {
        resetForm();
        document.getElementById('org-modal-title').textContent = 'إضافة مركز';
        document.getElementById('org-form-type').value = 'center';
        document.getElementById('org-parent-center-field').style.display = 'none';
        document.getElementById('org-parent-department-field').style.display = 'none';
        modal?.show();
    }

    function openAddChildModal(parent) {
        resetForm();
        if (parent.type === 'center') {
            document.getElementById('org-modal-title').textContent = 'إضافة دائرة';
            document.getElementById('org-form-type').value = 'department';
            document.getElementById('org-form-center-id').value = parent.id;
            populateCenterSelect(parent.center_id || parent.id);
            document.getElementById('org-parent-center-field').style.display = '';
            document.getElementById('org-parent-department-field').style.display = 'none';
        } else if (parent.type === 'department') {
            document.getElementById('org-modal-title').textContent = 'إضافة قسم';
            document.getElementById('org-form-type').value = 'section';
            document.getElementById('org-form-department-id').value = parent.id;
            populateDepartmentSelect(parent.department_id || parent.id, parent.center_id);
            document.getElementById('org-parent-center-field').style.display = 'none';
            document.getElementById('org-parent-department-field').style.display = '';
        }
        modal?.show();
    }

    function openEditModal(data) {
        resetForm();
        document.getElementById('org-modal-title').textContent = `تعديل ${typeLabels[data.type]}`;
        document.getElementById('org-form-type').value = data.type;
        document.getElementById('org-form-id').value = data.id;
        document.getElementById('org-form-name').value = data.name;

        document.getElementById('org-parent-center-field').style.display = 'none';
        document.getElementById('org-parent-department-field').style.display = 'none';

        if (data.type === 'department') {
            populateCenterSelect(data.center_id);
            document.getElementById('org-parent-center-field').style.display = '';
        } else if (data.type === 'section') {
            populateDepartmentSelect(data.department_id, data.center_id);
            document.getElementById('org-parent-department-field').style.display = '';
        }

        modal?.show();
    }

    function populateCenterSelect(selectedId) {
        const sel = document.getElementById('org-form-center-select');
        sel.innerHTML = centersFlat.map(c =>
            `<option value="${c.id}" ${Number(c.id) === Number(selectedId) ? 'selected' : ''}>${escapeHtml(c.name)}</option>`
        ).join('');
    }

    function populateDepartmentSelect(selectedId, centerId) {
        const sel = document.getElementById('org-form-department-select');
        const filtered = centerId
            ? departmentsFlat.filter(d => Number(d.center_id) === Number(centerId))
            : departmentsFlat;
        sel.innerHTML = filtered.map(d =>
            `<option value="${d.id}" ${Number(d.id) === Number(selectedId) ? 'selected' : ''}>${escapeHtml(d.name)}</option>`
        ).join('');
    }

    function resetForm() {
        document.getElementById('org-form').reset();
        document.getElementById('org-form-id').value = '';
        document.getElementById('org-form-error').classList.add('d-none');
    }

    async function deleteNode(data) {
        if (!confirm(`هل أنت متأكد من حذف «${data.name}»؟`)) return;
        try {
            await fetchJson(urlFromTemplate(cfg.destroyUrlTemplate, data.type, data.id), { method: 'DELETE' });
            selectedNode = null;
            $detail.innerHTML = `<div class="org-detail-empty"><p class="mb-0 text-success">تم الحذف بنجاح</p></div>`;
            await loadTree();
        } catch (err) {
            alert(err.message);
        }
    }

    document.getElementById('org-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const type = document.getElementById('org-form-type').value;
        const id = document.getElementById('org-form-id').value;
        const name = document.getElementById('org-form-name').value.trim();
        const errEl = document.getElementById('org-form-error');

        const body = { type, name };
        if (type === 'department') {
            body.center_id = document.getElementById('org-form-center-select').value
                || document.getElementById('org-form-center-id').value;
        }
        if (type === 'section') {
            body.department_id = document.getElementById('org-form-department-select').value
                || document.getElementById('org-form-department-id').value;
        }

        try {
            if (id) {
                await fetchJson(urlFromTemplate(cfg.updateUrlTemplate, type, id), {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
            } else {
                await fetchJson(cfg.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body),
                });
            }
            modal?.hide();
            await loadTree();
            if (id) {
                await selectNode(type, id);
            }
        } catch (err) {
            errEl.textContent = err.message;
            errEl.classList.remove('d-none');
        }
    });

    document.getElementById('btn-add-center')?.addEventListener('click', openAddCenterModal);
    document.getElementById('btn-refresh-tree')?.addEventListener('click', loadTree);

    loadTree();
})();
