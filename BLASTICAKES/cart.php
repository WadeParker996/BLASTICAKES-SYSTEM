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

// Get user info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Get user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Initialize variables
$cart_items = [];
$total = 0;
$stock_error = false;
$out_of_stock_items = [];

// Load cart items from database
$cart_query = "SELECT c.*, p.name, p.price, p.image, p.stock 
               FROM cart c 
               JOIN products p ON c.product_id = p.id 
               WHERE c.user_id = ?";
$stmt = mysqli_prepare($conn, $cart_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    // Use database cart for logged-in users
    while ($item = mysqli_fetch_assoc($result)) {
        $product_id = $item['product_id'];
        $cart_items[$product_id] = [
            'name' => $item['name'],
            'price' => $item['price'],
            'image' => $item['image'],
            'quantity' => $item['quantity']
        ];
        
        // Check stock and calculate total
        if ($item['quantity'] > $item['stock']) {
            $stock_error = true;
            $out_of_stock_items[] = [
                'name' => $item['name'],
                'requested' => $item['quantity'],
                'available' => $item['stock']
            ];
        }
        
        $total += $item['price'] * $item['quantity'];
    }
    
    // Sync with session cart for consistency
    $_SESSION['cart'] = $cart_items;
} else if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // If database cart is empty but session cart exists, sync to database
    $cart_items = $_SESSION['cart'];
    
    foreach ($cart_items as $id => $item) {
        // Insert into database cart
        $check_query = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $id);
        mysqli_stmt_execute($stmt);
        $cart_result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($cart_result) > 0) {
            // Update existing cart item
            $update_query = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($stmt, "iii", $item['quantity'], $user_id, $id);
            mysqli_stmt_execute($stmt);
        } else {
            // Insert new cart item
            $insert_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $id, $item['quantity']);
            mysqli_stmt_execute($stmt);
        }
        
        // Check stock availability and calculate total
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
        
        $total += $item['price'] * $item['quantity'];
    }
}

// Handle remove item from cart
if (isset($_GET['remove']) && isset($_SESSION['cart'][$_GET['remove']])) {
    $product_id = $_GET['remove'];
    unset($_SESSION['cart'][$product_id]);
    
    // Also remove from database cart
    $delete_query = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    
    header("Location: cart.php");
    exit();
}

// Handle update quantity
if (isset($_POST['update_cart'])) {
    foreach ($_POST['quantity'] as $id => $quantity) {
        if (isset($_SESSION['cart'][$id])) {
            // Get current stock from database
            $stock_query = "SELECT stock FROM products WHERE id = ?";
            $stmt = mysqli_prepare($conn, $stock_query);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $stock_result = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($stock_result);
            
            // Ensure quantity doesn't exceed stock
            $quantity = (int)$quantity;
            if ($quantity > $product['stock']) {
                $quantity = $product['stock'];
            }
            
            // Ensure quantity is at least 1
            $quantity = max(1, $quantity);
            
            // Update session cart
            $_SESSION['cart'][$id]['quantity'] = $quantity;
            
            // Update database cart
            $check_query = "SELECT * FROM cart WHERE user_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($conn, $check_query);
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $id);
            mysqli_stmt_execute($stmt);
            $cart_result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($cart_result) > 0) {
                // Update existing cart item
                $update_query = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
                $stmt = mysqli_prepare($conn, $update_query);
                mysqli_stmt_bind_param($stmt, "iii", $quantity, $user_id, $id);
                mysqli_stmt_execute($stmt);
            } else {
                // Insert new cart item
                $insert_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $insert_query);
                mysqli_stmt_bind_param($stmt, "iii", $user_id, $id, $quantity);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    header("Location: cart.php");
    exit();
}

// Handle clear cart
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    
    // Clear database cart
    $clear_query = "DELETE FROM cart WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $clear_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    
    header("Location: cart.php");
    exit();
}

