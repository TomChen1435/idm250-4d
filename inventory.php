<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$user_email = $_SESSION["user_email"] ?? "user@example.com";

// Get inventory grouped by SKU with unit counts
$query = "
    SELECT 
        i.sku,
        s.description,
        s.uom,
        s.pieces,
        COUNT(i.unit_id) as quantity_available,
        COUNT(CASE WHEN i.status = 'reserved' THEN 1 END) as quantity_reserved,
        MAX(i.received_at) as last_updated
    FROM inventory i
    LEFT JOIN sku s ON i.sku = s.sku
    WHERE i.status IN ('available', 'reserved')
    GROUP BY i.sku, s.description, s.uom, s.pieces
    ORDER BY i.sku
";

$result = $mysqli->query($query);
$inventory = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate stats
$total_skus = count($inventory);
$total_units = array_sum(array_column($inventory, 'quantity_available'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - 4D WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item"><p>SKUs</p></a>
            <a href="inventory.php" class="nav-item active"><p>Current Inventory</p></a>
            <a href="mpl.php" class="nav-item"><p>MPL</p></a>
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
                <div class="header-email"><?= htmlspecialchars($user_email) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">Warehouse / Current Inventory</div>
            <h1 class="page-title">Current Inventory</h1>
            <p class="page-subtitle">Track all units currently in the warehouse</p>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total SKUs</div>
                    <div class="stat-value"><?= number_format($total_skus) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Units</div>
                    <div class="stat-value green"><?= number_format($total_units) ?></div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-bar">
                <input 
                    type="text" 
                    class="search-input" 
                    placeholder="Search by SKU or description..." 
                    id="searchInput"
                    onkeyup="filterInventoryTable()"
                >
            </div>

            <!-- Inventory Table -->
            <div class="card">
                <table class="table" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces/Unit</th>
                            <th>Units Available</th>
                            <th>Units Reserved</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="8" class="empty-state">
                                <p>No inventory found.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inventory as $item):
                            $available = (int)$item['quantity_available'];
                            $reserved = (int)$item['quantity_reserved'];
                            
                            // Determine stock status
                            if ($available === 0) {
                                $status = 'Out of Stock';
                                $status_class = 'badge-out';
                            } elseif ($available < 10) {
                                $status = 'Low Stock';
                                $status_class = 'badge-low';
                            } else {
                                $status = 'In Stock';
                                $status_class = 'badge-in-stock';
                            }
                        ?>
                        <tr>
                            <td class="sku-id"><?= htmlspecialchars($item['sku']) ?></td>
                            <td class="description"><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= htmlspecialchars($item['pieces']) ?></td>
                            <td class="qty"><?= number_format($available) ?></td>
                            <td class="qty-reserved"><?= number_format($reserved) ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status ?></span></td>
                            <td class="date-cell"><?= $item['last_updated'] ? date('M d, Y', strtotime($item['last_updated'])) : '—' ?></td>
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
