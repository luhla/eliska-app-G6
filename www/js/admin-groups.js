/**
 * admin-groups.js
 * Sortable drag-and-drop for group list + delete.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Sortable on flat group list
    // ---------------------------------------------------------------
    const list = document.getElementById('group-list');
    if (list && typeof Sortable !== 'undefined') {
        Sortable.create(list, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                const items = [];
                list.querySelectorAll('[data-id]').forEach(function (el, index) {
                    items.push({ id: parseInt(el.dataset.id, 10), sort_order: index });
                });

                fetch('?do=saveOrder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ items: items }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            showToast('Pořadí uloženo', 'success');
                        } else {
                            showToast('Chyba při ukládání pořadí', 'danger');
                        }
                    });
            },
        });
    }

    // ---------------------------------------------------------------
    // Delete group
    // ---------------------------------------------------------------
    document.querySelectorAll('.btn-delete-group').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            const name = this.dataset.name;
            if (!confirm('Opravdu smazat skupinu "' + name + '"?\nBloky ve skupině zůstanou (přesunou se do kořene), podskupiny budou smazány.')) return;

            const row = this.closest('[data-id]');

            fetch('?do=deleteGroup&id=' + encodeURIComponent(id), {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        if (row) row.remove();
                        showToast('Skupina byla smazána', 'success');
                    } else {
                        showToast('Chyba: ' + (data.error || 'Neznámá chyba'), 'danger');
                    }
                });
        });
    });

    function showToast(message, type) {
        type = type || 'info';
        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('show'); }, 10);
        setTimeout(function () {
            toast.classList.remove('show');
            setTimeout(function () { toast.remove(); }, 300);
        }, 2500);
    }
})();
