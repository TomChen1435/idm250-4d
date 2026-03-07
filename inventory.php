<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';

// Stats
$total_row  = $mysqli->query("SELECT SUM(quantity_available) AS total FROM inventory")->fetch_assoc();
$in_stock   = $mysqli->query("SELECT SUM(quantity_available) AS total FROM inventory WHERE quantity_available > 0")->fetch_assoc();
$total_units = (int)($total_row['total'] ?? 0);
$in_stock_units = (int)($in_stock['total'] ?? 0);

// Fetch inventory joined with sku table
$search       = isset($_GET['search']) ? $mysqli->real_escape_string(trim($_GET['search'])) : '';
$uom_filter   = isset($_GET['uom'])    ? $mysqli->real_escape_string(trim($_GET['uom']))    : '';

$where_parts = [];
if ($search)     $where_parts[] = "(i.sku LIKE '%$search%' OR s.description LIKE '%$search%')";
if ($uom_filter) $where_parts[] = "s.uom = '$uom_filter'";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$result = $mysqli->query("
    SELECT i.id, i.sku, i.quantity_available, i.quantity_reserved, i.last_updated,
           s.description, s.uom, s.pieces
    FROM inventory i
    LEFT JOIN sku s ON i.sku = s.sku
    $where
    ORDER BY i.id ASC
    LIMIT 100
");
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// UOM options for filter
$uom_result = $mysqli->query("SELECT DISTINCT uom FROM sku WHERE uom IS NOT NULL ORDER BY uom");
$uoms = $uom_result ? $uom_result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Inventory - 4D Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item">
                <p>SKUs</p>
            </a>
            <a href="inventory.php" class="nav-item active">
                <p>Current Inventory</p>
            </a>

             <a href="mpl.php" class="nav-item">
                <p>MPL</p>
            </a>

            <a href="orders.php" class="nav-item">
                <p>Orders</p>
            </a>
        
            <a href="shipped.php" class="nav-item">
                <p>Shipped Items</p>
            </a>
           
        </nav>
        <div class="logout">
            <a href="logout.php" class="logout-btn">
                <p>Logout</p>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <header class="header">
            <div></div>
            <div class="header-right">

                <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">Warehouse / Current Inventory</div>
            <h1 class="page-title">Current Inventory Levels</h1>
            <p class="page-subtitle">Automatically updated when MPLs are confirmed (+) and orders are shipped (−)</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Units</div>
                    <div class="stat-value"><?= number_format($total_units) ?> units</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">In Stock</div>
                    <div class="stat-value green"><?= number_format($in_stock_units) ?> units</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <input type="text" class="search-input" style="max-width:340px;"
                       placeholder="Search by SKU or description..."
                       value="<?= htmlspecialchars($search) ?>"
                       onchange="window.location.href='inventory.php?search='+encodeURIComponent(this.value)+'<?= $uom_filter ? '&uom='.urlencode($uom_filter) : '' ?>'">

                <select class="select-box"
                        onchange="window.location.href='inventory.php?uom='+encodeURIComponent(this.value)+'<?= $search ? '&search='.urlencode($search) : '' ?>'">
                    <option value="">All UOM</option>
                    <?php foreach ($uoms as $u): ?>
                        <option value="<?= htmlspecialchars($u['uom']) ?>" <?= $uom_filter === $u['uom'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['uom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces</th>
                            <th>Available</th>
                            <th>Reserved</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📦</div>
                                    <p>No inventory yet.<br>Inventory will be added automatically when you confirm MPLs.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($rows as $row):
                            $avail = (int)$row['quantity_available'];
                            if ($avail === 0) {
                                $badge = 'badge-out'; $label = 'Out of Stock';
                            } elseif ($avail <= 10) {
                                $badge = 'badge-low'; $label = 'Low Stock';
                            } else {
                                $badge = 'badge-in-stock'; $label = 'In Stock';
                            }
                        ?>
                        <tr>
                            <td><span class="sku-id"><?= htmlspecialchars($row['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($row['description'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($row['uom'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['pieces'] ?? '—') ?></td>
                            <td><span class="qty"><?= number_format($avail) ?></span></td>
                            <td><span class="qty-reserved"><?= number_format((int)$row['quantity_reserved']) ?></span></td>
                            <td><span class="status-badge <?= $badge ?>"><?= $label ?></span></td>
                            <td style="color:#6B7280; font-size:13px;">
                                <?= $row['last_updated'] ? date('M d, Y', strtotime($row['last_updated'])) : '—' ?>
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

    <script src="app.js"></script>
</body>
</html>
