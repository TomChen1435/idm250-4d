<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';

// Get Order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Get Order details
$order_result = $mysqli->query("SELECT * FROM orders WHERE id = $order_id");
$order = $order_result ? $order_result->fetch_assoc() : null;

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get Order items - using 'ordered' column
$items_result = $mysqli->query("
    SELECT oi.*, s.description, s.uom, s.pieces
    FROM order_items oi
    LEFT JOIN sku s ON oi.sku = s.sku
    WHERE oi.order_id = $order_id
    ORDER BY oi.id ASC
");
$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Items - <?= htmlspecialchars($order['order_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item"><p>SKUs</p></a>
            <a href="inventory.php" class="nav-item"><p>Current Inventory</p></a>
            <a href="mpl.php" class="nav-item"><p>MPL</p></a>
            <a href="orders.php" class="nav-item active"><p>Orders</p></a>
            <a href="shipped.php" class="nav-item"><p>Shipped Items</p></a>
        </nav>
        <div class="logout">
            <a href="logout.php" class="logout-btn">
                <p>Logout</p>
            </a>
        </div>
    </div>

    <div class="main-content">
        <header class="header">
            <div></div>
            <div class="header-right">
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">
                <a href="orders.php" style="color: #6B7280; text-decoration: none;">Warehouse / Orders</a> 
                / <?= htmlspecialchars($order['order_number']) ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h1 class="page-title">Order: <?= htmlspecialchars($order['order_number']) ?></h1>
                    <p class="page-subtitle">
                        Customer: <strong><?= htmlspecialchars($order['customer_name']) ?></strong> | 
                        Status: <?php
                            $status = $order['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'shipped' => 'badge-in-stock',
                                'processing' => 'badge-processing',
                                'cancelled' => 'badge-out',
                                default => 'badge-low'
                            };
                        ?>
                        <span class="status-badge <?= $badge_class ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </p>
                </div>
                <a href="orders.php" class="btn-secondary">Back to Orders</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, 'Success') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Order Info Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Customer</div>
                        <div style="font-weight: 500;"><?= htmlspecialchars($order['customer_name']) ?></div>
                    </div>
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Address</div>
                        <div style="font-weight: 500;"><?= htmlspecialchars($order['address'] ?: '—') ?></div>
                    </div>
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Created</div>
                        <div style="font-weight: 500;"><?= date('M d, Y g:i A', strtotime($order['time_created'])) ?></div>
                    </div>
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Shipped</div>
                        <div style="font-weight: 500;"><?= $order['time_shipped'] ? date('M d, Y g:i A', strtotime($order['time_shipped'])) : '—' ?></div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="card">
                <h3 style="margin: 0 0 20px 0; font-size: 18px;">Line Items</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces</th>
                            <th>Ordered</th>
                            <th>Shipped</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 40px; color: #9CA3AF;">
                                No items found for this order
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="sku-id"><?= htmlspecialchars($item['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($item['description'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($item['uom'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($item['pieces'] ?? '—') ?></td>
                            <td><span class="qty"><?= number_format($item['ordered']) ?></span></td>
                            <td><span class="qty"><?= number_format($item['shipped']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer">© 2026 4D Warehouse Management System</footer>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
