<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$user_email = $_SESSION["user_email"] ?? "user@example.com";

// Get individual units from inventory
$query = "
    SELECT 
        i.unit_id,
        i.sku,
        s.description,
        s.uom,
        s.pieces,
        i.location,
        i.status,
        i.received_at,
        pl.reference_number
    FROM inventory i
    LEFT JOIN sku s ON i.sku = s.sku
    LEFT JOIN packing_list_items pli ON i.unit_id = pli.unit_id
    LEFT JOIN packing_list pl ON pli.mpl_id = pl.id
    WHERE i.status IN ('available', 'reserved')
    ORDER BY i.received_at DESC
";

$result = $mysqli->query($query);
$inventory = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Calculate stats
$total_units = count($inventory);
$unique_skus = count(array_unique(array_column($inventory, 'sku')));
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
                    <div class="stat-label">Unique SKUs</div>
                    <div class="stat-value"><?= number_format($unique_skus) ?></div>
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
                            <th>Unit ID</th>
                            <th>Reference Number</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Received</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <p>No inventory found.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><span class="sku-id"><?= htmlspecialchars($item['unit_id']) ?></span></td>
                            
                            <td><span class="sku-id"><?= htmlspecialchars($item['reference_number']) ?></span></td>
                            <td><span class="sku-id"><?= htmlspecialchars($item['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($item['description']) ?></span></td>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= htmlspecialchars($item['pieces']) ?></td>
                            <td><?= htmlspecialchars($item['location']) ?></td>
                            <td>
                                <span class="status-badge <?= $item['status'] === 'available' ? 'badge-in-stock' : 'badge-reserved' ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </td>
                            <td class="date-cell"><?= date('M d, Y g:i A', strtotime($item['received_at'])) ?></td>
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
