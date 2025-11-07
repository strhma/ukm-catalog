<?php
require_once '../config/config.php';
require_once '../includes/error_handler.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$cart = new Cart();
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get cart count
        if (isset($_GET['action']) && $_GET['action'] === 'count') {
            $response = [
                'success' => true,
                'count' => $cart->getTotalItems(),
                'items' => $cart->getItems()
            ];
        }
        
        // Get cart items with details
        elseif (isset($_GET['action']) && $_GET['action'] === 'details') {
            $cartItems = $cart->getItems();
            $products = [];
            
            if (!empty($cartItems)) {
                $placeholders = str_repeat('?,', count($cartItems) - 1) . '?';
                $productIds = array_keys($cartItems);
                
                $query = "SELECT p.*, c.name as category_name 
                          FROM products p 
                          LEFT JOIN categories c ON p.category_id = c.id 
                          WHERE p.id IN ($placeholders) AND p.status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->execute($productIds);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate totals
                $totalAmount = 0;
                $cartProducts = [];
                
                foreach ($products as $product) {
                    $quantity = $cartItems[$product['id']];
                    $subtotal = $product['price'] * $quantity;
                    $totalAmount += $subtotal;
                    
                    $cartProducts[] = [
                        'product' => $product,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal
                    ];
                }
                
                $response = [
                    'success' => true,
                    'items' => $cartProducts,
                    'total' => $totalAmount,
                    'count' => array_sum($cartItems)
                ];
            } else {
                $response = [
                    'success' => true,
                    'items' => [],
                    'total' => 0,
                    'count' => 0
                ];
            }
        }
        
        else {
            $response = ['success' => false, 'message' => 'Invalid action'];
        }
    } 
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                
                if ($productId <= 0) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                // Check if product exists and has stock
                $query = "SELECT stock FROM products WHERE id = ? AND status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->execute([$productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    $response = ['success' => false, 'message' => 'Product not found or inactive'];
                    break;
                }
                
                $currentQuantity = $cart->getItems()[$productId] ?? 0;
                $newQuantity = $currentQuantity + $quantity;
                
                if ($newQuantity <= $product['stock']) {
                    $cart->add($productId, $quantity);
                    $response = [
                        'success' => true,
                        'message' => 'Product added to cart',
                        'count' => $cart->getTotalItems(),
                        'added_quantity' => $quantity,
                        'total_quantity' => $newQuantity
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'message' => 'Not enough stock available',
                        'available_stock' => $product['stock'],
                        'requested_quantity' => $newQuantity
                    ];
                }
                break;
                
            case 'update':
                $productId = intval($input['product_id'] ?? 0);
                $quantity = max(0, intval($input['quantity'] ?? 0));
                
                if ($productId <= 0) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                if ($quantity > 0) {
                    // Check stock
                    $query = "SELECT stock FROM products WHERE id = ? AND status = 'active'";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$product) {
                        $response = ['success' => false, 'message' => 'Product not found or inactive'];
                        break;
                    }
                    
                    if ($quantity <= $product['stock']) {
                        $cart->update($productId, $quantity);
                        $response = [
                            'success' => true,
                            'message' => 'Cart updated',
                            'count' => $cart->getTotalItems(),
                            'new_quantity' => $quantity
                        ];
                    } else {
                        $response = [
                            'success' => false,
                            'message' => 'Not enough stock available',
                            'available_stock' => $product['stock']
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
                break;
                
            case 'remove':
                $productId = intval($input['product_id'] ?? 0);
                
                if ($productId <= 0) {
                    $response = ['success' => false, 'message' => 'Invalid product ID'];
                    break;
                }
                
                $cart->remove($productId);
                $response = [
                    'success' => true,
                    'message' => 'Product removed from cart',
                    'count' => $cart->getTotalItems()
                ];
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
} catch (Exception $e) {
    // Log error
    error_log("Cart API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    $response = [
        'success' => false, 
        'message' => 'An error occurred while processing your request',
        'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : null
    ];
}

// Ensure proper JSON output
if (!headers_sent()) {
    header('Content-Type: application/json');
}

echo json_encode($response);
?>