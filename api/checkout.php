<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => 'Invalid request'];

// Check authentication
$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit();
    }

    // Initialize cart
    $cart = new Cart();
    $cartItems = $cart->getItems();

    // Validation
    $errors = [];
    
    // Validate inputs
    $customerName = isset($input['customer_name']) ? sanitizeInput($input['customer_name']) : '';
    $customerEmail = isset($input['customer_email']) ? sanitizeInput($input['customer_email']) : '';
    $customerPhone = isset($input['customer_phone']) ? sanitizeInput($input['customer_phone']) : '';
    $shippingAddress = isset($input['shipping_address']) ? sanitizeInput($input['shipping_address']) : '';
    $paymentMethod = isset($input['payment_method']) ? sanitizeInput($input['payment_method']) : '';
    $notes = isset($input['notes']) ? sanitizeInput($input['notes']) : '';
    
    // Shipping Data
    $shippingCost = isset($input['shipping_cost']) ? floatval($input['shipping_cost']) : 0;
    $courier = isset($input['courier']) ? sanitizeInput($input['courier']) : '';
    $shippingService = isset($input['shipping_service']) ? sanitizeInput($input['shipping_service']) : '';

    if (empty($customerName)) $errors[] = 'Nama lengkap diperlukan';
    if (empty($customerEmail) || !validateEmail($customerEmail)) $errors[] = 'Email valid diperlukan';
    if (empty($customerPhone) || !validatePhone($customerPhone)) $errors[] = 'Nomor telepon valid diperlukan (10-13 digit)';
    if (empty($shippingAddress)) $errors[] = 'Alamat pengiriman diperlukan';
    if (empty($paymentMethod)) $errors[] = 'Metode pembayaran diperlukan';
    
    // Validate cart
    if (empty($cartItems)) {
        $errors[] = 'Keranjang belanja kosong';
    }

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Get product details and validate stock
            $productIds = array_keys($cartItems);
            $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
            
            $query = "SELECT id, name, price, stock FROM products WHERE id IN ($placeholders) AND status = 'active'";
            $stmt = $db->prepare($query);
            $stmt->execute($productIds);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Validate stock and calculate total
            // Validate stock and calculate total
            $totalAmount = 0;
            $totalWeight = 0; // for verification if needed
            $orderItems = [];
            
            $productMap = [];
            foreach ($products as $p) {
                $productMap[$p['id']] = $p;
            }
            
            foreach ($cartItems as $pid => $qty) {
                if (!isset($productMap[$pid])) continue; // Should not happen if foreign keys are correct or logic is tight
                
                $product = $productMap[$pid];
                
                if ($qty > $product['stock']) {
                    throw new Exception("Stok tidak cukup untuk produk: {$product['name']}");
                }
                
                $subtotal = $product['price'] * $qty;
                $totalAmount += $subtotal;
                
                $orderItems[] = [
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'price' => $product['price'],
                    'subtotal' => $subtotal
                ];
                // $totalWeight += ($product['weight'] ?? 1000) * $qty;
                
                $orderItems[] = [
                    'product_id' => $product['id'],
                    'quantity' => $qty,
                    'price' => $product['price'],
                    'subtotal' => $subtotal
                ];
            }
            
            // Add Shipping Cost to Total
            $totalAmount += $shippingCost;
            
            // Create customer record (linked to user)
            $query = "INSERT INTO customers (user_id, name, email, phone, address, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                $customerName,
                $customerEmail,
                $customerPhone,
                $shippingAddress
            ]);
            $customerId = $db->lastInsertId();
            
            // Generate order number
            $orderNumber = 'ORD' . date('YmdHis') . str_pad($customerId, 4, '0', STR_PAD_LEFT);
            
            // Create order
            $query = "INSERT INTO orders (customer_id, order_number, total_amount, status, shipping_address, notes, shipping_cost, courier, shipping_service, created_at) 
                      VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $customerId,
                $orderNumber,
                $totalAmount,
                $shippingAddress,
                $shippingAddress,
                $notes,
                $shippingCost,
                $courier,
                $shippingService
            ]);
            $orderId = $db->lastInsertId();
            
            // Create order items and update stock
            foreach ($orderItems as $item) {
                // Insert order item
                $query = "INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
                ]);
                
                // Update stock
                $query = "UPDATE products SET stock = stock - ? WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear cart
            $cart->clear();
            
            // Log activity
            $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                      VALUES (?, 'order_created', ?, ?, ?, NOW())";
            $stmt = $db->prepare($query);
            $stmt->execute([
                $_SESSION['user_id'],
                "Order #{$orderNumber} created via API with total " . formatRupiah($totalAmount),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
            $db->commit();
            
            // Success response
            $response = [
                'success' => true,
                'message' => "Pesanan #{$orderNumber} berhasil dibuat!",
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount
            ];
            
        } catch (Exception $e) {
            $db->rollBack();
            http_response_code(500);
            $response = ['success' => false, 'message' => 'Gagal membuat pesanan: ' . $e->getMessage()];
        }
    } else {
        http_response_code(400);
        $response = ['success' => false, 'message' => 'Validasi gagal', 'errors' => $errors];
    }
} else {
    http_response_code(405);
    $response = ['success' => false, 'message' => 'Method Not Allowed'];
}

echo json_encode($response);
?>
