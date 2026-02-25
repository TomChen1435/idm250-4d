<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';
$user_id  = $_SESSION['user_id']  ?? 1;

// ── Handle form submissions ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $mpl_number = $mysqli->real_escape_string(trim($_POST['mpl_number']));
        $status     = $mysqli->real_escape_string(trim($_POST['status']));

        $ok = $mysqli->query("INSERT INTO packing_list (mpl_number, status, created_at)
                              VALUES ('$mpl_number', '$status', NOW())");
        $message = $ok ? '✅ Packing list created!' : '❌ Error: ' . $mysqli->error;

    } elseif ($_POST['action'] === 'update') {
        $id     = (int) $_POST['id'];
        $status = $mysqli->real_escape_string(trim($_POST['status']));
        
        $confirmed_clause = '';
        if ($status === 'confirmed' && $_POST['old_status'] !== 'confirmed') {
            $confirmed_clause = ", confirmed_at = NOW(), confirmed_by_user_id = $user_id";
        }

        $ok = $mysqli->query("UPDATE packing_list SET status = '$status' $confirmed_clause WHERE id = $id");
        $message = $ok ? '✅ Packing list updated!' : '❌ Error: ' . $mysqli->error;

    } elseif ($_POST['action'] === 'delete') {
        $id = (int) $_POST['id'];
        $mysqli->query("DELETE FROM packing_list_items WHERE mpl_id = $id");
        $ok = $mysqli->query("DELETE FROM packing_list WHERE id = $id");
        $message = $ok ? '✅ Packing list deleted!' : '❌ Error: ' . $mysqli->error;
    }
}

// ── Stats ─────────────────────────────────────────────
$total_mpls   = $mysqli->query("SELECT COUNT(*) c FROM packing_list")->fetch_assoc()['c'] ?? 0;
$pending      = $mysqli->query("SELECT COUNT(*) c FROM packing_list WHERE status = 'pending'")->fetch_assoc()['c'] ?? 0;
$confirmed    = $mysqli->query("SELECT COUNT(*) c FROM packing_list WHERE status = 'confirmed'")->fetch_assoc()['c'] ?? 0;

// ── Fetch packing lists ───────────────────────────────
$search        = isset($_GET['search']) ? $mysqli->real_escape_string(trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? $mysqli->real_escape_string(trim($_GET['status'])) : '';

$where_parts = [];
if ($search)        $where_parts[] = "mpl_number LIKE '%$search%'";
if ($status_filter) $where_parts[] = "status = '$status_filter'";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$result = $mysqli->query("SELECT pl.*, u.username as confirmed_by
                          FROM packing_list pl
                          LEFT JOIN users u ON pl.confirmed_by_user_id = u.id
                          $where
                          ORDER BY pl.created_at DESC
                          LIMIT 100");
$mpls = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPL - 4D WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php"      class="nav-item"><p>SKUs</p></a>
            <a href="inventory.php"  class="nav-item"><p>Current Inventory</p></a>
            <a href="orders.php"     class="nav-item"><p>Orders</p></a>
            
            <a href="shipped.php"    class="nav-item"><p>Shipped</p></a>
            <a href="mpl.php"        class="nav-item active"><p>MPL</p></a>
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
                <button class="icon-btn">🔔</button>
                <button class="icon-btn">⚙️</button>
                <div class="user-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
            </div>
        </header>

        <main class="content">
            <div class="breadcrumb">Warehouse / Master Packing List</div>
            <h1 class="page-title">Master Packing List (MPL)</h1>
            <p class="page-subtitle">Manage packing lists for warehouse operations</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row" style="grid-template-columns:repeat(3,1fr);">
                <div class="stat-card">
                    <div class="stat-label">Total MPLs</div>
                    <div class="stat-value"><?= number_format($total_mpls) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:#D97706;"><?= number_format($pending) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Confirmed</div>
                    <div class="stat-value green"><?= number_format($confirmed) ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <input type="text" class="search-input" style="max-width:340px;"
                       placeholder="Search by MPL number..."
                       value="<?= htmlspecialchars($search) ?>"
                       onchange="window.location.href='mpl.php?search='+encodeURIComponent(this.value)+'<?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>'">

                <select class="select-box"
                        onchange="window.location.href='mpl.php?status='+this.value+'<?= $search ? '&search='.urlencode($search) : '' ?>'">
                    <option value="">All Statuses</option>
                    <option value="pending"   <?= $status_filter === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>

                <button class="btn-primary" onclick="openMPLModal()" style="margin-left:auto;">
                    <span>+</span> New MPL
                </button>
            </div>

            <!-- Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>MPL #</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Confirmed At</th>
                            <th>Confirmed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mpls)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📦</div>
                                    <p>No packing lists yet. Create your first MPL above.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($mpls as $mpl):
                            $status = $mpl['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'confirmed' => 'badge-in-stock',
                                'cancelled' => 'badge-out',
                                default     => 'badge-low'
                            };
                        ?>
                        <tr>
                            <td>
                                <a href="mpl-items.php?mpl_id=<?= $mpl['id'] ?>" class="order-link">
                                    <?= htmlspecialchars($mpl['mpl_number']) ?>
                                </a>
                            </td>
                            <td><span class="status-badge <?= $badge_class ?>"><?= ucfirst($status) ?></span></td>
                            <td class="date-cell"><?= $mpl['created_at'] ? date('M d, Y', strtotime($mpl['created_at'])) : '—' ?></td>
                            <td class="date-cell"><?= $mpl['confirmed_at'] ? date('M d, Y g:i A', strtotime($mpl['confirmed_at'])) : '—' ?></td>
                            <td><?= htmlspecialchars($mpl['confirmed_by'] ?? '—') ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="mpl-items.php?mpl_id=<?= $mpl['id'] ?>" class="edit-btn">📋 Items</a>
                                    <button class="edit-btn" onclick='openEditMPL(<?= htmlspecialchars(json_encode($mpl), ENT_QUOTES) ?>)'>Edit</button>
                                    <button class="delete-btn" onclick="deleteMPL(<?= $mpl['id'] ?>)">🗑️</button>
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
    <div id="mplModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-header" id="mplModalTitle">New MPL</h2>
            <form method="POST">
                <input type="hidden" name="action"     id="mplAction" value="add">
                <input type="hidden" name="id"         id="mplId">
                <input type="hidden" name="old_status" id="mplOldStatus">

                <div class="form-group">
                    <label class="form-label">MPL Number</label>
                    <input type="text" name="mpl_number" id="mplNumber" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="mplStatus" class="form-input">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save MPL</button>
                    <button type="button" class="btn-secondary" onclick="closeMPLModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete form -->
    <form id="deleteMPLForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id"     id="deleteMPLId">
    </form>

    <script src="js/app.js"></script>
</body>
</html>
