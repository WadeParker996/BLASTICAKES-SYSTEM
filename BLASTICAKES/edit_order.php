<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Include database connection
require_once 'includes/db.php';

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_orders.php");
    exit;
}

$order_id = $_GET['id'];

// Verify the order belongs to the user
$check_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    // Order doesn't exist or doesn't belong to this user
    header("Location: my_orders.php");
    exit;
}

$order = mysqli_fetch_assoc($result);

// Check if order is in editable state (Pending or Processing)
if ($order['status'] != 'Pending' && $order['status'] != 'Processing') {
    $_SESSION['error'] = "This order cannot be edited anymore.";
    header("Location: my_orders.php");
    exit;
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.image FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?";
$stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);
$order_items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $order_items[] = $item;
}

// Get all available products for potential order changes
$products_query = "SELECT * FROM products";
$products_result = mysqli_query($conn, $products_query);
$available_products = [];
while ($product = mysqli_fetch_assoc($products_result)) {
    $available_products[] = $product;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $fulfillment_option = mysqli_real_escape_string($conn, $_POST['fulfillment_option']);
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);
    
    // Update order details
    $update_query = "UPDATE orders SET
                    fulfillment_option = ?,
                    payment_method = ?,
                    phone = ?,
                    shipping_address = ?
                    WHERE id = ? AND user_id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ssssii", $fulfillment_option, $payment_method, $phone, $shipping_address, $order_id, $user_id);
    $update_result = mysqli_stmt_execute($stmt);
    
    // Handle order items changes if any
    if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
        foreach ($_POST['item_id'] as $key => $item_id) {
            $quantity = (int)$_POST['quantity'][$key];
            
            if ($quantity <= 0) {
                // Remove item if quantity is 0 or negative
                $delete_item_query = "DELETE FROM order_items WHERE id = ? AND order_id = ?";
                $stmt = mysqli_prepare($conn, $delete_item_query);
                mysqli_stmt_bind_param($stmt, "ii", $item_id, $order_id);
                mysqli_stmt_execute($stmt);
            } else {
                // Update item quantity
                $update_item_query = "UPDATE order_items SET quantity = ? WHERE id = ? AND order_id = ?";
                $stmt = mysqli_prepare($conn, $update_item_query);
                mysqli_stmt_bind_param($stmt, "iii", $quantity, $item_id, $order_id);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    // Add new items if any
    if (isset($_POST['new_product_id']) && is_array($_POST['new_product_id'])) {
        foreach ($_POST['new_product_id'] as $key => $product_id) {
            if (empty($product_id)) continue;
            
            $quantity = (int)$_POST['new_quantity'][$key];
            if ($quantity <= 0) continue;
            
            // Get product price
            $price_query = "SELECT price FROM products WHERE id = ?";
            $stmt = mysqli_prepare($conn, $price_query);
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            mysqli_stmt_execute($stmt);
            $price_result = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($price_result);
            
            if ($product) {
                $price = $product['price'];
                
                // Insert new item
                $insert_item_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_item_query);
                mysqli_stmt_bind_param($stmt, "iiid", $order_id, $product_id, $quantity, $price);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    // Recalculate total amount
    $total_query = "SELECT SUM(quantity * price) as total FROM order_items WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $total_query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    $total_result = mysqli_stmt_get_result($stmt);
    $total_row = mysqli_fetch_assoc($total_result);
    $new_total = $total_row['total'];
    
    // Update order total
    $update_total_query = "UPDATE orders SET total_amount = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_total_query);
    mysqli_stmt_bind_param($stmt, "di", $new_total, $order_id);
    mysqli_stmt_execute($stmt);
    
    // Add notification
    $notification_title = "Order Updated";
    $notification_message = "Your order #$order_id has been updated successfully.";
    $notification_query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $notification_query);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $notification_title, $notification_message);
    mysqli_stmt_execute($stmt);
    
    $_SESSION['success'] = "Order has been updated successfully.";
    header("Location: my_orders.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Order - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
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
            --border-radius: 8px;
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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
        
        .container {
            width: 85%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
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
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
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
            color: var(--dark);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* User dropdown menu */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-btn {
            display: flex;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-dropdown-btn:hover {
            background-color: rgba(255, 107, 107, 0.1);
            color: var(--primary);
        }
        
        .user-dropdown-btn img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            margin-right: 8px;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: var(--white);
            min-width: 200px;
            box-shadow: var(--box-shadow);
            z-index: 1;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-top: 10px;
        }
        
        .user-dropdown-content a {
            color: var(--dark);
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .user-dropdown-content a:hover {
            background-color: var(--light);
            color: var(--primary);
        }
        
        .user-dropdown:hover .user-dropdown-content {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
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
        
        /* Page Title */
        .page-title-section {
            background-color: var(--primary);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Form Styles */
        .form-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .form-container h3 {
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 1.4rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
            color: var(--dark);
        }
        
        .form-control:focus {
            border-color: var(--primary);
               outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        
        /* Order Items Styles */
        .order-items {
            margin-top: 30px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .order-item:hover {
            background-color: rgba(249, 249, 249, 0.5);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .item-details {
            flex-grow: 1;
        }
        
        .item-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .item-price {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .item-quantity {
            width: 70px;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            text-align: center;
            margin: 0 15px;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }
        
        .item-quantity:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        
        .item-total {
            font-weight: 600;
            margin-left: 15px;
            min-width: 100px;
            text-align: right;
            color: var(--primary-dark);
        }
        
        .remove-item {
            color: var(--danger);
            cursor: pointer;
            margin-left: 15px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .remove-item:hover {
            background-color: rgba(231, 76, 60, 0.1);
        }
        
        /* Add Item Row */
        .add-item-row {
            display: flex;
            align-items: center;
            padding: 20px;
            background-color: var(--light);
            border-radius: var(--border-radius);
            margin-top: 20px;
            border: 1px dashed var(--light-gray);
            transition: var(--transition);
        }
        
        .add-item-row:hover {
            border-color: var(--primary);
            background-color: rgba(255, 107, 107, 0.05);
        }
        
        .add-item-select {
            flex-grow: 1;
            margin-right: 15px;
        }
        
        .add-item-btn {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
            transition: var(--transition);
        }
        
        .add-item-btn:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }
        
        .add-item-btn i {
            margin-right: 8px;
        }
        
        /* Order Summary */
        .order-summary {
            margin-top: 30px;
            text-align: right;
            padding: 20px;
            background-color: var(--light);
            border-radius: var(--border-radius);
            border: 1px solid var(--light-gray);
        }
        
        .order-total {
            font-size: 1.3rem;
            font-weight: 700;
            margin-top: 10px;
            color: var(--primary-dark);
        }
        
        .text-muted {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        /* Alerts */
        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.15);
            color: #27ae60;
            border-left: 4px solid #27ae60;
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.15);
            color: #c0392b;
            border-left: 4px solid #c0392b;
        }
        
        /* Form Actions */
        .form-actions {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary);
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: var(--border-radius);
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-secondary {
            background-color: var(--gray);
        }
        
        .btn-secondary:hover {
            background-color: #4d5d61;
            box-shadow: 0 5px 15px rgba(99, 110, 114, 0.3);
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 50px 0 20px;
            margin-top: 80px;
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
        
        .footer-column h3:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary);
        }
        
        .footer-column p {
            color: #b2bec3;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        
        .social-links {
            display: flex;
            gap: 15px;
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
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 12px;
        }
        
        .footer-links a {
            color: #b2bec3;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            font-size: 0.9rem;
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
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .footer-contact p i {
            margin-right: 10px;
            color: var(--primary);
            margin-top: 5px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #b2bec3;
            font-size: 0.9rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
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
            
            .order-item {
                flex-wrap: wrap;
            }
            
            .item-image {
                margin-bottom: 10px;
            }
            
            .item-details {
                width: 100%;
                margin-bottom: 15px;
            }
            
            .item-quantity {
                margin: 0;
            }
            
            .item-total {
                margin-left: auto;
            }
        }
        
        @media (max-width: 576px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
            
            .form-container {
                padding: 20px 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
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
                    <li><a href="my_orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                    <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                    <li class="user-dropdown">
                        <div class="user-dropdown-btn">
                            <?php
                            // Get user profile picture
                            $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.png';
                            ?>
                            <img src="profile_images/<?php echo $profile_pic; ?>" alt="Profile">
                            <?php echo $username; ?>
                        </div>
                        <div class="user-dropdown-content">
                            <a href="account.php"><i class="fas fa-user"></i> My Account</a>
                            <a href="my_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
                            <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="page-title-section">
        <div class="container">
            <h1 class="page-title" data-aos="fade-up">Edit Order #<?php echo $order_id; ?></h1>
            <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">Make changes to your order details and items below</p>
        </div>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" data-aos="fade-up">
                <i class="fas fa-exclamation-circle"></i>
                <?php
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-container" data-aos="fade-up">
                <h3><i class="fas fa-info-circle"></i> Order Details</h3>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="100">
                    <label for="fulfillment_option">Fulfillment Method</label>
                    <select name="fulfillment_option" id="fulfillment_option" class="form-control" required>
                        <option value="pickup" <?php echo ($order['fulfillment_option'] == 'pickup') ? 'selected' : ''; ?>>Pickup at Store</option>
                        <option value="delivery" <?php echo ($order['fulfillment_option'] == 'delivery') ? 'selected' : ''; ?>>Home Delivery</option>
                    </select>
                </div>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="150">
                    <label for="payment_method">Payment Method</label>
                    <select name="payment_method" id="payment_method" class="form-control" required>
                        <option value="Cash on Delivery" <?php echo ($order['payment_method'] == 'Cash on Delivery') ? 'selected' : ''; ?>>Cash on Delivery</option>
                        <option value="GCash" <?php echo ($order['payment_method'] == 'GCash') ? 'selected' : ''; ?>>GCash</option>
                        <option value="Bank Transfer" <?php echo ($order['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="200">
                    <label for="phone">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($order['phone'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group" data-aos="fade-up" data-aos-delay="250">
                    <label for="shipping_address">Delivery Address</label>
                    <textarea name="shipping_address" id="shipping_address" class="form-control" rows="3" required><?php echo htmlspecialchars($order['shipping_address']); ?></textarea>
                </div>
            </div>
            
            <div class="form-container order-items" data-aos="fade-up" data-aos-delay="300">
                <h3><i class="fas fa-shopping-basket"></i> Order Items</h3>
                
                <?php if (empty($order_items)): ?>
                    <div class="empty-items" style="text-align: center; padding: 30px;">
                        <i class="fas fa-box-open" style="font-size: 3rem; color: var(--light-gray); margin-bottom: 15px;"></i>
                        <p>No items in this order. Add some items below.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($order_items as $item): ?>
                    <div class="order-item" data-aos="fade-up">
                        <img src="images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="item-image">
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price">₱<?php echo number_format($item['price'], 2); ?> each</div>
                        </div>
                        <input type="hidden" name="item_id[]" value="<?php echo $item['id']; ?>">
                        <input type="number" name="quantity[]" class="item-quantity" value="<?php echo $item['quantity']; ?>" min="0" max="99">
                        <div class="item-total">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        <span class="remove-item" onclick="this.parentNode.querySelector('.item-quantity').value=0;" title="Remove item">
                            <i class="fas fa-trash-alt"></i>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div id="new-items-container">
                    <!-- New items will be added here -->
                </div>
                
                <button type="button" class="add-item-btn" onclick="addNewItemRow()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
                
                <div class="order-summary" data-aos="fade-up">
                    <div class="text-muted">Current Order Total</div>
                    <div class="order-total">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                    <div class="text-muted">Total will be recalculated after saving changes</div>
                </div>
            </div>
            
            <div class="form-actions" data-aos="fade-up">
                <a href="my_orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
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
            
            // Update item totals when quantity changes
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('item-quantity')) {
                    const item = e.target.closest('.order-item');
                    if (item) {
                        const priceText = item.querySelector('.item-price').textContent;
                        const price = parseFloat(priceText.replace('₱', '').replace(',', ''));
                        const quantity = parseInt(e.target.value) || 0;
                        const total = price * quantity;
                        item.querySelector('.item-total').textContent = '₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                }
            });
        });
        
        // Add new item row
        function addNewItemRow() {
            const container = document.getElementById('new-items-container');
            const newRow = document.createElement('div');
            newRow.className = 'add-item-row';
            newRow.setAttribute('data-aos', 'fade-up');
            
            newRow.innerHTML = `
                <select name="new_product_id[]" class="form-control add-item-select" required>
                    <option value="">Select a product</option>
                    <?php foreach ($available_products as $product): ?>
                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?> - ₱<?php echo number_format($product['price'], 2); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="new_quantity[]" class="item-quantity" value="1" min="1" max="99">
                <span class="remove-item" onclick="this.parentNode.remove();" title="Remove">
                    <i class="fas fa-times"></i>
                </span>
            `;
            
            container.appendChild(newRow);
        }
    </script>
</body>
</html>