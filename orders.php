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

    if ($_POST['action'] === 'ship') {
        $id = (int) $_POST['id'];
        
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Get order details
            $order_result = $mysqli->query("SELECT * FROM orders WHERE id = $id");
            $order = $order_result->fetch_assoc();
            
            if (!$order) {
                throw new Exception('Order not found');
            }
            
            // Get all items in this order
            $items_result = $mysqli->query("
                SELECT oi.*, s.sku
                FROM order_items oi
                JOIN sku s ON oi.sku_id = s.id
                WHERE oi.order_id = $id
            ");
            $items = $items_result->fetch_all(MYSQLI_ASSOC);
            
            // Remove quantities from inventory
            foreach ($items as $item) {
                $sku = $mysqli->real_escape_string($item['sku']);
                $qty = (int)$item['quantity'];
                
                // Check if enough inventory available
                $inv_check = $mysqli->query("SELECT quantity_available FROM inventory WHERE sku = '$sku'");
                
                if (!$inv_check || $inv_check->num_rows === 0) {
                    throw new Exception("SKU $sku not found in inventory");
                }
                
                $inv_row = $inv_check->fetch_assoc();
                if ($inv_row['quantity_available'] < $qty) {
                    throw new Exception("Insufficient inventory for SKU $sku (need $qty, have {$inv_row['quantity_available']})");
                }
                
                // Deduct from inventory
                $mysqli->query("UPDATE inventory 
                               SET quantity_available = quantity_available - $qty,
                                   last_updated = NOW()
                               WHERE sku = '$sku'");
            }
            
            // Update order status to shipped
            $mysqli->query("UPDATE orders 
                           SET status = 'shipped',
                               time_shipped = NOW()
                           WHERE id = $id");
            
            // Add to shipped history
            foreach ($items as $item) {
                $sku = $mysqli->real_escape_string($item['sku']);
                $qty = (int)$item['quantity'];
                $order_number = $mysqli->real_escape_string($order['order_number']);
                $customer = $mysqli->real_escape_string($order['customer_name']);
                
                $mysqli->query("INSERT INTO shipped_items (order_id, order_number, sku, quantity, customer_name, shipped_at)
                               VALUES ($id, '$order_number', '$sku', $qty, '$customer', NOW())");
            }
            
            // Send shipment callback to CMS
            $callback_data = [
                'action' => 'ship',
                'order_number' => $order['order_number'],
                'shipped_at' => date('Y-m-d')
            ];
            
            // TODO: Replace with actual CMS callback URL
            $cms_callback_url = 'https://cms.example.com/api/v1/orders.php';
            send_cms_callback($cms_callback_url, $callback_data);
            
            $mysqli->commit();
            $message = '✅ Order shipped! Inventory updated and callback sent to CMS.';
            
        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '❌ Error shipping order: ' . $e->getMessage();
        }
        
    } elseif ($_POST['action'] === 'delete') {
        $id = (int) $_POST['id'];
        $mysqli->query("DELETE FROM order_items WHERE order_id = $id");
        $ok = $mysqli->query("DELETE FROM orders WHERE id = $id");
        $message = $ok ? '✅ Order deleted!' : '❌ Error: ' . $mysqli->error;
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

// Stats
$total_orders = $mysqli->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'] ?? 0;
$total_items = $mysqli->query("SELECT COUNT(*) AS total FROM order_items")->fetch_assoc()['total'] ?? 0;
$pending_count = $mysqli->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0;
$shipped_count = $mysqli->query("SELECT COUNT(*) AS total FROM orders WHERE status = 'shipped'")->fetch_assoc()['total'] ?? 0;

// Filters
$search = isset($_GET['search']) ? $mysqli->real_escape_string(trim($_GET['search'])) : '';
$status_filter = isset($_GET['status']) ? $mysqli->real_escape_string(trim($_GET['status'])) : '';

$where_parts = [];
if ($search) $where_parts[] = "(order_number LIKE '%$search%' OR customer_name LIKE '%$search%' OR address LIKE '%$search%')";
if ($status_filter) $where_parts[] = "status = '$status_filter'";
$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Fetch orders with item count
$result = $mysqli->query("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS total_items
    FROM orders o
    $where
    ORDER BY o.time_created DESC
    LIMIT 100
");
$orders = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - 4D Warehouse</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div class="sidebar">
        <div class="logo">4D WMS</div>
        <nav class="nav">
            <a href="index.php" class="nav-item">
                <p>SKUs</p>
            </a>
            <a href="inventory.php" class="nav-item">
                <p>Current Inventory</p>
            </a>

            <a href="mpl.php" class="nav-item">
                <p>MPL</p>
            </a>

            <a href="orders.php" class="nav-item active">
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
            <div class="breadcrumb">Warehouse / Orders</div>
            <h1 class="page-title">Order Management</h1>
            <p class="page-subtitle">Receive orders from CMS and ship to update inventory</p>

            <?php if ($message): ?>
                <div class="message <?= str_contains($message, '✅') ? 'success' : 'error' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row" style="grid-template-columns:repeat(4,1fr);">
                <div class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?= number_format($total_orders) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Items</div>
                    <div class="stat-value"><?= number_format($total_items) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value" style="color:#D97706;"><?= number_format($pending_count) ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Shipped</div>
                    <div class="stat-value green"><?= number_format($shipped_count) ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <input type="text" class="search-input" style="max-width:340px;"
                       placeholder="Search by order #, customer, or address..."
                       value="<?= htmlspecialchars($search) ?>"
                       onchange="window.location.href='orders.php?search='+encodeURIComponent(this.value)+'<?= $status_filter ? '&status='.urlencode($status_filter) : '' ?>'">

                <select class="select-box"
                        onchange="window.location.href='orders.php?status='+this.value+'<?= $search ? '&search='.urlencode($search) : '' ?>'">
                    <option value="">All Statuses</option>
                    <option value="pending"   <?= $status_filter === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="processing"<?= $status_filter === 'processing'? 'selected' : '' ?>>Processing</option>
                    <option value="shipped"   <?= $status_filter === 'shipped'   ? 'selected' : '' ?>>Shipped</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>

            <!-- Table -->
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Shipped</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div style="font-size:48px;">📋</div>
                                    <p>No orders yet. Orders will appear here when received from CMS.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o):
                            $status = $o['status'] ?? 'pending';
                            $badge_class = match($status) {
                                'shipped'    => 'badge-in-stock',
                                'processing' => 'badge-processing',
                                'cancelled'  => 'badge-out',
                                default      => 'badge-low'
                            };
                        ?>
                        <tr>
                            <td>
                                <a href="order-items.php?order_id=<?= $o['id'] ?>" class="order-link">
                                    <?= htmlspecialchars($o['order_number']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($o['customer_name']) ?></td>
                            <td class="description"><?= htmlspecialchars($o['address'] ?? '—') ?></td>
                            <td><span class="qty"><?= (int)$o['total_items'] ?></span></td>
                            <td><span class="status-badge <?= $badge_class ?>"><?= ucfirst($status) ?></span></td>
                            <td class="date-cell"><?= $o['time_created'] ? date('M d, Y', strtotime($o['time_created'])) : '—' ?></td>
                            <td class="date-cell"><?= $o['time_shipped'] ? date('M d, Y', strtotime($o['time_shipped'])) : '—' ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="order-items.php?order_id=<?= $o['id'] ?>" class="edit-btn">🧾 View Items</a>
                                    <?php if ($status === 'pending' || $status === 'processing'): ?>
                                        <button class="btn-primary" style="padding:6px 12px; font-size:13px;" onclick="shipOrder(<?= $o['id'] ?>)">
                                            📦 Ship Order
                                        </button>
                                    <?php endif; ?>
                                    <button class="delete-btn" onclick="deleteOrder(<?= $o['id'] ?>)">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer">© 2026 4D Warehouse System</footer>
    </div>

    <!-- Ship form -->
    <form id="shipOrderForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="ship">
        <input type="hidden" name="id" id="shipOrderId">
    </form>

    <!-- Delete form -->
    <form id="deleteOrderForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteOrderId">
    </form>

    <script>
        function shipOrder(id) {
            if (confirm('Ship this order? This will deduct items from inventory and send a shipment callback to CMS.')) {
                document.getElementById('shipOrderId').value = id;
                document.getElementById('shipOrderForm').submit();
            }
        }

        function deleteOrder(id) {
            if (confirm('Are you sure you want to delete this order?')) {
                document.getElementById('deleteOrderId').value = id;
                document.getElementById('deleteOrderForm').submit();
            }
        }
    </script>
</body>
</html>
