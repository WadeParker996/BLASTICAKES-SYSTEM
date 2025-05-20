<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';  // This is where the database connection is located

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Initialize variables
$cart_items = [];
$total = 0;
$order_id = null;
$qr_code_url = '';
$payment_methods = ['GCash', 'PayMaya', 'Bank Transfer', 'Cash on Delivery'];
$selected_payment = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
$selected_fulfillment = isset($_POST['fulfillment_option']) ? $_POST['fulfillment_option'] : '';
$errors = [];
$success = false;

// Check if cart exists and has items
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

$cart_items = $_SESSION['cart'];

// Verify stock availability before proceeding
$stock_error = false;
$out_of_stock_items = [];
foreach ($cart_items as $id => $item) {
    // Get current stock from database
    $stock_query = "SELECT stock FROM products WHERE id = ?";
    $stmt = mysqli_prepare($conn, $stock_query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $stock_result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($stock_result);
    
    // Check if requested quantity exceeds available stock
    if ($item['quantity'] > $product['stock']) {
        $stock_error = true;
        $out_of_stock_items[] = [
            'name' => $item['name'],
            'requested' => $item['quantity'],
            'available' => $product['stock']
        ];
    }
}

// If stock issues are detected, redirect back to cart
if ($stock_error) {
    $_SESSION['stock_error'] = true;
    $_SESSION['out_of_stock_items'] = $out_of_stock_items;
    header("Location: cart.php");
    exit;
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Process checkout form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Validate form
    if (empty($_POST['full_name'])) {
        $errors[] = 'Full name is required';
    }
    if (empty($_POST['phone'])) {
        $errors[] = 'Phone number is required';
    }
    if (empty($_POST['fulfillment_option'])) {
        $errors[] = 'Please select a fulfillment option (Walk-in pickup or Delivery)';
    }
    // If delivery is selected, validate address fields
    if ($_POST['fulfillment_option'] === 'delivery') {
        if (empty($_POST['email'])) {
            $errors[] = 'Email is required for delivery';
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
            
        if (empty($_POST['address'])) {
            $errors[] = 'Address is required for delivery';
        }
    }
    if (empty($_POST['payment_method'])) {
        $errors[] = 'Payment method is required';
    }
    
    // If no errors, process the order
    if (empty($errors)) {
        // Start transaction
        mysqli_begin_transaction($conn);
            
        try {
            // Create order
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $phone = mysqli_real_escape_string($conn, $_POST['phone']);
            $fulfillment_option = mysqli_real_escape_string($conn, $_POST['fulfillment_option']);
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            
            // Set optional fields based on fulfillment option
            $email = ($fulfillment_option === 'delivery' && isset($_POST['email'])) ? 
                mysqli_real_escape_string($conn, $_POST['email']) : '';
            $address = ($fulfillment_option === 'delivery' && isset($_POST['address'])) ? 
                mysqli_real_escape_string($conn, $_POST['address']) : '';
            
            // Check if orders table exists
            $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'orders'");
            
            if (mysqli_num_rows($table_exists) == 0) {
                // Create orders table if it doesn't exist
                $create_orders_table = "CREATE TABLE orders (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    user_id INT(11) NOT NULL,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    address TEXT NOT NULL,
                    phone VARCHAR(20) NOT NULL,
                    payment_method VARCHAR(50) NOT NULL,
                    fulfillment_option VARCHAR(20) NOT NULL,
                    total_amount DECIMAL(10,2) NOT NULL,
                    status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )";
                mysqli_query($conn, $create_orders_table);
            } else {
                // Check if the required columns exist in the orders table
                $columns_result = mysqli_query($conn, "SHOW COLUMNS FROM orders");
                $columns = [];
                while ($column = mysqli_fetch_assoc($columns_result)) {
                    $columns[] = $column['Field'];
                }
                
                // Add missing columns if needed
                $required_columns = ['full_name', 'email', 'address', 'phone', 'payment_method', 'fulfillment_option'];
                foreach ($required_columns as $column) {
                    if (!in_array($column, $columns)) {
                        $column_type = ($column == 'address') ? 'TEXT' : 
                            (($column == 'fulfillment_option') ? 'VARCHAR(20)' : 'VARCHAR(100)');
                        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN $column $column_type NOT NULL");
                    }
                }
            }
            
            // Check if order_items table exists
            $table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'order_items'");
            
            if (mysqli_num_rows($table_exists) == 0) {
                // Create order_items table if it doesn't exist
                $create_order_items_table = "CREATE TABLE order_items (
                    id INT(11) AUTO_INCREMENT PRIMARY KEY,
                    order_id INT(11) NOT NULL,
                    product_id INT(11) NOT NULL,
                    quantity INT(11) NOT NULL,
                    price DECIMAL(10,2) NOT NULL,
                    FOREIGN KEY (order_id) REFERENCES orders(id),
                    FOREIGN KEY (product_id) REFERENCES products(id)
                )";
                mysqli_query($conn, $create_order_items_table);
            }
            
            // Verify stock one more time before placing order
            foreach ($cart_items as $id => $item) {
                $stock_query = "SELECT stock FROM products WHERE id = ?";
                $stmt = mysqli_prepare($conn, $stock_query);
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $stock_result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($stock_result);
                
                if ($item['quantity'] > $product['stock']) {
                    throw new Exception("Product '{$item['name']}' is out of stock. Available: {$product['stock']}");
                }
            }
            
            // Insert order - FIX: Use prepared statement instead of direct string interpolation
            $insert_order_sql = "INSERT INTO orders (user_id, full_name, email, address, phone, payment_method, fulfillment_option, total_amount, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = mysqli_prepare($conn, $insert_order_sql);
            mysqli_stmt_bind_param($stmt, "issssssd", $user_id, $full_name, $email, $address, $phone, $payment_method, $fulfillment_option, $total);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_error($conn));
            }
            
            // Get order ID
            $order_id = mysqli_insert_id($conn);
            
            // Insert order items and update stock
            foreach ($cart_items as $id => $item) {
                $product_id = $id; // FIX: Use the key as product_id
                $quantity = $item['quantity'];
                $price = $item['price'];
                
                $sql = "INSERT INTO order_items (order_id, product_id, quantity, price)
                         VALUES (?, ?, ?, ?)";
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iiid", $order_id, $product_id, $quantity, $price);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_error($conn));
                }
                
                // Update product stock
                
            }
            
            // Generate QR code data
            $qr_data = json_encode([
                'order_id' => $order_id,
                'user_id' => $user_id,
                'total' => $total,
                'payment_method' => $payment_method,
                'fulfillment_option' => $fulfillment_option,
                'timestamp' => time()
            ]);
            
            // Generate QR code URL using a free API
            $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_data);
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Clear cart
            $_SESSION['cart'] = [];
            
            // Set success flag
            $success = true;
        } catch (Exception $e) {
            // Rollback transaction on error
            mysqli_rollback($conn);
            $errors[] = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --secondary: #4ecdc4;
            --dark: #2d3436;
            --gray: #636e72;
            --light-gray: #dfe6e9;
            --light: #f9f9f9;
            --white: #ffffff;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --border-radius: 10px;
            --box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
        }
        
        /* Header */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav li {
            margin-left: 25px;
        }
        
        nav a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        nav a i {
            margin-right: 5px;
        }
        
        nav a:hover {
            color: var(--primary);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }
        
        /* Container */
        .container {
            width: 85%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Page Title */
        .page-title-section {
            background-color: var(--white);
            padding: 40px 0;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: var(--box-shadow);
        }
        
        .page-title {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .page-subtitle {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Checkout Container */
        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-bottom: 60px;
        }
        
        /* Checkout Form */
        .checkout-form {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .checkout-form h3 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            color: var(--dark);
              padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .section-title {
            font-size: 1.2rem;
            margin: 25px 0 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
            color: var(--primary);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--dark);
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        textarea:focus,
        select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        /* Fulfillment Options */
        .fulfillment-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .fulfillment-option {
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 20px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .fulfillment-option:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .fulfillment-option.selected {
            border-color: var(--primary);
            background-color: rgba(255, 107, 107, 0.05);
        }
        
        .fulfillment-option.selected::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--primary);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .fulfillment-option i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary);
            display: block;
        }
        
        .fulfillment-option h4 {
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .fulfillment-option p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Delivery Fields */
        .delivery-fields {
            display: none;
            background-color: var(--light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border-left: 3px solid var(--primary);
            animation: fadeIn 0.3s ease;
        }
        
        .delivery-fields.active {
            display: block;
        }
        
        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .payment-method {
            border: 2px solid var(--light-gray);
            border-radius: var(--border-radius);
            padding: 15px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .payment-method:hover {
            border-color: var(--primary);
        }
        
        .payment-method.selected {
            border-color: var(--primary);
            background-color: rgba(255, 107, 107, 0.05);
        }
        
        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .payment-method-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 107, 107, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .payment-method-icon i {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .payment-method-details {
            flex: 1;
        }
        
        .payment-method-name {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .payment-method-description {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        /* Order Summary */
        .order-summary {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 100px;
            align-self: flex-start;
        }
        
        .order-summary h3 {
            font-size: 1.5rem;
            margin-bottom: 25px;
            color: var(--dark);
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .cart-items {
            margin-bottom: 25px;
            max-height: 300px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .cart-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .cart-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .cart-item-details {
            flex-grow: 1;
        }
        
        .cart-item-name {
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .cart-item-price {
            color: var(--primary);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .cart-item-quantity {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 3px;
        }
        
        .order-totals {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px dashed var(--light-gray);
        }
        
        .order-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .order-total-row.final {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: var(--white);
            padding: 12px 25px;
            text-decoration: none;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: var(--transition);
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* Success Message */
        .success-message {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            text-align: center;
            box-shadow: var(--box-shadow);
            margin-bottom: 60px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background-color: var(--success);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 2.5rem;
        }
        
        .success-message h3 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .success-message p {
            color: var(--gray);
            margin-bottom: 25px;
            font-size: 1.1rem;
        }
        
        .qr-code {
            margin: 30px 0;
        }
        
        .qr-code h4 {
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .qr-code img {
            max-width: 200px;
            border: 10px solid var(--white);
            box-shadow: var(--box-shadow);
            border-radius: 10px;
        }
        
        .order-details {
            background: var(--light);
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 30px 0;
            text-align: left;
        }
        
        .order-details h3 {
            margin-top: 0;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }
        
        .payment-instructions {
            margin: 30px 0;
            padding: 25px;
            background: #fff8e1;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--warning);
            text-align: left;
        }
        
        .payment-instructions h4 {
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .payment-instructions p {
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        /* Errors */
        .errors {
            color: var(--white);
            background-color: var(--danger);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            list-style-position: inside;
        }
        
        .errors li {
            margin-bottom: 5px;
        }
        
        .errors li:last-child {
            margin-bottom: 0;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 60px 0 20px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .footer-column h3 {
            color: var(--white);
            margin-bottom: 20px;
            font-size: 1.2rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #ddd;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 8px;
            font-size: 0.8rem;
        }
        
        .footer-links a:hover {
            color: var(--primary);
            transform: translateX(5px);
        }
        
        .footer-contact p {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            color: #ddd;
        }
        
        .footer-contact p i {
            margin-right: 10px;
            color: var(--primary);
            margin-top: 5px;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .social-links a:hover {
            background-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
            font-size: 0.9rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
                margin-bottom: 30px;
            }
            
            .footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                width: 90%;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            nav ul {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background-color: var(--white);
                flex-direction: column;
                padding: 20px;
                box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            }
            
            nav ul.active {
                display: flex;
            }
            
            nav li {
                margin: 10px 0;
            }
            
            .fulfillment-options,
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .checkout-form,
            .order-summary {
                padding: 20px;
            }
            
            .page-title-section {
                padding: 30px 0;
            }
        }
        
        /* Custom Scrollbar */
        .cart-items::-webkit-scrollbar {
            width: 6px;
        }
        
        .cart-items::-webkit-scrollbar-track {
            background: var(--light);
            border-radius: 10px;
        }
        
        .cart-items::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-birthday-cake"></i>
                BLASTICAKES & CRAFTS
            </a>
            
            <button class="mobile-menu-btn" id="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav>
                <ul id="nav-menu">
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
                    <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                    <?php if ($is_admin): ?>
                        <li><a href="admin.php"><i class="fas fa-user-shield"></i> Admin Panel</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo $username; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="page-title-section">
        <div class="container">
            <h1 class="page-title">Checkout</h1>
            <p class="page-subtitle">Complete your order by providing the necessary information below</p>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($errors)): ?>
            <ul class="errors" data-aos="fade-up">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message" data-aos="fade-up">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h3>Thank you for your order!</h3>
                <p>Your order #<?php echo $order_id; ?> has been placed successfully.</p>
                
                <div class="qr-code" data-aos="zoom-in" data-aos-delay="300">
                    <h4>Scan this QR code for your order details</h4>
                    <img src="<?php echo $qr_code_url; ?>" alt="Order QR Code">
                </div>
                
                <div class="order-details" data-aos="fade-up" data-aos-delay="400">
                    <h3>Order Summary</h3>
                    <p><strong>Fulfillment Method:</strong> <?php echo ucfirst($selected_fulfillment); ?></p>
                    
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="cart-item-image">
                            <div class="cart-item-details">
                                <div class="cart-item-name"><?php echo $item['name']; ?></div>
                                <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="order-total-row final">
                        <span>Total:</span>
                        <span>₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>
                
                <?php if ($selected_payment != 'Cash on Delivery'): ?>
                    <div class="payment-instructions" data-aos="fade-up" data-aos-delay="500">
                        <h4><i class="fas fa-info-circle"></i> Payment Instructions</h4>
                        <p>Please complete your payment using the selected method: <strong><?php echo $selected_payment; ?></strong></p>
                        
                        <?php if ($selected_payment == 'GCash'): ?>
                            <p><i class="fas fa-mobile-alt"></i> <strong>GCash Number:</strong> 09123456789</p>
                            <p><i class="fas fa-user"></i> <strong>Account Name:</strong> BLASTICAKES & CRAFTS</p>
                        <?php elseif ($selected_payment == 'PayMaya'): ?>
                            <p><i class="fas fa-mobile-alt"></i> <strong>PayMaya Number:</strong> 09123456789</p>
                            <p><i class="fas fa-user"></i> <strong>Account Name:</strong> BLASTICAKES & CRAFTS</p>
                        <?php elseif ($selected_payment == 'Bank Transfer'): ?>
                            <p><i class="fas fa-university"></i> <strong>Bank:</strong> BDO</p>
                            <p><i class="fas fa-credit-card"></i> <strong>Account Number:</strong> 1234567890</p>
                            <p><i class="fas fa-user"></i> <strong>Account Name:</strong> BLASTICAKES & CRAFTS</p>
                        <?php endif; ?>
                        
                        <p><i class="fas fa-exclamation-triangle"></i> Please include your Order ID (#<?php echo $order_id; ?>) in the payment reference.</p>
                    </div>
                <?php endif; ?>
                
                <?php if ($selected_fulfillment == 'pickup'): ?>
                    <div class="payment-instructions" data-aos="fade-up" data-aos-delay="600">
                        <h4><i class="fas fa-store"></i> Pickup Instructions</h4>
                        <p>You can pick up your order at our store:</p>
                        <p><i class="fas fa-map-marker-alt"></i> <strong>Address:</strong> 123 Cake Street, Barangay Sweet Tooth, Laguna</p>
                        <p><i class="fas fa-clock"></i> <strong>Store Hours:</strong> Monday to Saturday, 9:00 AM to 6:00 PM</p>
                        <p><i class="fas fa-info-circle"></i> Please bring a copy of your order confirmation or QR code when picking up your order.</p>
                    </div>
                <?php endif; ?>
                
                <a href="products.php" class="btn" data-aos="fade-up" data-aos-delay="700">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="checkout-container">
                <div class="checkout-form" data-aos="fade-up">
                    <h3>Order Information</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="checkout-form">
                        <div class="section-title">
                            <i class="fas fa-truck-loading"></i> Select Fulfillment Option
                        </div>
                        
                        <div class="fulfillment-options">
                            <div class="fulfillment-option <?php echo $selected_fulfillment == 'pickup' ? 'selected' : ''; ?>" id="pickup-option" data-aos="fade-up" data-aos-delay="100">
                                <i class="fas fa-store"></i>
                                <h4>Walk-in pickup</h4>
                                <p>Pick up your order at our store</p>
                                <input type="radio" name="fulfillment_option" value="pickup" id="pickup" <?php echo $selected_fulfillment == 'pickup' ? 'checked' : ''; ?> style="display:none;">
                            </div>
                            
                            <div class="fulfillment-option <?php echo $selected_fulfillment == 'delivery' ? 'selected' : ''; ?>" id="delivery-option" data-aos="fade-up" data-aos-delay="200">
                                <i class="fas fa-truck"></i>
                                <h4>Delivery</h4>
                                <p>We'll deliver to your address</p>
                                <input type="radio" name="fulfillment_option" value="delivery" id="delivery" <?php echo $selected_fulfillment == 'delivery' ? 'checked' : ''; ?> style="display:none;">
                            </div>
                        </div>
                        
                        <div class="section-title">
                            <i class="fas fa-user"></i> Customer Information
                        </div>
                        
                        <div class="form-group" data-aos="fade-up" data-aos-delay="300">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" placeholder="Enter your full name" required>
                        </div>
                        
                        <div class="form-group" data-aos="fade-up" data-aos-delay="400">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" placeholder="Enter your phone number" required>
                        </div>
                        
                        <div id="delivery-fields" class="delivery-fields <?php echo $selected_fulfillment == 'delivery' ? 'active' : ''; ?>">
                            <div class="form-group" data-aos="fade-up" data-aos-delay="500">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="Enter your email address">
                            </div>
                            
                            <div class="form-group" data-aos="fade-up" data-aos-delay="600">
                                <label for="address">Delivery Address</label>
                                <textarea id="address" name="address" rows="3" placeholder="Enter your complete delivery address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <div class="section-title">
                            <i class="fas fa-credit-card"></i> Payment Method
                        </div>
                        
                        <div class="payment-methods">
                            <?php 
                            $payment_icons = [
                                'GCash' => 'fa-wallet',
                                'PayMaya' => 'fa-credit-card',
                                'Bank Transfer' => 'fa-university',
                                'Cash on Delivery' => 'fa-money-bill-wave'
                            ];
                            
                            $payment_descriptions = [
                                'GCash' => 'Pay using your GCash wallet',
                                'PayMaya' => 'Pay using your PayMaya account',
                                'Bank Transfer' => 'Direct bank transfer',
                                'Cash on Delivery' => 'Pay when you receive your order'
                            ];
                            
                            foreach ($payment_methods as $index => $method): 
                            ?>
                                <label class="payment-method <?php echo $selected_payment == $method ? 'selected' : ''; ?>" data-aos="fade-up" data-aos-delay="<?php echo 700 + ($index * 100); ?>">
                                    <input type="radio" name="payment_method" value="<?php echo $method; ?>" <?php echo $selected_payment == $method ? 'checked' : ''; ?> required>
                                    <div class="payment-method-icon">
                                        <i class="fas <?php echo $payment_icons[$method]; ?>"></i>
                                    </div>
                                    <div class="payment-method-details">
                                        <div class="payment-method-name"><?php echo $method; ?></div>
                                        <div class="payment-method-description"><?php echo $payment_descriptions[$method]; ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" name="place_order" class="btn btn-block" data-aos="fade-up" data-aos-delay="1100">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                    </form>
                </div>
                
                <div class="order-summary" data-aos="fade-left">
                    <h3>Order Summary</h3>
                    
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" data-aos="fade-up" data-aos-delay="100"></div>
                                <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <div class="cart-item-name"><?php echo $item['name']; ?></div>
                                    <div class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                    <div class="cart-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-totals">
                        <div class="order-total-row">
                            <span>Subtotal:</span>
                            <span>₱<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <div class="order-total-row">
                            <span>Shipping:</span>
                            <span><?php echo ($selected_fulfillment == 'delivery') ? '₱50.00' : 'Free'; ?></span>
                        </div>
                        
                        <div class="order-total-row final">
                            <span>Total:</span>
                            <span>₱<?php echo number_format($total + (($selected_fulfillment == 'delivery') ? 50 : 0), 2); ?></span>
                        </div>
                    </div>
                    
                    <a href="cart.php" class="btn btn-outline btn-block" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Back to Cart
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>BLASTICAKES & CRAFTS</h3>
                    <p>Delicious custom cakes and crafts for all your special occasions. We make your celebrations sweeter and more memorable.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="products.php"><i class="fas fa-chevron-right"></i> Products</a></li>
                        <li><a href="cart.php"><i class="fas fa-chevron-right"></i> Cart</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Categories</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Birthday Cakes</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Wedding Cakes</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Custom Cakes</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Cupcakes</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Crafts & Gifts</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <div class="footer-contact">
                        <p><i class="fas fa-map-marker-alt"></i> 123 Cake Street, Barangay Sweet Tooth, Laguna, Philippines</p>
                        <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
                        <p><i class="fas fa-envelope"></i> info@blasticakes.com</p>
                        <p><i class="fas fa-clock"></i> Monday - Saturday: 9:00 AM - 6:00 PM</p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> BLASTICAKES & CRAFTS. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            const navMenu = document.getElementById('nav-menu');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                });
            }
            
            // Payment method selection
            const paymentMethods = document.querySelectorAll('.payment-method');
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remove selected class from all methods
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    // Add selected class to clicked method
                    this.classList.add('selected');
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                });
            });
            
            // Fulfillment option selection
            const pickupOption = document.getElementById('pickup-option');
            const deliveryOption = document.getElementById('delivery-option');
            const pickupRadio = document.getElementById('pickup');
            const deliveryRadio = document.getElementById('delivery');
            const deliveryFields = document.getElementById('delivery-fields');
            const emailField = document.getElementById('email');
            const addressField = document.getElementById('address');
            
            if (pickupOption && deliveryOption) {
                pickupOption.addEventListener('click', function() {
                    pickupOption.classList.add('selected');
                    deliveryOption.classList.remove('selected');
                    pickupRadio.checked = true;
                    deliveryFields.classList.remove('active');
                    
                    // Make delivery fields not required
                    if (emailField) emailField.required = false;
                    if (addressField) addressField.required = false;
                    
                    // Update total to remove shipping fee
                    updateOrderTotal(false);
                });
                
                deliveryOption.addEventListener('click', function() {
                    deliveryOption.classList.add('selected');
                    pickupOption.classList.remove('selected');
                    deliveryRadio.checked = true;
                    deliveryFields.classList.add('active');
                    
                    // Make delivery fields required
                    if (emailField) emailField.required = true;
                    if (addressField) addressField.required = true;
                    
                    // Update total to add shipping fee
                    updateOrderTotal(true);
                });
                
                // Set initial state based on selected option
                if (pickupRadio && pickupRadio.checked) {
                    pickupOption.click();
                } else if (deliveryRadio && deliveryRadio.checked) {
                    deliveryOption.click();
                } else {
                    // Default to pickup if nothing is selected
                    pickupOption.click();
                }
            }
            
            // Function to update order total based on fulfillment option
            function updateOrderTotal(isDelivery) {
                const subtotalElement = document.querySelector('.order-total-row:first-child span:last-child');
                const shippingElement = document.querySelector('.order-total-row:nth-child(2) span:last-child');
                const totalElement = document.querySelector('.order-total-row.final span:last-child');
                
                if (subtotalElement && shippingElement && totalElement) {
                    const subtotal = parseFloat(subtotalElement.textContent.replace('₱', '').replace(',', ''));
                    
                    if (isDelivery) {
                        shippingElement.textContent = '₱50.00';
                        totalElement.textContent = '₱' + (subtotal + 50).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    } else {
                        shippingElement.textContent = 'Free';
                        totalElement.textContent = '₱' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                }
            }
        });
    </script>
</body>
</html>