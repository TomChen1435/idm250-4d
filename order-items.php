<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$user_email = $_SESSION["user_email"] ?? "user@example.com";
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Get order header details
$order_result = $mysqli->query("SELECT * FROM orders WHERE id = $order_id");
$order = $order_result ? $order_result->fetch_assoc() : null;

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get individual units in this order
$items_result = $mysqli->query("
    SELECT oi.unit_id, oi.sku, s.description, s.uom, s.pieces
    FROM order_items oi
    LEFT JOIN sku s ON oi.sku = s.sku
    WHERE oi.order_id = $order_id
    ORDER BY oi.unit_id
");
$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];

// Count total units and group by SKU for summary
$sku_summary = [];
foreach ($items as $item) {
    $sku = $item['sku'];
    if (!isset($sku_summary[$sku])) {
        $sku_summary[$sku] = [
            'count' => 0,
            'description' => $item['description'],
            'uom' => $item['uom'],
            'pieces' => $item['pieces']
        ];
    }
    $sku_summary[$sku]['count']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Items - 4D WMS</title>
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
                <div class="header-email"><?= htmlspecialchars($user_email) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">
                <a href="orders.php" style="color: #6B7280; text-decoration: none;">Warehouse / Orders</a> 
                / <?= htmlspecialchars($order['order_number']) ?>
            </div>
            
            <div class="page-header">
                <div>
                    <h1 class="page-title">Order: <?= htmlspecialchars($order['order_number']) ?></h1>
                    <p class="page-subtitle">
                        Customer: <strong><?= htmlspecialchars($order['ship_to_company']) ?></strong> | 
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

            <!-- Order Summary -->
            <div class="card card-spaced">
                <div class="grid-3">
                    <div>
                        <div class="stat-label">Ship To</div>
                        <div class="text-md">
                            <?= htmlspecialchars($order['ship_to_company']) ?><br>
                            <span style="color: #6B7280; font-size: 13px;">
                                <?= htmlspecialchars($order['ship_to_street']) ?><br>
                                <?= htmlspecialchars($order['ship_to_city']) ?>, <?= htmlspecialchars($order['ship_to_state']) ?> <?= htmlspecialchars($order['ship_to_zip']) ?>
                            </span>
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">Created</div>
                        <div class="text-md">
                            <?= $order['created_at'] ? date('M d, Y g:i A', strtotime($order['created_at'])) : '—' ?>
                        </div>
                        <?php if ($order['shipped_at']): ?>
                        <div style="margin-top: 12px;">
                            <div class="stat-label">Shipped At</div>
                            <div class="text-md">
                                <?= date('M d, Y g:i A', strtotime($order['shipped_at'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="stat-label">Total Units</div>
                        <div class="text-xl">
                            <?= count($items) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SKU Summary -->
            <?php if (!empty($sku_summary)): ?>
            <div class="card card-spaced">
                <h3 class="text-lg">SKU Summary</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Units Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sku_summary as $sku => $summary): ?>
                        <tr>
                            <td class="sku-id"><?= htmlspecialchars($sku) ?></td>
                            <td><?= htmlspecialchars($summary['description']) ?></td>
                            <td><?= htmlspecialchars($summary['uom']) ?></td>
                            <td><span class="qty"><?= $summary['count'] ?></span> units</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Individual Units List -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Unit ID</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">
                                <p>No units found in this order.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="sku-id"><?= htmlspecialchars($item['unit_id']) ?></td>
                            <td class="sku-id"><?= htmlspecialchars($item['sku']) ?></td>
                            <td class="description"><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= htmlspecialchars($item['pieces']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer">© 2026 4D Warehouse Management System</footer>
    </div>

<script src="app.js"></script>
</body>
</html>
