<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$user_email = $_SESSION["user_email"] ?? "user@example.com";
$mpl_id = isset($_GET['mpl_id']) ? (int)$_GET['mpl_id'] : 0;

// Get MPL header details
$mpl_result = $mysqli->query("SELECT pl.*, u.email as confirmed_by
                               FROM packing_list pl
                               LEFT JOIN users u ON pl.confirmed_by_user_id = u.id
                               WHERE pl.id = $mpl_id");
$mpl = $mpl_result ? $mpl_result->fetch_assoc() : null;

if (!$mpl) {
    header('Location: mpl.php');
    exit;
}

// Get individual units in this MPL
$items_result = $mysqli->query("
    SELECT pli.unit_id, pli.sku, pli.status, s.description, s.uom, s.pieces
    FROM packing_list_items pli
    LEFT JOIN sku s ON pli.sku = s.sku
    WHERE pli.mpl_id = $mpl_id
    ORDER BY pli.unit_id
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
    <title>MPL Items - 4D WMS</title>
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
                <div class="header-email"><?= htmlspecialchars($user_email) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">
                <a href="mpl.php" class="breadcrumb-link">Warehouse / MPL</a> 
                / <?= htmlspecialchars($mpl['reference_number']) ?>
            </div>
            
            <div class="page-header">
                <div>
                    <h1 class="page-title">MPL: <?= htmlspecialchars($mpl['reference_number']) ?></h1>
                    <p class="page-subtitle">
                        Status: <?php
                            $status = $mpl['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'confirmed' => 'badge-in-stock',
                                'cancelled' => 'badge-out',
                                default => 'badge-low'
                            };
                        ?>
                        <span class="status-badge <?= $badge_class ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </p>
                </div>
                <a href="mpl.php" class="btn-secondary">Back to MPL List</a>
            </div>

            <!-- MPL Summary -->
            <div class="card card-spaced">
                <div class="grid-4">
                    <div>
                        <div class="stat-label">Created</div>
                        <div class="text-md">
                            <?= $mpl['created_at'] ? date('M d, Y g:i A', strtotime($mpl['created_at'])) : '—' ?>
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">Confirmed At</div>
                        <div class="text-md">
                            <?= $mpl['confirmed_at'] ? date('M d, Y g:i A', strtotime($mpl['confirmed_at'])) : '—' ?>
                        </div>
                    </div>
                    <div>
                        <div class="stat-label">Confirmed By</div>
                        <div class="text-md">
                            <?= htmlspecialchars($mpl['confirmed_by'] ?? '—') ?>
                        </div>
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
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <p>No units found in this MPL.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item):
                            $item_status = $item['status'] ?? 'pending';
                            $status_badge = match($item_status) {
                                'received' => 'badge-in-stock',
                                default => 'badge-low'
                            };
                        ?>
                        <tr>
                            <td class="sku-id"><?= htmlspecialchars($item['unit_id']) ?></td>
                            <td class="sku-id"><?= htmlspecialchars($item['sku']) ?></td>
                            <td class="description"><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= htmlspecialchars($item['uom']) ?></td>
                            <td><?= htmlspecialchars($item['pieces']) ?></td>
                            <td><span class="status-badge <?= $status_badge ?>"><?= ucfirst($item_status) ?></span></td>
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
