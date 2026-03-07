<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';

// Filters
$search = isset($_GET['search']) ? $mysqli->real_escape_string(trim($_GET['search'])) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_parts = [];
if ($search) {
    $where_parts[] = "(si.order_number LIKE '%$search%' OR si.customer_name LIKE '%$search%' OR si.sku LIKE '%$search%')";
}
if ($date_from) {
    $date_from_safe = $mysqli->real_escape_string($date_from);
    $where_parts[] = "DATE(si.shipped_at) >= '$date_from_safe'";
}
if ($date_to) {
    $date_to_safe = $mysqli->real_escape_string($date_to);
    $where_parts[] = "DATE(si.shipped_at) <= '$date_to_safe'";
}
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Fetch shipped items
$result = $mysqli->query("
    SELECT si.*, s.description, s.uom
    FROM shipped_items si
    LEFT JOIN sku s ON si.sku = s.sku
    $where
    ORDER BY si.shipped_at DESC
    LIMIT 200
");
$items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Stats
$total_shipped = $mysqli->query("SELECT COUNT(*) AS total FROM shipped_items")->fetch_assoc()['total'] ?? 0;
$total_quantity = $mysqli->query("SELECT SUM(quantity) AS total FROM shipped_items")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipped Items - 4D WMS</title>
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
            <a href="orders.php" class="nav-item"><p>Orders</p></a>
            <a href="shipped.php" class="nav-item active"><p>Shipped</p></a>
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
            <div class="breadcrumb">Warehouse / Shipped Items</div>
            <h1 class="page-title">Shipped Items History</h1>
            <p class="page-subtitle">Complete record of all shipped orders</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Shipments</div>
                    <div class="stat-value"><?= number_format($total_shipped) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Units Shipped</div>
                    <div class="stat-value"><?= number_format($total_quantity) ?> units</div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filters">
                <input type="text" 
                       name="search" 
                       class="search-input" 
                       style="max-width:340px;"
                       placeholder="Search by order #, customer, or SKU..."
                       value="<?= htmlspecialchars($search) ?>">

                <input type="date" 
                       name="date_from" 
                       class="form-input" 
                       style="max-width:160px;"
                       value="<?= htmlspecialchars($date_from) ?>"
                       placeholder="From">

                <input type="date" 
                       name="date_to" 
                       class="form-input" 
                       style="max-width:160px;"
                       value="<?= htmlspecialchars($date_to) ?>"
                       placeholder="To">

                <button type="submit" class="btn-primary">Filter</button>
                
                <?php if ($search || $date_from || $date_to): ?>
                    <a href="shipped.php" class="btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Shipped Date</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📦</div>
                                    <p>No shipped items yet.<br>Items will appear here when orders are shipped.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="date-cell"><?= date('M d, Y g:i A', strtotime($item['shipped_at'])) ?></td>
                            <td>
                                <span class="sku-id"><?= htmlspecialchars($item['order_number']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($item['customer_name']) ?></td>
                            <td><span class="sku-id"><?= htmlspecialchars($item['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($item['description'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($item['uom'] ?? '—') ?></td>
                            <td><span class="qty"><?= number_format($item['quantity']) ?></span></td>
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
