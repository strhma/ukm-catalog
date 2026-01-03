<?php
require_once '../config/config.php';

$auth = new Auth($db);
$auth->requireLogin();
$auth->requireRole('admin');

$pageTitle = 'Manage Orders';

// Handle actions (Update Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid request');
        header('Location: orders.php');
        exit();
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $orderId = intval($_POST['order_id']);
        $newStatus = sanitizeInput($_POST['status']);
        
        $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        
        if (in_array($newStatus, $allowedStatuses)) {
            $query = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$newStatus, $orderId])) {
                // Log activity
                $userId = $_SESSION['user_id'];
                $logQuery = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'update_order', ?, NOW())";
                $logStmt = $db->prepare($logQuery);
                $logStmt->execute([$userId, "Updated order #{$orderId} status to {$newStatus}"]);
                
                setFlashMessage('success', 'Order status updated successfully');
            } else {
                setFlashMessage('error', 'Failed to update order status');
            }
        } else {
            setFlashMessage('error', 'Invalid status');
        }
    }
    
    header('Location: orders.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filter
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$whereConditions = ['1=1'];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 'o.status = ?';
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = '(o.order_number LIKE ? OR c.name LIKE ? OR c.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total 
               FROM orders o 
               JOIN customers c ON o.customer_id = c.id 
               WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalOrders / $limit);

// Get orders
$query = "SELECT o.*, c.name as customer_name, c.email as customer_email 
          FROM orders o 
          JOIN customers c ON o.customer_id = c.id 
          WHERE $whereClause 
          ORDER BY o.created_at DESC 
          LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/style.css">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Orders</h1>
        <div class="d-flex gap-2">
            <a href="export.php?type=orders" class="btn btn-secondary">📥 Export Orders</a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filter -->
    <div class="form-container mb-4">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1">
                <label for="search">Search Orders</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Order #, Customer Name, or Email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <div>
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="orders.php" class="btn btn-warning">Reset</a>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Shipping</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No orders found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($order['created_at'])); ?></td>
                            <td><?php echo formatRupiah($order['total_amount']); ?></td>
                            <td>
                                <?php if ($order['shipping_cost'] > 0): ?>
                                    <small><?php echo formatRupiah($order['shipping_cost']); ?></small><br>
                                    <span class="badge badge-secondary" style="font-size: 0.7rem;"><?php echo strtoupper($order['courier'] ?? '-'); ?></span>
                                <?php else: ?>
                                    <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $statusColors = [
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                ?>
                                <span class="badge badge-<?php echo $statusColors[$order['status']]; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-flex align-items-center gap-1">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        
                                        <select name="status" class="form-control form-control-sm" style="width: auto;" onchange="if(confirm('Change status to ' + this.value + '?')) this.form.submit()">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                            <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                    
                                    <!-- Optional: Link to Detail (Currently using same Detail page as User, or create separate Admin Detail) -->
                                    <a href="../order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info text-white" target="_blank">View</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="text-center mt-4">
            <?php
            $baseUrl = 'orders.php?';
            $paramsArray = [];
            if ($search) $paramsArray[] = 'search=' . urlencode($search);
            if ($status !== 'all') $paramsArray[] = 'status=' . $status;
            
            $queryString = implode('&', $paramsArray);
            if ($queryString) {
                $baseUrl .= $queryString . '&';
            }
            
            echo paginate($totalOrders, $limit, $page, $baseUrl);
            ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
