<?php
require_once '../config/config.php';

header('Content-Type: application/json');

$cart = new Cart();
$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get cart count
    if (isset($_GET['action']) && $_GET['action'] === 'count') {
        $response = [
            'success' => true,
            'count' => $cart->getTotalItems()
        ];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $response = ['success' => false, 'message' => 'Invalid JSON data'];
        echo json_encode($response);
        exit();
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $productId = intval($input['product_id'] ?? 0);
            $quantity = max(1, intval($input['quantity'] ?? 1));
            
            if ($productId > 0) {
                // Check if product exists and has stock
                $query = "SELECT stock FROM products WHERE id = ? AND status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product) {
                    $currentQuantity = $cart->getItems()[$productId] ?? 0;
                    $newQuantity = $currentQuantity + $quantity;
                    
                    if ($newQuantity <= $product['stock']) {
                        $cart->add($productId, $quantity);
                        $response = [
                            'success' => true,
                            'message' => 'Product added to cart',
                            'count' => $cart->getTotalItems()
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Not enough stock available'
                        ];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Product not found'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
            }
            break;
            
        case 'update':
            $productId = intval($input['product_id'] ?? 0);
            $quantity = max(0, intval($input['quantity'] ?? 0));
            
            if ($productId > 0) {
                if ($quantity > 0) {
                    // Check stock
                    $query = "SELECT stock FROM products WHERE id = ? AND status = 'active'";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($product && $quantity <= $product['stock']) {
                        $cart->update($productId, $quantity);
                        $response = [
                            'success' => true,
                            'message' => 'Cart updated',
                            'count' => $cart->getTotalItems()
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Not enough stock available'
                        ];
                    }
                } else {
                    $cart->remove($productId);
                    $response = [
                        'success' => true,
                        'message' => 'Product removed from cart',
                        'count' => $cart->getTotalItems()
                    ];
                }
            } else {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
            }
            break;
            
        case 'remove':
            $productId = intval($input['product_id'] ?? 0);
            
            if ($productId > 0) {
                $cart->remove($productId);
                $response = [
                    'success' => true,
                    'message' => 'Product removed from cart',
                    'count' => $cart->getTotalItems()
                ];
            } else {
                $response = ['success' => false, 'message' => 'Invalid product ID'];
            }
            break;
            
        case 'clear':
            $cart->clear();
            $response = [
                'success' => true,
                'message' => 'Cart cleared',
                'count' => 0
            ];
            break;
            
        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
    }
} else {
    $response = ['success' => false, 'message' => 'Method not allowed'];
}

echo json_encode($response);
?>