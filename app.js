// app.js — 4D Warehouse Management System

// ── Generic modal close on backdrop click ─────────────
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    });

});

// ── Shared close ──────────────────────────────────────
function closeModal() {
    document.querySelectorAll('.modal').forEach(function (m) {
        m.classList.remove('active');
    });
}


// SKU MANAGEMENT (index.php)


function openAddModal() {
    document.getElementById('modalTitle').textContent  = 'Add New SKU';
    document.getElementById('formAction').value        = 'add';
    document.getElementById('skuId').value             = '';
    document.getElementById('ficha').value             = '';
    document.getElementById('sku').value               = '';
    document.getElementById('description').value       = '';
    document.getElementById('uom').value               = '';
    document.getElementById('pieces').value            = '';
    document.getElementById('length').value            = '';
    document.getElementById('width').value             = '';
    document.getElementById('height').value            = '';
    document.getElementById('weight').value            = '';
    document.getElementById('skuModal').classList.add('active');
}

function editSKU(s) {
    document.getElementById('modalTitle').textContent  = 'Edit SKU';
    document.getElementById('formAction').value        = 'update';
    document.getElementById('skuId').value             = s.id;
    document.getElementById('ficha').value             = s.ficha;
    document.getElementById('sku').value               = s.sku;
    document.getElementById('description').value       = s.description || '';
    document.getElementById('uom').value               = s.uom        || '';
    document.getElementById('pieces').value            = s.pieces     || '';
    document.getElementById('length').value            = s.length     || '';
    document.getElementById('width').value             = s.width      || '';
    document.getElementById('height').value            = s.height     || '';
    document.getElementById('weight').value            = s.weight     || '';
    document.getElementById('skuModal').classList.add('active');
}


// INVENTORY (inventory.php)


function openInvAddModal() {
    document.getElementById('modalTitle').textContent       = 'Add Inventory Record';
    document.getElementById('formAction').value             = 'add';
    document.getElementById('rowId').value                  = '';
    document.getElementById('skuSelect').value              = '';
    document.getElementById('qty_available').value          = '0';
    document.getElementById('qty_reserved').value           = '0';
    document.getElementById('skuSelectGroup').style.display = '';
    document.getElementById('skuTextGroup').style.display   = 'none';
    document.getElementById('invModal').classList.add('active');
}

function editRow(r) {
    document.getElementById('modalTitle').textContent       = 'Edit Inventory';
    document.getElementById('formAction').value             = 'update';
    document.getElementById('rowId').value                  = r.id;
    document.getElementById('skuDisplay').value             = r.sku;
    document.getElementById('qty_available').value          = r.quantity_available;
    document.getElementById('qty_reserved').value           = r.quantity_reserved;
    document.getElementById('skuSelectGroup').style.display = 'none';
    document.getElementById('skuTextGroup').style.display   = '';
    document.getElementById('invModal').classList.add('active');
}
