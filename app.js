
// Login & Registration

function switchTab(tab) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('mode', tab);
    window.history.pushState({}, '', url);
    
    // Show/hide tabs
    const loginTab = document.getElementById('login-tab');
    const registerTab = document.getElementById('register-tab');
    
    if (loginTab && registerTab) {
        loginTab.classList.toggle('active', tab === 'login');
        registerTab.classList.toggle('active', tab === 'register');
    }
    
    // Update tab buttons
    document.querySelectorAll('.login-tab').forEach((btn, i) => {
        btn.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
    });
}

function togglePassword(fieldId) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    
    const btn = input.parentElement.querySelector('.password-toggle-btn');
    if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        if (btn) btn.textContent = 'Show';
    }
}


// Modal Handlers


document.addEventListener('DOMContentLoaded', function () {
    // Close modal on backdrop click
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    });
});

function closeModal() {
    document.querySelectorAll('.modal').forEach(function (m) {
        m.classList.remove('active');
    });
}


// SKU Management


function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New SKU';
    document.getElementById('formAction').value = 'add';
    document.getElementById('skuId').value = '';
    document.getElementById('ficha').value = '';
    document.getElementById('sku').value = '';
    document.getElementById('description').value = '';
    document.getElementById('uom').value = '';
    document.getElementById('pieces').value = '';
    document.getElementById('length').value = '';
    document.getElementById('width').value = '';
    document.getElementById('height').value = '';
    document.getElementById('weight').value = '';
    document.getElementById('skuModal').classList.add('active');
}

function editSKU(sku) {
    document.getElementById('modalTitle').textContent = 'Edit SKU';
    document.getElementById('formAction').value = 'update';
    document.getElementById('skuId').value = sku.id;
    document.getElementById('ficha').value = sku.ficha;
    document.getElementById('sku').value = sku.sku;
    document.getElementById('description').value = sku.description || '';
    document.getElementById('uom').value = sku.uom || '';
    document.getElementById('pieces').value = sku.pieces || '';
    document.getElementById('length').value = sku.length || '';
    document.getElementById('width').value = sku.width || '';
    document.getElementById('height').value = sku.height || '';
    document.getElementById('weight').value = sku.weight || '';
    document.getElementById('skuModal').classList.add('active');
}

function deleteSKU(id) {
    if (!confirm('Are you sure you want to delete this SKU?')) return;
    document.getElementById('deleteSKUId').value = id;
    document.getElementById('deleteSKUForm').submit();
}


// Inventory


function filterInventoryTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('inventoryTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const tdSku = tr[i].getElementsByTagName('td')[0];
        const tdDesc = tr[i].getElementsByTagName('td')[1];
        
        if (tdSku || tdDesc) {
            const skuValue = tdSku.textContent || tdSku.innerText;
            const descValue = tdDesc.textContent || tdDesc.innerText;
            
            if (skuValue.toUpperCase().indexOf(filter) > -1 || 
                descValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = '';
            } else {
                tr[i].style.display = 'none';
            }
        }
    }
}


// Orders


function shipOrder(id) {
    if (!confirm('Ship this order? This will update inventory.')) return;
    document.getElementById('shipOrderId').value = id;
    document.getElementById('shipOrderForm').submit();
}

function deleteOrder(id) {
    if (!confirm('Delete this order and all its items?')) return;
    document.getElementById('deleteOrderId').value = id;
    document.getElementById('deleteOrderForm').submit();
}


// MPL


function confirmMPL(id) {
    if (!confirm('Confirm this MPL? This will add units to inventory.')) return;
    document.getElementById('confirmMPLId').value = id;
    document.getElementById('confirmMPLForm').submit();
}

function deleteMPL(id) {
    if (!confirm('Delete this MPL and all its items?')) return;
    document.getElementById('deleteMPLId').value = id;
    document.getElementById('deleteMPLForm').submit();
}


// Shipped Items


function filterShippedTable() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.getElementById('shippedTable');
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        const tds = tr[i].getElementsByTagName('td');
        let found = false;
        
        for (let j = 0; j < 4; j++) { // Search first 4 columns
            if (tds[j]) {
                const txtValue = tds[j].textContent || tds[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}
