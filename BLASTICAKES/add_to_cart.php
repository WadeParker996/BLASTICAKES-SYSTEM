<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';

// Get user ID
$user_id = $_SESSION['user_id'];

// Check if product ID is provided from POST
if (!isset($_POST['product_id'])) {
    header("Location: products.php");
    exit;
}

$product_id = $_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$return_url = isset($_POST['return_url']) ? $_POST['return_url'] : 'cart.php';

// Validate quantity
if ($quantity < 1) {
    $quantity = 1;
}

// Check if product exists and get stock information
$sql = "SELECT * FROM products WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 1) {
    $product = mysqli_fetch_assoc($result);
    $available_stock = $product['stock'];
    
    // Check if product is already in user's cart
    $check_cart_sql = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_cart_sql);
    mysqli_stmt_bind_param($check_stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($check_stmt);
    $cart_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($cart_result) > 0) {
        // Product already in cart, update quantity
        $cart_item = mysqli_fetch_assoc($cart_result);
        $current_quantity = $cart_item['quantity'];
        $new_quantity = $current_quantity + $quantity;
        
        // Check if new quantity exceeds available stock
        if ($new_quantity > $available_stock) {
            // Set quantity to maximum available stock
            $new_quantity = $available_stock;
            $stock_warning = true;
        }
        
        // Update cart quantity
        $update_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "iii", $new_quantity, $user_id, $product_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $success = true;
        } else {
            $error = "Failed to update cart. Please try again.";
        }
        mysqli_stmt_close($update_stmt);
    } else {
        // Check if requested quantity exceeds available stock
        if ($quantity > $available_stock) {
            $quantity = $available_stock;
            $stock_warning = true;
        }
        
        // Add new product to cart - FIXED: Removed added_at column
        $insert_sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);
        mysqli_stmt_bind_param($insert_stmt, "iii", $user_id, $product_id, $quantity);
        
        if (mysqli_stmt_execute($insert_stmt)) {
            $success = true;
        } else {
            $error = "Failed to add product to cart. Please try again.";
        }
        mysqli_stmt_close($insert_stmt);
    }
    
    mysqli_stmt_close($check_stmt);
    
    // Redirect with appropriate message
    if (isset($stock_warning) && $stock_warning) {
        header("Location: " . $return_url . "?stock_warning=1&product=" . urlencode($product['name']));
        exit;
    } elseif (isset($success) && $success) {
        // Redirect back to the product page with success message
        if (strpos($return_url, 'product_details.php') !== false) {
            header("Location: " . $return_url . "&added=1");
        } else {
            header("Location: " . $return_url . "?added=1");
        }
        exit;
    } elseif (isset($error)) {
        header("Location: product_details.php?id=" . $product_id . "&error=" . urlencode($error));
        exit;
    }
} else {
    // Product not found
    header("Location: products.php?error=product_not_found");
    exit;
}

// Close database connection
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
