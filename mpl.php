<?php
session_start();
require_once 'db_connect.php';
require_once 'auth.php';
require_login();

$message = '';
$username = $_SESSION['username'] ?? 'U';
$user_id  = $_SESSION['user_id']  ?? 1;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'confirm') {
        $id = (int) $_POST['id'];
        
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Get MPL details
            $mpl_result = $mysqli->query("SELECT * FROM packing_list WHERE id = $id");
            $mpl = $mpl_result->fetch_assoc();
            
            if (!$mpl) {
                throw new Exception('MPL not found');
            }
            
            // Get all items in this MPL
            $items_result = $mysqli->query("
                SELECT pli.*, s.sku
                FROM packing_list_items pli
                JOIN sku s ON pli.sku_id = s.id
                WHERE pli.mpl_id = $id
            ");
            $items = $items_result->fetch_all(MYSQLI_ASSOC);
            
            // Add quantities to inventory
            foreach ($items as $item) {
                $sku = $mysqli->real_escape_string($item['sku']);
                $qty = (int)$item['quantity'];
                
                // Check if inventory record exists
                $inv_check = $mysqli->query("SELECT id, quantity_available FROM inventory WHERE sku = '$sku'");
                
                if ($inv_check && $inv_check->num_rows > 0) {
                    // Update existing inventory
                    $mysqli->query("UPDATE inventory 
                                   SET quantity_available = quantity_available + $qty,
                                       last_updated = NOW()
                                   WHERE sku = '$sku'");
                } else {
                    // Create new inventory record
                    $mysqli->query("INSERT INTO inventory (sku, quantity_available, quantity_reserved, last_updated)
                                   VALUES ('$sku', $qty, 0, NOW())");
                }
            }
            
            // Update MPL status to confirmed
            $mysqli->query("UPDATE packing_list 
                           SET status = 'confirmed',
                               confirmed_at = NOW(),
                               confirmed_by_user_id = $user_id
                           WHERE id = $id");
            
            // Send confirmation callback to CMS
            $callback_data = [
                'action' => 'confirm',
                'reference_number' => $mpl['mpl_number']
            ];
            
            // TODO: Replace with actual CMS callback URL
            $cms_callback_url = 'https://cms.example.com/api/v1/mpls.php';
            send_cms_callback($cms_callback_url, $callback_data);
            
            $mysqli->commit();
            $message = '✅ MPL confirmed and inventory updated! Callback sent to CMS.';
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '❌ Error confirming MPL: ' . $e->getMessage();
        }
        
    } elseif ($_POST['action'] === 'delete') {
        $id = (int) $_POST['id'];
        $mysqli->query("DELETE FROM packing_list_items WHERE mpl_id = $id");
        $ok = $mysqli->query("DELETE FROM packing_list WHERE id = $id");
        $message = $ok ? '✅ Packing list deleted!' : '❌ Error: ' . $mysqli->error;
    }
}

// Helper function to send callback to CMS
function send_cms_callback($url, $data) {
    // Load API key from .env
    $env = parse_ini_file(__DIR__ . '/.env');
    $api_key = $env['X-API-KEY'] ?? '';
    
    // Build HTTP context
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'X-API-Key: ' . $api_key
            ],
            'content' => json_encode($data),
            'ignore_errors' => true
        ]
    ]);
    
    // Send request
    $response = file_get_contents($url, false, $context);
    $http_code = isset($http_response_header[0]) ? intval(substr($http_response_header[0], 9, 3)) : 0;
    
    if ($http_code !== 200) {
        error_log("CMS callback failed: HTTP $http_code - $response");
    }
    
    return $http_code === 200;
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

             <a href="mpl.php"        class="nav-item active"><p>MPL</p></a>
            <a href="orders.php"     class="nav-item"><p>Orders</p></a>
         
           
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
            <p class="page-subtitle">Receive MPLs from CMS and confirm to update inventory</p>

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
                                    <p>No packing lists yet. MPLs will appear here when received from CMS.</p>
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
                                    <a href="mpl-items.php?mpl_id=<?= $mpl['id'] ?>" class="edit-btn">📋 View Items</a>
                                    <?php if ($status === 'pending'): ?>
                                        <button class="btn-primary" style="padding:6px 12px; font-size:13px;" onclick="confirmMPL(<?= $mpl['id'] ?>)">
                                            ✅ Confirm MPL
                                        </button>
                                    <?php endif; ?>
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

    <!-- Confirm form -->
    <form id="confirmMPLForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="confirm">
        <input type="hidden" name="id" id="confirmMPLId">
    </form>

    <!-- Delete form -->
    <form id="deleteMPLForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteMPLId">
    </form>

    <script>
        function confirmMPL(id) {
            if (confirm('Confirm this MPL? This will add all items to inventory and send a confirmation to CMS.')) {
                document.getElementById('confirmMPLId').value = id;
                document.getElementById('confirmMPLForm').submit();
            }
        }

        function deleteMPL(id) {
            if (confirm('Are you sure you want to delete this MPL?')) {
                document.getElementById('deleteMPLId').value = id;
                document.getElementById('deleteMPLForm').submit();
            }
        }
    </script>
</body>
</html>
