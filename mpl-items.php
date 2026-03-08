<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';

// Get MPL ID from URL
$mpl_id = isset($_GET['mpl_id']) ? (int)$_GET['mpl_id'] : 0;

if (!$mpl_id) {
    header('Location: mpl.php');
    exit;
}

// Get MPL details
$mpl_result = $mysqli->query("SELECT * FROM packing_list WHERE id = $mpl_id");
$mpl = $mpl_result ? $mpl_result->fetch_assoc() : null;

if (!$mpl) {
    header('Location: mpl.php');
    exit;
}

// Get MPL items - handle both sku_id and sku column names
$columns_check = $mysqli->query("SHOW COLUMNS FROM packing_list_items LIKE 'sku%'");
$has_sku_id = false;

while ($col = $columns_check->fetch_assoc()) {
    if ($col['Field'] === 'sku_id') $has_sku_id = true;
}

if ($has_sku_id) {
    $items_result = $mysqli->query("
        SELECT pli.*, s.sku, s.description, s.uom, s.pieces
        FROM packing_list_items pli
        JOIN sku s ON pli.sku_id = s.id
        WHERE pli.mpl_id = $mpl_id
        ORDER BY pli.id ASC
    ");
} else {
    $items_result = $mysqli->query("
        SELECT pli.*, s.description, s.uom, s.pieces
        FROM packing_list_items pli
        LEFT JOIN sku s ON pli.sku = s.sku
        WHERE pli.mpl_id = $mpl_id
        ORDER BY pli.id ASC
    ");
}
$items = $items_result ? $items_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPL Items - <?= htmlspecialchars($mpl['mpl_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item"><p>SKUs</p></a>
            <a href="inventory.php" class="nav-item"><p>Current Inventory</p></a>
            <a href="mpl.php" class="nav-item active"><p>MPL</p></a>
            <a href="orders.php" class="nav-item"><p>Orders</p></a>
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
                <a href="mpl.php" style="color: #6B7280; text-decoration: none;">Warehouse / MPL</a> 
                / <?= htmlspecialchars($mpl['mpl_number']) ?>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h1 class="page-title">MPL: <?= htmlspecialchars($mpl['mpl_number']) ?></h1>
                    <p class="page-subtitle">
                        Status: <span class="status-badge <?= $mpl['status'] === 'confirmed' ? 'badge-in-stock' : 'badge-low' ?>">
                            <?= ucfirst($mpl['status']) ?>
                        </span>
                    </p>
                </div>
                <a href="mpl.php" class="btn-secondary">← Back to MPL List</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- MPL Info Card -->
            <div class="card" style="margin-bottom: 20px;">
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Created</div>
                        <div style="font-weight: 500;"><?= date('M d, Y g:i A', strtotime($mpl['created_at'])) ?></div>
                    </div>
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Confirmed At</div>
                        <div style="font-weight: 500;"><?= $mpl['confirmed_at'] ? date('M d, Y g:i A', strtotime($mpl['confirmed_at'])) : '—' ?></div>
                    </div>
                    <div>
                        <div style="color: #6B7280; font-size: 14px; margin-bottom: 5px;">Total Items</div>
                        <div style="font-weight: 500;"><?= count($items) ?></div>
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
                            <th>Quantity Expected</th>
                            <th>Quantity Received</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding: 40px; color: #9CA3AF;">
                                No items found for this MPL
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><span class="sku-id"><?= htmlspecialchars($item['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($item['description'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($item['uom'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($item['pieces'] ?? '—') ?></td>
                            <td><span class="qty"><?= number_format($item['quantity_expected']) ?></span></td>
                            <td><span class="qty"><?= number_format($item['quantity_received']) ?></span></td>
                            <td>
                                <span class="status-badge <?= $item['status'] === 'received' ? 'badge-in-stock' : 'badge-low' ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </td>
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