// Get cart count
$cart_count = 0;
foreach ($cart_items as $item) {
    $cart_count += $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - BLASTICAKES & CRAFTS</title>
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
            --dark: #333;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --light: #f8f9fa;
            --white: #ffffff;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --transition: all 0.3s ease;
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
            --border-radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
            overflow-x: hidden;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
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
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
            align-items: center;
        }
        
        nav ul li {
            margin-left: 25px;
        }
        
        nav ul li a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 8px 0;
            position: relative;
            transition: var(--transition);
        }
        
        nav ul li a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background-color: var(--primary);
            bottom: 0;
            left: 0;
            transition: var(--transition);
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        nav ul li a:hover::after {
            width: 100%;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-btn {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-dropdown-btn img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        
        .user-dropdown-content {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            width: 200px;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
        }
        
        .user-dropdown:hover .user-dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-dropdown-content a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .user-dropdown-content a:hover {
            background-color: var(--light);
            color: var(--primary);
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        /* Cart Badge */
        .cart-link {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
        }
        
        /* Page Title */
        .page-title {
            margin: 30px 0;
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark);
            text-align: center;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Cart Container */
        .cart-container {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        /* Cart Table */
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cart-table th {
            text-align: left;
            padding: 15px 10px;
            border-bottom: 2px solid var(--light-gray);
            font-weight: 600;
            color: var(--gray);
        }
        
        .cart-table td {
            padding: 15px 10px;
            border-bottom: 1px solid var(--light-gray);
            vertical-align: middle;
        }
        
        .cart-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-info {
            display: flex;
            align-items: center;
        }
        
        .product-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 15px;
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        
        .product-name {
            font-weight: 500;
            color: var(--dark);
        }
        
        .stock-warning {
            font-size: 0.8rem;
            color: var(--danger);
            margin-top: 5px;
        }
        
        .quantity-input {
            width: 70px;
            padding: 8px 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            text-align: center;
            font-family: 'Poppins', sans-serif;
        }
        
        .price, .subtotal {
            font-weight: 500;
            color: var(--dark);
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-danger {
            background-color: var(--danger);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-secondary {
            background-color: var(--gray);
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-disabled {
            background-color: var(--light-gray);
            color: var(--gray);
            cursor: not-allowed;
        }
        
        .btn-disabled:hover {
            background-color: var(--light-gray);
            transform: none;
            box-shadow: none;
        }
        
        /* Cart Actions */
        .cart-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        /* Cart Summary */
        .cart-summary {
            background-color: var(--light);
            padding: 20px;
            border-radius: var(--border-radius);
            min-width: 250px;
        }
        
        .cart-total {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        /* Empty Cart */
        .empty-cart {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .empty-cart p {
            color: var(--gray);
            margin-bottom: 20px;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 60px 0 20px;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column {
            flex: 1;
            min-width: 200px;
        }
        
        .footer-column h3 {
            color: var(--white);
            margin-bottom: 20px;
            font-size: 1.3rem;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            width: 40px;
            height: 2px;
            background-color: var(--primary);
            bottom: 0;
            left: 0;
        }
        
        .footer-column p {
            color: #adb5bd;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #adb5bd;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 8px;
            color: var(--primary);
        }
        
        .footer-links a:hover {
            color: var(--white);
            padding-left: 5px;
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
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: var(--white);
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
            color: #adb5bd;
            font-size: 0.9rem;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .cart-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .cart-summary {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
            }
            
            nav {
                width: 100%;
                margin-top: 15px;
            }
            
            nav ul {
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            nav ul li {
                margin: 0 10px;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .cart-table thead {
                display: none;
            }
            
            .cart-table tbody, .cart-table tr, .cart-table td {
                display: block;
                width: 100%;
            }
            
            .cart-table tr {
                margin-bottom: 20px;
                border: 1px solid var(--light-gray);
                border-radius: var(--border-radius);
                padding: 15px;
            }
            
            .cart-table td {
                text-align: right;
                padding: 10px 0;
                border-bottom: 1px solid var(--light-gray);
                position: relative;
            }
            
            .cart-table td:last-child {
                border-bottom: none;
            }
            
            .cart-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 50%;
                padding-right: 15px;
                font-weight: 600;
                text-align: left;
            }
            
            .product-info {
                justify-content: flex-end;
            }
        }
        
        @media (max-width: 576px) {
            .container {
                width: 95%;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-birthday-cake"></i>
                    BLASTICAKES & CRAFTS
                </a>
                
                <button class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
                
                <nav>
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="products.php"><i class="fas fa-shopping-basket"></i> Products</a></li>
                        <li><a href="my_orders.php"><i class="fas fa-box"></i> My Orders</a></li>
                        <li>
                            <a href="cart.php" class="cart-link">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <?php if ($cart_count > 0): ?>
                                <span class="cart-badge"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="user-dropdown">
                            <div class="user-dropdown-btn">
                                <?php
                                // Get user profile picture
                                $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.png';
                                ?>
                                <img src="profile_images/<?php echo $profile_pic; ?>" alt="Profile">
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
        </div>
    </header>

    <div class="container">
        <h1 class="page-title">Shopping Cart</h1>
        
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Product added to cart successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['stock_warning'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>Stock Limit Reached:</strong>
                The quantity for <?php echo htmlspecialchars($_GET['product']); ?> has been set to the maximum available stock.
            </div>
        <?php endif; ?>
        
        <?php if ($stock_error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <strong>Out of Stock Warning:</strong>
                <ul>
                    <?php foreach ($out_of_stock_items as $item): ?>
                        <li>
                            <?php echo $item['name']; ?> - You requested <?php echo $item['requested']; ?> but only <?php echo $item['available']; ?> are available.
                        </li>
                    <?php endforeach; ?>
                </ul>
                Please update your cart quantities before proceeding to checkout.
            </div>
        <?php endif; ?>
        
        <div class="cart-container" data-aos="fade-up">
            <?php if (!empty($cart_items)): ?>
                <form method="post" action="cart.php">
                    <table class="cart-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $id => $item):
                                // Get current stock from database for display
                                $stock_query = "SELECT stock FROM products WHERE id = ?";
                                $stmt = mysqli_prepare($conn, $stock_query);
                                mysqli_stmt_bind_param($stmt, "i", $id);
                                mysqli_stmt_execute($stmt);
                                $stock_result = mysqli_stmt_get_result($stmt);
                                $product = mysqli_fetch_assoc($stock_result);
                                $available_stock = $product['stock'];
                                $exceeds_stock = $item['quantity'] > $available_stock;
                            ?>
                                <tr <?php echo $exceeds_stock ? 'style="background-color: #fff8f8;"' : ''; ?>>
                                    <td data-label="Product">
                                        <div class="product-info">
                                            <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="product-img">
                                            <div>
                                                <div class="product-name"><?php echo $item['name']; ?></div>
                                                <?php if ($exceeds_stock): ?>
                                                    <div class="stock-warning">
                                                        <i class="fas fa-exclamation-circle"></i> Only <?php echo $available_stock; ?> in stock!
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Price" class="price">₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td data-label="Quantity">
                                        <input type="number" name="quantity[<?php echo $id; ?>]" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $available_stock; ?>" class="quantity-input"
                                            onchange="if(this.value > <?php echo $available_stock; ?>) { this.value = <?php echo $available_stock; ?>; alert('Maximum available stock is <?php echo $available_stock; ?>'); }">
                                    </td>
                                    <td data-label="Subtotal" class="subtotal">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    <td data-label="Action">
                                        <a href="cart.php?remove=<?php echo $id; ?>" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Remove
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="cart-actions">
                        <div class="action-buttons">
                            <button type="submit" name="update_cart" class="btn">
                                <i class="fas fa-sync-alt"></i> Update Cart
                            </button>
                            <a href="cart.php?clear=1" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Clear Cart
                            </a>
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-shopping-basket"></i> Continue Shopping
                            </a>
                        </div>
                        
                        <div class="cart-summary">
                            <div class="cart-total">Total: ₱<?php echo number_format($total, 2); ?></div>
                            <?php if (!$stock_error): ?>
                                <a href="checkout.php" class="btn" style="width: 100%;">
                                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-disabled" style="width: 100%;" disabled>
                                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                                </button>
                                <p style="color: var(--danger); font-size: 0.8rem; margin-top: 10px;">
                                    <i class="fas fa-exclamation-circle"></i> Please update quantities before checkout
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="empty-cart" data-aos="fade-up">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any products to your cart yet.</p>
                    <a href="products.php" class="btn">
                        <i class="fas fa-shopping-basket"></i> Browse Products
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>BLASTICAKES & CRAFTS</h3>
                    <p>Specializing in custom cakes, cupcakes, and crafts for all occasions. We bring your sweet dreams to life!</p>
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
                        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="products.php"><i class="fas fa-birthday-cake"></i> Products</a></li>
                        <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul class="footer-links">
                        <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                        <li><a href="shipping.php"><i class="fas fa-truck"></i> Shipping & Delivery</a></li>
                        <li><a href="privacy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
                        <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Information</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> 123 Cake Street, Bacolod City</a></li>
                        <li><a href="tel:+639123456789"><i class="fas fa-phone"></i> +63 912 345 6789</a></li>
                        <li><a href="mailto:info@blasticakes.com"><i class="fas fa-envelope"></i> info@blasticakes.com</a></li>
                        <li><a href="#"><i class="fas fa-clock"></i> Mon-Sat: 8:00 AM - 8:00 PM</a></li>
                    </ul>
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
        // Initialize AOS animation
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const nav = document.querySelector('nav');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    nav.classList.toggle('active');
                });
            }
            
            // Quantity input validation and real-time subtotal update
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const max = parseInt(this.getAttribute('max'), 10);
                    const value = parseInt(this.value, 10) || 0;
                    
                    if (value > max) {
                        this.value = max;
                        alert('Maximum available stock is ' + max);
                    }
                    
                    if (value < 1) {
                        this.value = 1;
                    }
                    
                    // Update subtotal
                    const row = this.closest('tr');
                    const priceCell = row.querySelector('[data-label="Price"]');
                    const subtotalCell = row.querySelector('[data-label="Subtotal"]');
                    
                    if (priceCell && subtotalCell) {
                        const price = parseFloat(priceCell.innerText.replace('₱', '').replace(',', ''));
                        const subtotal = price * this.value;
                        subtotalCell.innerText = '₱' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    }
                    
                    // Update total
                    updateTotal();
                });
            });
            
            function updateTotal() {
                let total = 0;
                document.querySelectorAll('[data-label="Subtotal"]').forEach(cell => {
                    const subtotal = parseFloat(cell.innerText.replace('₱', '').replace(',', ''));
                    if (!isNaN(subtotal)) {
                        total += subtotal;
                    }
                });
                
                const cartTotal = document.querySelector('.cart-total');
                if (cartTotal) {
                    cartTotal.innerText = 'Total: ₱' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
            }
            
            // Confirm before removing items or clearing cart
            const removeLinks = document.querySelectorAll('a[href*="remove"]');
            removeLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to remove this item from your cart?')) {
                        e.preventDefault();
                    }
                });
            });
            
            const clearCartLink = document.querySelector('a[href*="clear"]');
            if (clearCartLink) {
                clearCartLink.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to clear your entire cart?')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>