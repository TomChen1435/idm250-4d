<?php
session_start();
require_once 'db_connect.php';



// Stats
$total_row  = $mysqli->query("SELECT SUM(quantity_available) AS total FROM inventory")->fetch_assoc();
$in_stock   = $mysqli->query("SELECT SUM(quantity_available) AS total FROM inventory WHERE quantity_available > 0")->fetch_assoc();
$total_units = (int)($total_row['total'] ?? 0);
$in_stock_units = (int)($in_stock['total'] ?? 0);

// ── Fetch inventory joined with sku table ─────────────
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

// ── UOM options for filter ────────────────────────────
$uom_result = $mysqli->query("SELECT DISTINCT uom FROM sku WHERE uom IS NOT NULL ORDER BY uom");
$uoms = $uom_result ? $uom_result->fetch_all(MYSQLI_ASSOC) : [];

// ── SKU list for dropdown in modal ───────────────────
$sku_result = $mysqli->query("SELECT sku, description FROM sku ORDER BY sku ASC");
$sku_list = $sku_result ? $sku_result->fetch_all(MYSQLI_ASSOC) : [];
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
            <a href="orders.php" class="nav-item">
                <p>Orders</p>
            </a>

             <a href="order-items.php" class="nav-item">
                <p>Order Items</p>
            </a>
            
            <a href="shipped.php" class="nav-item">
                <p>Shipped</p>
            </a>
        </nav>
        <div class="logout">
            <a href="#" class="logout-btn">
                <p>Logout</p>
            </a>
        </div>
    </div>

    <!-- Main Content -->
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
            <div class="breadcrumb">Warehouse / Current Inventory</div>
            <h1 class="page-title">Current Inventory Levels</h1>
            <p class="page-subtitle">Real-time view of stock levels across all warehouse locations</p>

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

                <button class="btn-primary" onclick="openInvAddModal()" style="margin-left:auto;">
                    + Add Inventory
                </button>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📭</div>
                                    <p>No inventory records found.<br>Add your first entry above.</p>
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
                            <td>
                                <button class="edit-btn" onclick='editRow(<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>)'>
                                    Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer">© 2025 4D Warehouse Management System</footer>
    </div>

    <!-- Modal -->
    <div id="invModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="modalTitle">Add Inventory Record</h2>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="rowId">

                <div class="form-group" id="skuSelectGroup">
                    <label class="form-label">SKU</label>
                    <select name="sku" id="skuSelect" class="form-input" style="width:100%;">
                        <option value="">— Select a SKU —</option>
                        <?php foreach ($sku_list as $s): ?>
                            <option value="<?= htmlspecialchars($s['sku']) ?>">
                                <?= htmlspecialchars($s['sku']) ?> — <?= htmlspecialchars($s['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="skuTextGroup" style="display:none;">
                    <label class="form-label">SKU</label>
                    <input type="text" id="skuDisplay" class="form-input" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity Available</label>
                    <input type="number" name="quantity_available" id="qty_available" class="form-input" value="0" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantity Reserved</label>
                    <input type="number" name="quantity_reserved" id="qty_reserved" class="form-input" value="0" min="0">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
