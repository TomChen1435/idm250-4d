<?php
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';

// ── Handle Add / Update / Delete ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add') {
            $ficha       = (int)   $_POST['ficha'];
            $sku         = $mysqli->real_escape_string(trim($_POST['sku']));
            $description = $mysqli->real_escape_string(trim($_POST['description']));
            $uom         = $mysqli->real_escape_string(trim($_POST['uom']));
            $pieces      = (int)   $_POST['pieces'];
            $length      = (float) $_POST['length'];
            $width       = (float) $_POST['width'];
            $height      = (float) $_POST['height'];
            $weight      = (float) $_POST['weight'];

            $ok = $mysqli->query("INSERT INTO sku (ficha, sku, description, uom, pieces, length, width, height, weight)
                                  VALUES ($ficha, '$sku', '$description', '$uom', $pieces, $length, $width, $height, $weight)");
            $message = $ok ? 'SKU added successfully!' : 'Error: ' . $mysqli->error;

        } elseif ($_POST['action'] === 'update') {
            $id          = (int)   $_POST['id'];
            $ficha       = (int)   $_POST['ficha'];
            $sku         = $mysqli->real_escape_string(trim($_POST['sku']));
            $description = $mysqli->real_escape_string(trim($_POST['description']));
            $uom         = $mysqli->real_escape_string(trim($_POST['uom']));
            $pieces      = (int)   $_POST['pieces'];
            $length      = (float) $_POST['length'];
            $width       = (float) $_POST['width'];
            $height      = (float) $_POST['height'];
            $weight      = (float) $_POST['weight'];

            $ok = $mysqli->query("UPDATE sku SET
                                  ficha='$ficha', sku='$sku', description='$description',
                                  uom='$uom', pieces=$pieces, length=$length,
                                  width=$width, height=$height, weight=$weight
                                  WHERE id=$id");
            $message = $ok ? 'SKU updated successfully!' : 'Error: ' . $mysqli->error;

        } elseif ($_POST['action'] === 'delete') {
            $id = (int) $_POST['id'];
            $ok = $mysqli->query("DELETE FROM sku WHERE id = $id");
            $message = $ok ? 'SKU deleted successfully!' : 'Error: ' . $mysqli->error;
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}

// ── Fetch SKUs ────────────────────────────────────────
$search = isset($_GET['search']) ? $mysqli->real_escape_string(trim($_GET['search'])) : '';
$where  = $search ? "WHERE sku LIKE '%$search%' OR description LIKE '%$search%' OR ficha LIKE '%$search%'" : '';
$result = $mysqli->query("SELECT * FROM sku $where ORDER BY id ASC LIMIT 50");
$skus   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKU Management - 4D WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item active">
                <p>SKUs</p>
            </a>
            <a href="inventory.php"  class="nav-item" >
                <p>Current Inventory</p>
            </a>
            <a href="orders.php" class="nav-item">
                <p>Orders</p>
            </a>
           
            <a href="shipped.php" class="nav-item">
                <p>Shipped</p>
            </a>
            <a href="mpl.php" class="nav-item">
                <p>MPL</p>
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
                <button class="icon-btn">🔔</button>
                <button class="icon-btn">⚙️</button>
                <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'], 0, 1)) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">Warehouse / SKU Management</div>
            <h1 class="page-title">SKU Management</h1>
            <p class="page-subtitle">Manage and edit product SKUs in your warehouse</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="search-bar">
                <input type="text" class="search-input"
                       placeholder="Search by SKU, description, or ficha..."
                       value="<?= htmlspecialchars($search) ?>"
                       onchange="window.location.href='index.php?search=' + encodeURIComponent(this.value)">
                <button class="btn-primary" onclick="openAddModal()">
                    <span>+</span> Add New SKU
                </button>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ficha</th>
                            <th>SKU</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Pieces</th>
                            <th>L × W × H</th>
                            <th>Weight</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($skus)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:#9CA3AF; padding:40px;">
                                No SKUs found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($skus as $s): ?>
                        <tr>
                            <td><span class="sku-id"><?= htmlspecialchars($s['ficha']) ?></span></td>
                            <td><span class="sku-id"><?= htmlspecialchars($s['sku']) ?></span></td>
                            <td><span class="description"><?= htmlspecialchars($s['description']) ?></span></td>
                            <td><?= htmlspecialchars($s['uom']) ?></td>
                            <td><?= htmlspecialchars($s['pieces']) ?></td>
                            <td class="dimension"><?= $s['length'] ?> × <?= $s['width'] ?> × <?= $s['height'] ?></td>
                            <td><?= number_format($s['weight'], 2) ?></td>
                            <td>
                                <div class="action-group">
                                    <button class="edit-btn" onclick='editSKU(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)'>
                                        Edit
                                    </button>
                                    <button class="delete-btn" onclick="deleteSKU(<?= $s['id'] ?>)">
                                        Delete
                                    </button>
                                </div>
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

    <!-- Modal -->
    <div id="skuModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="modalTitle">Add New SKU</h2>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="skuId">

                <div class="form-group">
                    <label class="form-label">Ficha #</label>
                    <input type="number" name="ficha" id="ficha" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" id="sku" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="description" class="form-textarea"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">UOM</label>
                    <input type="text" name="uom" id="uom" class="form-input" placeholder="e.g. PALLET, BUNDLE">
                </div>
                <div class="form-group">
                    <label class="form-label">Pieces</label>
                    <input type="number" name="pieces" id="pieces" class="form-input">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Length</label>
                        <input type="number" step="0.01" name="length" id="length" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Width</label>
                        <input type="number" step="0.01" name="width" id="width" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height</label>
                        <input type="number" step="0.01" name="height" id="height" class="form-input">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Weight</label>
                    <input type="number" step="0.01" name="weight" id="weight" class="form-input">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save SKU</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete form -->
    <form id="deleteSKUForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id"     id="deleteSKUId">
    </form>

    <script src="js/app.js"></script>
</body>
</html>
