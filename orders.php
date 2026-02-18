<?php
session_start();
require_once 'db_connect.php';




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - 4D Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item">
                <p>SKUs</p>
            </a>
            <a href="inventory.php" class="nav-item">
                <p>Current Inventory</p>
            </a>
            <a href="orders.php" class="nav-item active">
                <p>Orders</p>
            </a>

             <a href="order-items.php" class="nav-item">
                <p>Order Items</p>
            </a>
            
            <a href="shipped.php" class="nav-item">
                <p>Shipped</p>
            </a>

            <a href="mpl.php" class="nav-item">
                <p>MPL</p>
            </a>
        </nav>
        <div class="logout">
            <a href="#" class="logout-btn">
                <p>Logout</p>
            </a>
        </div>
    </div>

    <div class="main-content">
        <header class="header">
            <div></div>
            <div class="header-right">
                <button class="icon-btn">🔔</button>
                <button class="icon-btn">⚙️</button>
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">Warehouse / Orders</div>
            <h1 class="page-title">Order Management</h1>
            <p class="page-subtitle">Track and manage all customer orders</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?= number_format($total_orders) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Items</div>
                    <div class="stat-value"><?= number_format($total_items) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:#D97706;"><?= number_format($pending_count) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Shipped</div>
                    <div class="stat-value green"><?= number_format($shipped_count) ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <input type="text" class="search-input" style="max-width:340px;"
                       placeholder="Search by order #, customer, or address..."
                       value="<?= htmlspecialchars($search) ?>"
                       onchange="window.location.href='orders.php?search='+encodeURIComponent(this.value)+'<?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>'">

                <select class="select-box"
                        onchange="window.location.href='orders.php?status='+this.value+'<?= $search ? '&search='.urlencode($search) : '' ?>'">
                    <option value="">All Statuses</option>
                    <option value="pending"   <?= $status_filter === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="processing"<?= $status_filter === 'processing'? 'selected' : '' ?>>Processing</option>
                    <option value="shipped"   <?= $status_filter === 'shipped'   ? 'selected' : '' ?>>Shipped</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <button class="btn-primary" onclick="openOrderModal()" style="margin-left:auto;">
                    + New Order
                </button>
            </div>

            <!-- Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Shipped</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📋</div>
                                    <p>No orders yet. Create your first order above.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o):
                            $status = $o['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'shipped'    => 'badge-in-stock',
                                'processing' => 'badge-processing',
                                'cancelled'  => 'badge-out',
                                default      => 'badge-low'
                            };
                        ?>
                        <tr>
                            <td>
                                <a href="order-items.php?order_id=<?= $o['id'] ?>" class="order-link">
                                    <?= htmlspecialchars($o['order_number']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td class="description"><?= htmlspecialchars($o['address'] ?? '—') ?></td>
                            <td><span class="qty"><?= (int)$o['total_items'] ?></span></td>
                            <td><span class="status-badge <?= $badge_class ?>"><?= ucfirst($status) ?></span></td>
                            <td class="date-cell"><?= $o['time_created'] ? date('M d, Y', strtotime($o['time_created'])) : '—' ?></td>
                            <td class="date-cell"><?= $o['time_shipped'] ? date('M d, Y', strtotime($o['time_shipped'])) : '—' ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="order-items.php?order_id=<?= $o['id'] ?>" class="edit-btn">🧾 Items</a>
                                    <button class="edit-btn" onclick='openEditOrder(<?= htmlspecialchars(json_encode($o), ENT_QUOTES) ?>)'>✏️ Edit</button>
                                    <button class="delete-btn" onclick="deleteOrder(<?= $o['id'] ?>)">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer">© 2025 4D Warehouse System</footer>
    </div>

    <!-- Add / Edit Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="orderModalTitle">New Order</h2>
            <form method="POST">
                <input type="hidden" name="action" id="orderAction" value="add">
                <input type="hidden" name="id"     id="orderId">

                <div class="form-group">
                    <label class="form-label">Order Number</label>
                    <input type="text" name="order_number" id="orderNumber" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" id="customerName" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="orderAddress" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="orderStatus" class="form-input">
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Order</button>
                    <button type="button" class="btn-secondary" onclick="closeOrderModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete form -->
    <form id="deleteOrderForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id"     id="deleteOrderId">
    </form>

    <script src="js/app.js"></script>
</body>
</html>
