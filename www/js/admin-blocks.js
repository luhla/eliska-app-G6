/**
 * admin-blocks.js
 * Library management: unified tree (left) + content panel (right).
 * Features: AJAX group loading, Sortable.js ordering, HTML5 drag-to-tree move,
 *           context menu with move/copy/delete.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // State
    // ---------------------------------------------------------------
    let currentGroupId = null;
    let pendingAction = null;    // {action:'move'|'copy', type, id}
    let sortableInstance = null;
    let dragData = null;         // {type, id} during HTML5 drag to tree

    // ---------------------------------------------------------------
    // DOM refs
    // ---------------------------------------------------------------
    const treeList   = document.getElementById('tree-list');
    const rootNode   = document.querySelector('.tree-root-node');
    const itemList   = document.getElementById('item-list');
    const emptyState = document.getElementById('empty-state');
    const breadcrumb = document.getElementById('breadcrumb');
    const ctxMenu    = document.getElementById('ctx-menu');
    const ctxEdit    = document.getElementById('ctx-edit');
    const ctxMove    = document.getElementById('ctx-move');
    const ctxCopy    = document.getElementById('ctx-copy');
    const ctxDelete  = document.getElementById('ctx-delete');
    const pendBanner = document.getElementById('pending-banner');
    const pendText   = document.getElementById('pending-text');
    const btnCancel  = document.getElementById('btn-cancel-pending');

    if (!treeList || !itemList) return;

    // ---------------------------------------------------------------
    // Init
    // ---------------------------------------------------------------
    function init() {
        var treeEl  = document.getElementById('tree-data');
        var itemsEl = document.getElementById('items-data');

        var treeData = treeEl ? JSON.parse(treeEl.textContent) : [];
        renderTree(treeData, treeList, 1);
        setupRootNode();

        var initialItems = itemsEl ? JSON.parse(itemsEl.textContent) : [];
        renderItems(initialItems);
    }

    // ---------------------------------------------------------------
    // Tree rendering
    // ---------------------------------------------------------------
    function renderTree(nodes, container, depth) {
        container.innerHTML = '';
        nodes.forEach(function (node) {
            var li = document.createElement('li');

            var nodeEl = document.createElement('div');
            nodeEl.className = 'tree-node';
            nodeEl.dataset.groupId = node.id;
            nodeEl.style.paddingLeft = (depth * 14 + 10) + 'px';

            var toggle = document.createElement('span');
            toggle.className = 'tree-toggle' + (node.children && node.children.length ? '' : ' leaf');
            nodeEl.appendChild(toggle);

            var colorDot = document.createElement('span');
            colorDot.style.cssText = 'width:10px;height:10px;border-radius:50%;background:' + node.bg_color + ';flex-shrink:0;display:inline-block;margin-right:4px;border:1px solid rgba(0,0,0,0.15);';
            nodeEl.appendChild(colorDot);

            var label = document.createElement('span');
            label.style.cssText = 'flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
            label.textContent = node.name;
            nodeEl.appendChild(label);

            li.appendChild(nodeEl);

            if (node.children && node.children.length) {
                var childUl = document.createElement('ul');
                childUl.className = 'tree-children ps-0';
                childUl.style.display = 'none';
                renderTree(node.children, childUl, depth + 1);
                li.appendChild(childUl);

                toggle.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var open = toggle.classList.toggle('open');
                    childUl.style.display = open ? '' : 'none';
                });
            }

            nodeEl.addEventListener('click', function () {
                if (pendingAction) { completePendingAction(node.id); return; }
                loadGroup(node.id);
            });

            setupTreeDropTarget(nodeEl, node.id);
            container.appendChild(li);
        });
    }

    function setupRootNode() {
        if (!rootNode) return;
        rootNode.addEventListener('click', function () {
            if (pendingAction) { completePendingAction(null); return; }
            loadGroup(null);
        });
        setupTreeDropTarget(rootNode, null);
    }

    function setActiveTreeNode(groupId) {
        document.querySelectorAll('.tree-node, .tree-root-node').forEach(function (el) {
            el.classList.remove('active');
        });
        if (groupId === null) {
            if (rootNode) rootNode.classList.add('active');
        } else {
            var el = document.querySelector('.tree-node[data-group-id="' + groupId + '"]');
            if (el) {
                el.classList.add('active');
                // auto-expand ancestors
                var p = el.parentElement;
                while (p) {
                    if (p.classList && p.classList.contains('tree-children')) {
                        p.style.display = '';
                        var prevSib = p.previousElementSibling;
                        if (prevSib) {
                            var tog = prevSib.querySelector('.tree-toggle');
                            if (tog) tog.classList.add('open');
                        }
                    }
                    p = p.parentElement;
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // Tree drop targets
    // ---------------------------------------------------------------
    function setupTreeDropTarget(nodeEl, groupId) {
        nodeEl.addEventListener('dragover', function (e) {
            if (!dragData) return;
            e.preventDefault();
            nodeEl.classList.add('drop-target');
        });
        nodeEl.addEventListener('dragleave', function () {
            nodeEl.classList.remove('drop-target');
        });
        nodeEl.addEventListener('drop', function (e) {
            e.preventDefault();
            nodeEl.classList.remove('drop-target');
            if (!dragData) return;
            if (dragData.type === 'group' && groupId !== null && dragData.id === groupId) return;
            moveItem(dragData.type, dragData.id, groupId);
            dragData = null;
        });
    }

    // ---------------------------------------------------------------
    // Load group (AJAX)
    // ---------------------------------------------------------------
    function loadGroup(groupId) {
        currentGroupId = groupId;
        setActiveTreeNode(groupId);

        var url = '?do=loadGroup' + (groupId !== null ? '&groupId=' + groupId : '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { showToast(data.error, 'danger'); return; }
                renderBreadcrumb(data.breadcrumb);
                renderItems(data.items);
            })
            .catch(function () { showToast('Chyba při načítání skupiny.', 'danger'); });
    }

    // ---------------------------------------------------------------
    // Breadcrumb
    // ---------------------------------------------------------------
    function renderBreadcrumb(crumbs) {
        if (!breadcrumb || !crumbs) return;
        breadcrumb.innerHTML = '';
        crumbs.forEach(function (c, i) {
            if (i > 0) {
                var sep = document.createElement('i');
                sep.className = 'bi bi-chevron-right mx-1';
                sep.style.cssText = 'font-size:0.7rem;color:#aaa;';
                breadcrumb.appendChild(sep);
            }
            var span = document.createElement('span');
            if (i === 0) {
                span.innerHTML = '<i class="bi bi-house-fill text-primary me-1"></i>';
            } else {
                span.innerHTML = '<i class="bi bi-folder-fill me-1 text-secondary"></i>';
            }
            span.appendChild(document.createTextNode(c.name));
            if (i < crumbs.length - 1) {
                span.style.cursor = 'pointer';
                span.style.color = '#0d6efd';
                (function (gid) {
                    span.addEventListener('click', function () { loadGroup(gid); });
                })(c.id);
            } else {
                span.className = 'fw-semibold';
            }
            breadcrumb.appendChild(span);
        });
    }

    // ---------------------------------------------------------------
    // Render items (right panel)
    // ---------------------------------------------------------------
    function renderItems(items) {
        itemList.innerHTML = '';
        if (!items || items.length === 0) {
            if (emptyState) emptyState.classList.remove('d-none');
            return;
        }
        if (emptyState) emptyState.classList.add('d-none');
        items.forEach(function (item) { itemList.appendChild(createItemEl(item)); });
        initSortable();
    }

    function createItemEl(item) {
        var div = document.createElement('div');
        div.className = 'lib-item';
        div.dataset.type = item.type;
        div.dataset.id = item.id;
        div.draggable = true;

        // Drag handle (Sortable.js)
        var handle = document.createElement('span');
        handle.className = 'drag-handle';
        handle.innerHTML = '<i class="bi bi-grip-vertical"></i>';
        div.appendChild(handle);

        // Icon
        var icon = document.createElement('div');
        if (item.type === 'group') {
            icon.className = 'lib-icon-group';
            icon.style.background = item.bg_color || '#4A90D9';
            icon.innerHTML = '<i class="bi bi-folder-fill" style="font-size:1.1rem;"></i>';
        } else {
            icon.className = 'lib-icon';
            if (item.image_path) {
                var img = document.createElement('img');
                img.src = '/' + item.image_path;
                img.alt = '';
                img.loading = 'lazy';
                icon.appendChild(img);
            } else {
                icon.style.background = '#f0f0f0';
                icon.style.borderRadius = '6px';
                icon.innerHTML = '<i class="bi bi-image text-muted"></i>';
            }
        }
        div.appendChild(icon);

        // Label
        var labelWrap = document.createElement('div');
        labelWrap.className = 'lib-label';
        var name = item.type === 'group' ? item.name : item.text;
        var nameDiv = document.createElement('div');
        nameDiv.className = 'fw-semibold';
        nameDiv.textContent = name;
        labelWrap.appendChild(nameDiv);
        if (item.type === 'block') {
            var sub = document.createElement('div');
            sub.className = 'lib-label-sub';
            sub.innerHTML = '<span class="badge bg-secondary me-1">' + escHtml(item.block_type) + '</span>'
                + (item.audio_path ? '<i class="bi bi-volume-up-fill text-success" title="Má zvuk"></i>' : '');
            labelWrap.appendChild(sub);
        } else if (item.has_children) {
            var sub2 = document.createElement('div');
            sub2.className = 'lib-label-sub text-muted';
            sub2.textContent = 'obsahuje položky';
            labelWrap.appendChild(sub2);
        }
        div.appendChild(labelWrap);

        // Edit button
        var editBtn = document.createElement('a');
        editBtn.className = 'btn btn-sm btn-outline-secondary lib-btn-edit';
        editBtn.title = 'Upravit';
        editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
        editBtn.href = item.type === 'group'
            ? '/admin/groups/edit/' + item.id
            : '/admin/blocks/edit/' + item.id;
        div.appendChild(editBtn);

        // Context menu button
        var ctxBtn = document.createElement('button');
        ctxBtn.type = 'button';
        ctxBtn.className = 'btn btn-sm btn-outline-secondary lib-btn-ctx';
        ctxBtn.title = 'Více akcí';
        ctxBtn.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
        (function (it) {
            ctxBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                openContextMenu(e.clientX, e.clientY, it);
            });
        })(item);
        div.appendChild(ctxBtn);

        // Double-click group = enter group
        if (item.type === 'group') {
            div.addEventListener('dblclick', function (e) {
                if (e.target.closest('.lib-btn-edit, .lib-btn-ctx, .drag-handle')) return;
                loadGroup(item.id);
            });
        }

        // Right-click context menu
        (function (it) {
            div.addEventListener('contextmenu', function (e) {
                e.preventDefault();
                openContextMenu(e.clientX, e.clientY, it);
            });
        })(item);

        // HTML5 drag (to tree nodes) — activated by dragging item body, not handle
        div.addEventListener('dragstart', function (e) {
            if (e.target.closest('.drag-handle')) { e.preventDefault(); return; }
            dragData = { type: item.type, id: item.id };
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', item.type + ':' + item.id);
        });
        div.addEventListener('dragend', function () {
            dragData = null;
            document.querySelectorAll('.drop-target').forEach(function (el) {
                el.classList.remove('drop-target');
            });
        });

        return div;
    }

    // ---------------------------------------------------------------
    // Sortable.js (within panel)
    // ---------------------------------------------------------------
    function initSortable() {
        if (sortableInstance) { sortableInstance.destroy(); sortableInstance = null; }
        if (typeof Sortable === 'undefined' || !itemList.children.length) return;
        sortableInstance = Sortable.create(itemList, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            forceFallback: true,
            onEnd: saveOrder,
        });
    }

    function saveOrder() {
        var items = [];
        itemList.querySelectorAll('.lib-item[data-id]').forEach(function (el, index) {
            items.push({ type: el.dataset.type, id: parseInt(el.dataset.id, 10), sort_order: index });
        });
        fetch('?do=saveOrder', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ items: items }),
        })
            .then(function (r) { return r.json(); })
            .then(function (d) { showToast(d.ok ? 'Pořadí uloženo' : 'Chyba při ukládání pořadí', d.ok ? 'success' : 'danger'); })
            .catch(function () { showToast('Chyba při ukládání pořadí', 'danger'); });
    }

    // ---------------------------------------------------------------
    // Move item
    // ---------------------------------------------------------------
    function moveItem(type, id, targetGroupId) {
        fetch('?do=moveItem', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ type: type, id: id, targetGroupId: targetGroupId }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    showToast('Přesunuto', 'success');
                    var el = itemList.querySelector('.lib-item[data-type="' + type + '"][data-id="' + id + '"]');
                    if (el) el.remove();
                    if (!itemList.children.length && emptyState) emptyState.classList.remove('d-none');
                    if (type === 'group') window.location.reload();
                } else {
                    showToast(data.error || 'Chyba při přesunu', 'danger');
                }
            })
            .catch(function () { showToast('Chyba při přesunu', 'danger'); });
    }

    // ---------------------------------------------------------------
    // Copy block
    // ---------------------------------------------------------------
    function copyBlock(blockId, targetGroupId) {
        fetch('?do=copyBlock', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ blockId: blockId, targetGroupId: targetGroupId }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    showToast('Blok zkopírován', 'success');
                    if (targetGroupId === currentGroupId) loadGroup(currentGroupId);
                } else {
                    showToast(data.error || 'Chyba při kopírování', 'danger');
                }
            })
            .catch(function () { showToast('Chyba při kopírování', 'danger'); });
    }

    // ---------------------------------------------------------------
    // Delete item
    // ---------------------------------------------------------------
    function deleteItem(type, id, name) {
        var msg = type === 'group'
            ? 'Opravdu smazat skupinu "' + name + '"?\nBloky v ní zůstanou bez skupiny.'
            : 'Opravdu smazat blok "' + name + '"?';
        if (!confirm(msg)) return;

        var signal = type === 'group' ? '?do=deleteGroup&id=' + id : '?do=deleteBlock&id=' + id;
        fetch(signal, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    var el = itemList.querySelector('.lib-item[data-type="' + type + '"][data-id="' + id + '"]');
                    if (el) el.remove();
                    if (!itemList.children.length && emptyState) emptyState.classList.remove('d-none');
                    showToast('Smazáno', 'success');
                    if (type === 'group') window.location.reload();
                } else {
                    showToast(data.error || 'Chyba při mazání', 'danger');
                }
            })
            .catch(function () { showToast('Chyba při mazání', 'danger'); });
    }

    // ---------------------------------------------------------------
    // Context menu
    // ---------------------------------------------------------------
    var ctxItem = null;

    function openContextMenu(x, y, item) {
        ctxItem = item;
        if (ctxCopy) ctxCopy.style.display = item.type === 'group' ? 'none' : '';
        ctxMenu.classList.remove('d-none');
        ctxMenu.style.left = x + 'px';
        ctxMenu.style.top  = y + 'px';
        requestAnimationFrame(function () {
            var rect = ctxMenu.getBoundingClientRect();
            if (rect.right > window.innerWidth)  ctxMenu.style.left = (x - rect.width) + 'px';
            if (rect.bottom > window.innerHeight) ctxMenu.style.top  = (y - rect.height) + 'px';
        });
    }

    function closeContextMenu() { ctxMenu.classList.add('d-none'); ctxItem = null; }

    if (ctxEdit) {
        ctxEdit.addEventListener('click', function () {
            if (!ctxItem) return;
            closeContextMenu();
            window.location.href = ctxItem.type === 'group'
                ? '/admin/groups/edit/' + ctxItem.id
                : '/admin/blocks/edit/' + ctxItem.id;
        });
    }

    if (ctxMove) {
        ctxMove.addEventListener('click', function () {
            if (!ctxItem) return;
            var name = ctxItem.type === 'group' ? ctxItem.name : ctxItem.text;
            closeContextMenu();
            startPendingAction('move', ctxItem.type, ctxItem.id, name);
        });
    }

    if (ctxCopy) {
        ctxCopy.addEventListener('click', function () {
            if (!ctxItem) return;
            closeContextMenu();
            startPendingAction('copy', ctxItem.type, ctxItem.id, ctxItem.text);
        });
    }

    if (ctxDelete) {
        ctxDelete.addEventListener('click', function () {
            if (!ctxItem) return;
            var name = ctxItem.type === 'group' ? ctxItem.name : ctxItem.text;
            closeContextMenu();
            deleteItem(ctxItem.type, ctxItem.id, name);
        });
    }

    document.addEventListener('click', function (e) {
        if (ctxMenu && !ctxMenu.classList.contains('d-none') && !ctxMenu.contains(e.target)) {
            closeContextMenu();
        }
    });

    // ---------------------------------------------------------------
    // Pending action (move / copy via tree click)
    // ---------------------------------------------------------------
    function startPendingAction(action, type, id, name) {
        pendingAction = { action: action, type: type, id: id };
        var verb = action === 'move' ? 'Přesunout' : 'Kopírovat';
        if (pendText) pendText.textContent = verb + ' „' + name + '" → klikněte na cílovou skupinu ve stromu vlevo';
        if (pendBanner) pendBanner.classList.remove('d-none');
        document.querySelectorAll('.tree-node, .tree-root-node').forEach(function (el) {
            el.classList.add('drop-target');
        });
    }

    function completePendingAction(targetGroupId) {
        if (!pendingAction) return;
        if (pendingAction.action === 'move') {
            moveItem(pendingAction.type, pendingAction.id, targetGroupId);
        } else {
            copyBlock(pendingAction.id, targetGroupId);
        }
        cancelPendingAction();
    }

    function cancelPendingAction() {
        pendingAction = null;
        if (pendBanner) pendBanner.classList.add('d-none');
        document.querySelectorAll('.tree-node, .tree-root-node').forEach(function (el) {
            el.classList.remove('drop-target');
        });
    }

    if (btnCancel) btnCancel.addEventListener('click', cancelPendingAction);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeContextMenu(); cancelPendingAction(); }
    });

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------
    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + (type || 'info');
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, 2500);
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ---------------------------------------------------------------
    // Start
    // ---------------------------------------------------------------
    init();

})();
