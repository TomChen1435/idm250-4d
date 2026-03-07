<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$user_email = $_SESSION["user_email"] ?? "user@example.com";

// Get shipped items with order details
$result = $mysqli->query("
    SELECT si.*, o.ship_to_company
    FROM shipped_items si
    LEFT JOIN orders o ON si.order_id = o.id
    ORDER BY si.shipped_at DESC
    LIMIT 500
");
$shipped = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Count total units
$total_units = count($shipped);
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
            <a href="shipped.php" class="nav-item active"><p>Shipped Items</p></a>
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
            <div class="breadcrumb">Warehouse / Shipped Items</div>
            <h1 class="page-title">Shipped Items</h1>
            <p class="page-subtitle">History of all shipped units</p>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-label">Total Units Shipped</div>
                    <div class="stat-value"><?= number_format($total_units) ?></div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-bar">
                <input 
                    type="text" 
                    class="search-input" 
                    placeholder="Search by order, unit ID, or SKU..." 
                    id="searchInput"
                    onkeyup="filterShippedTable()"
                >
            </div>

            <!-- Shipped Items Table -->
            <div class="card">
                <table class="table" id="shippedTable">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Unit ID</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>Customer</th>
                            <th>Shipped Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($shipped)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <p>No shipped items found.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($shipped as $item): ?>
                        <tr>
                            <td class="order-link"><?= htmlspecialchars($item['order_number']) ?></td>
                            <td class="sku-id"><?= htmlspecialchars($item['unit_id']) ?></td>
                            <td class="sku-id"><?= htmlspecialchars($item['sku']) ?></td>
                            <td class="description"><?= htmlspecialchars($item['sku_description']) ?></td>
                            <td><?= htmlspecialchars($item['ship_to_company']) ?></td>
                            <td class="date-cell"><?= date('M d, Y g:i A', strtotime($item['shipped_at'])) ?></td>
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
