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

// ═══════════════════════════════════════════════════════
// SKU MANAGEMENT (index.php)
// ═══════════════════════════════════════════════════════

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

// ═══════════════════════════════════════════════════════
// INVENTORY (inventory.php)
// ═══════════════════════════════════════════════════════

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


// ═══════════════════════════════════════════════════════
// ORDERS (orders.php)
// ═══════════════════════════════════════════════════════

function openOrderModal() {
    document.getElementById('orderModalTitle').textContent = 'New Order';
    document.getElementById('orderAction').value           = 'add';
    document.getElementById('orderId').value               = '';
    document.getElementById('orderNumber').value           = '';
    document.getElementById('orderNumber').readOnly        = false;
    document.getElementById('customerName').value          = '';
    document.getElementById('orderAddress').value          = '';
    document.getElementById('orderStatus').value           = 'pending';
    document.getElementById('orderModal').classList.add('active');
}

function openEditOrder(o) {
    document.getElementById('orderModalTitle').textContent = 'Edit Order';
    document.getElementById('orderAction').value           = 'update';
    document.getElementById('orderId').value               = o.id;
    document.getElementById('orderNumber').value           = o.order_number;
    document.getElementById('orderNumber').readOnly        = true;
    document.getElementById('customerName').value          = o.customer_name;
    document.getElementById('orderAddress').value          = o.address   || '';
    document.getElementById('orderStatus').value           = o.status    || 'pending';
    document.getElementById('orderModal').classList.add('active');
}

function closeOrderModal() {
    document.getElementById('orderModal').classList.remove('active');
}

function deleteOrder(id) {
    if (!confirm('Delete this order and all its items?')) return;
    document.getElementById('deleteOrderId').value = id;
    document.getElementById('deleteOrderForm').submit();
}

// ═══════════════════════════════════════════════════════
// ORDER ITEMS (order-items.php)
// ═══════════════════════════════════════════════════════

function openItemModal() {
    document.getElementById('itemModalTitle').textContent   = 'Add Item';
    document.getElementById('itemAction').value             = 'add_item';
    document.getElementById('itemId').value                 = '';
    document.getElementById('itemSkuSelect').value          = '';
    document.getElementById('itemOrdered').value            = '0';
    document.getElementById('itemShipped').value            = '0';
    document.getElementById('skuSelectWrap').style.display  = '';
    document.getElementById('skuTextWrap').style.display    = 'none';
    document.getElementById('itemModal').classList.add('active');
}

function openEditItem(item) {
    document.getElementById('itemModalTitle').textContent   = 'Edit Item';
    document.getElementById('itemAction').value             = 'update_item';
    document.getElementById('itemId').value                 = item.id;
    document.getElementById('itemSkuDisplay').value         = item.sku;
    document.getElementById('itemOrdered').value            = item.ordered;
    document.getElementById('itemShipped').value            = item.shipped;
    document.getElementById('skuSelectWrap').style.display  = 'none';
    document.getElementById('skuTextWrap').style.display    = '';
    document.getElementById('itemModal').classList.add('active');
}

function closeItemModal() {
    document.getElementById('itemModal').classList.remove('active');
}

function deleteItem(id) {
    if (!confirm('Remove this item from the order?')) return;
    document.getElementById('deleteItemId').value = id;
    document.getElementById('deleteItemForm').submit();
}


// ═══════════════════════════════════════════════════════
// MPL (mpl.php)
// ═══════════════════════════════════════════════════════

function openMPLModal() {
    document.getElementById('mplModalTitle').textContent = 'New MPL';
    document.getElementById('mplAction').value           = 'add';
    document.getElementById('mplId').value               = '';
    document.getElementById('mplNumber').value           = '';
    document.getElementById('mplNumber').readOnly        = false;
    document.getElementById('mplStatus').value           = 'pending';
    document.getElementById('mplOldStatus').value        = '';
    document.getElementById('mplModal').classList.add('active');
}

function openEditMPL(mpl) {
    document.getElementById('mplModalTitle').textContent = 'Edit MPL';
    document.getElementById('mplAction').value           = 'update';
    document.getElementById('mplId').value               = mpl.id;
    document.getElementById('mplNumber').value           = mpl.mpl_number;
    document.getElementById('mplNumber').readOnly        = true;
    document.getElementById('mplStatus').value           = mpl.status || 'pending';
    document.getElementById('mplOldStatus').value        = mpl.status || 'pending';
    document.getElementById('mplModal').classList.add('active');
}

function closeMPLModal() {
    document.getElementById('mplModal').classList.remove('active');
}

function deleteMPL(id) {
    if (!confirm('Delete this MPL and all its items?')) return;
    document.getElementById('deleteMPLId').value = id;
    document.getElementById('deleteMPLForm').submit();
}

// ═══════════════════════════════════════════════════════
// MPL ITEMS (mpl-items.php)
// ═══════════════════════════════════════════════════════

function openMPLItemModal() {
    document.getElementById('mplItemModalTitle').textContent   = 'Add Item';
    document.getElementById('mplItemAction').value             = 'add_item';
    document.getElementById('mplItemId').value                 = '';
    document.getElementById('mplItemSkuSelect').value          = '';
    document.getElementById('mplQtyExpected').value            = '0';
    document.getElementById('mplQtyReceived').value            = '0';
    document.getElementById('mplItemStatus').value             = 'pending';
    document.getElementById('mplSkuSelectWrap').style.display  = '';
    document.getElementById('mplSkuTextWrap').style.display    = 'none';
    document.getElementById('mplItemModal').classList.add('active');
}

function openEditMPLItem(item) {
    document.getElementById('mplItemModalTitle').textContent   = 'Edit Item';
    document.getElementById('mplItemAction').value             = 'update_item';
    document.getElementById('mplItemId').value                 = item.id;
    document.getElementById('mplItemSkuDisplay').value         = item.sku;
    document.getElementById('mplQtyExpected').value            = item.quantity_expected;
    document.getElementById('mplQtyReceived').value            = item.quantity_received;
    document.getElementById('mplItemStatus').value             = item.status || 'pending';
    document.getElementById('mplSkuSelectWrap').style.display  = 'none';
    document.getElementById('mplSkuTextWrap').style.display    = '';
    document.getElementById('mplItemModal').classList.add('active');
}

function closeMPLItemModal() {
    document.getElementById('mplItemModal').classList.remove('active');
}

function deleteMPLItem(id) {
    if (!confirm('Remove this item from the packing list?')) return;
    document.getElementById('deleteMPLItemId').value = id;
    document.getElementById('deleteMPLItemForm').submit();
}
