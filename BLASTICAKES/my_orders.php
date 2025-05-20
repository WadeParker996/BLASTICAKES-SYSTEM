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
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Include database connection
require_once 'includes/db.php';

// Get user data
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $user_query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($user_result);

// Get user's orders
$orders = [];
$sql = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY order_date DESC";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Get order items for this order
        $order_items_sql = "SELECT oi.*, p.name, p.image FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            WHERE oi.order_id = {$row['id']}";
        $items_result = mysqli_query($conn, $order_items_sql);
        $items = [];
        if ($items_result && mysqli_num_rows($items_result) > 0) {
            while ($item = mysqli_fetch_assoc($items_result)) {
                $items[] = $item;
            }
        }
        $row['items'] = $items;
        $orders[] = $row;
    }
}

// Check for notifications
$notifications = [];
$notification_sql = "SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC";
$notification_result = mysqli_query($conn, $notification_sql);
if ($notification_result && mysqli_num_rows($notification_result) > 0) {
    while ($row = mysqli_fetch_assoc($notification_result)) {
        $notifications[] = $row;
    }
}

// Mark notifications as read if viewing this page
if (!empty($notifications)) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
}

// Get cart count
$cart_count = 0;
if(isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_count += $item['quantity'];
    }
}

// Get product categories for footer
$categories = [];
$category_query = "SELECT DISTINCT category FROM products";
$category_result = mysqli_query($conn, $category_query);
if ($category_result && mysqli_num_rows($category_result) > 0) {
    while ($row = mysqli_fetch_assoc($category_result)) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --primary-light: #ffeded;
            --secondary: #4ecdc4;
            --dark: #333333;
            --light: #f9f9f9;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
            --border-radius: 10px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: var(--light);
            color: var(--dark);
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            padding: 15px 0;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            font-weight: bold;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.5rem;
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
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-btn {
            display: flex;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            padding: 8px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-dropdown-btn:hover {
            color: var(--primary);
        }
        
        .user-dropdown-btn img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--white);
            min-width: 220px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1000;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
            transition: var(--transition);
        }
        
        .user-dropdown-content a {
            color: var(--dark);
            padding: 15px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
            font-weight: 400;
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            font-size: 1.1rem;
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
        
        /* Cart Badge */
        .cart-link {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Page Title */
        .page-title-section {
            background: linear-gradient(135deg, var(--primary-light) 0%, #fff5f5 100%);
            padding: 50px 0;
            margin-bottom: 50px;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Notification Styles */
        .notifications-container {
            margin-bottom: 40px;
        }
        
        .notification-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-title {
            font-size: 1.5rem;
            margin: 0;
            margin-right: 10px;
        }
        
        .notification-badge {
            background-color: var(--primary);
            color: var(--white);
            font-size: 0.8rem;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .notification {
            background-color: var(--white);
            border-left: 4px solid var(--primary);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 15px;
            transition: var(--transition);
            animation: fadeIn 0.5s ease;
        }
        
        .notification:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .notification-message-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: var(--dark);
        }
        
        .notification-message-body {
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .notification-time {
            color: var(--gray);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }
        
        .notification-time i {
            margin-right: 5px;
            color: var(--primary);
        }
        
        /* Order Card Styles */
        .orders-container {
            margin-bottom: 60px;
        }
        
        .order-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            overflow: hidden;
            transition: var(--transition);
            animation: fadeIn 0.5s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        
        .order-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fcfcfc;
        }
        
        .order-id {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .order-id i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .order-date {
            color: var(--gray);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .order-date i {
            margin-right: 5px;
        }
        
        .order-body {
            padding: 25px;
        }
        
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .order-detail {
            margin-bottom: 5px;
        }
        
        .order-detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .order-detail-value {
            font-weight: 500;
            color: var(--dark);
        }

        .order-status {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-completed, .status-delivered {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Order Products */
        .order-products {
            margin-top: 20px;
            border-top: 1px solid var(--light-gray);
            padding-top: 20px;
        }
        
        .order-products-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .product-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--light-gray);
        }
        
        .product-item:last-child {
            border-bottom: none;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        
        .product-info {
            flex-grow: 1;
        }
        
        .product-name {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 3px;
        }
        
        .product-price {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .product-quantity {
            background-color: var(--light);
            color: var(--dark);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 15px;
        }
        
        /* Order Actions */
        .order-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: var(--white);
        }
        
        .btn-outline {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-edit {
            background-color: var(--secondary);
            color: var(--white);
        }
        
        .btn-edit:hover {
            background-color: #3dbeb6;
            color: var(--white);
        }
        
        .btn-cancel {
            background-color: #dc3545;
            color: var(--white);
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            color: var(--white);
        }
        
        .btn-delete {
            background-color: #6c757d;
            color: var(--white);
            width: 40px;
            height: 40px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-delete:hover {
            background-color: #5a6268;
            color: var(--white);
        }
        
        .btn-delete i {
            margin: 0;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin: 30px 0;
        }
        
        .empty-state i {
            font-size: 70px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 60px 0 20px;
            margin-top: 80px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
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
            left: 0;
            bottom: 0;
            width: 50px;
            height: 2px;
            background-color: var(--primary);
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
            color: #aaa;
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 8px;
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        .footer-links a:hover {
            color: var(--primary);
            padding-left: 5px;
        }
        
        .footer-contact p {
            margin-bottom: 15px;
            color: #aaa;
            display: flex;
            align-items: flex-start;
        }
        
        .footer-contact p i {
            margin-right: 10px;
            color: var(--primary);
            font-size: 1.1rem;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.1);
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
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #aaa;
            font-size: 0.9rem;
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
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .container {
                width: 95%;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            nav ul {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 80%;
                height: calc(100vh - 80px);
                background-color: var(--white);
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
                box-shadow: 5px 0 15px rgba(0,0,0,0.1);
                transition: 0.4s;
                z-index: 999;
            }
            
            nav ul.active {
                left: 0;
            }
            
            nav ul li {
                margin: 15px 0;
                width: 100%;
            }
            
            .order-details {
                grid-template-columns: 1fr;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-date {
                margin-top: 5px;
            }
            
            .order-actions {
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .notification {
                padding: 15px;
            }
            
            .order-card {
                margin-bottom: 20px;
            }
            
            .order-header, .order-body {
                padding: 15px;
            }
            
            .product-image {
                width: 50px;
                height: 50px;
            }
            
            .btn {
                padding: 8px 15px;
                font-size: 0.9rem;
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
            
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav>
                <ul id="nav-menu">
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
                                <a href="account.php"><i class="fas fa-user-circle"></i> My Account</a>
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
            <h1 class="page-title" data-aos="fade-up">My Orders</h1>
            <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">Track and manage all your orders in one place</p>
        </div>
    </div>
    
    <div class="container">
        <?php if (!empty($notifications)): ?>
        <div class="notifications-container" data-aos="fade-up">
            <div class="notification-header">
                <h2 class="notification-title">Notifications</h2>
                <span class="notification-badge"><?php echo count($notifications); ?></span>
            </div>
            
            <?php foreach ($notifications as $notification): ?>
            <div class="notification" data-aos="fade-up" data-aos-delay="<?php echo $loop_index * 50; ?>">
                <div class="notification-message-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                <div class="notification-message-body"><?php echo htmlspecialchars($notification['message']); ?></div>
                <div class="notification-time">
                    <i class="far fa-clock"></i>
                    <?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
        <div class="empty-state" data-aos="fade-up">
            <i class="fas fa-shopping-bag"></i>
            <h3>No Orders Yet</h3>
            <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-store"></i> Shop Now
            </a>
        </div>
        <?php else: ?>
        <div class="orders-container">
            <?php foreach ($orders as $index => $order): ?>
            <div class="order-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                <div class="order-header">
                    <div class="order-id">
                        <i class="fas fa-receipt"></i>
                        Order #<?php echo $order['id']; ?>
                    </div>
                    <div class="order-date">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('F j, Y', strtotime($order['order_date'])); ?>
                    </div>
                </div>
                <div class="order-body">
                    <div class="order-details">
                        <div class="order-detail">
                            <div class="order-detail-label">Status</div>
                            <div class="order-detail-value">
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <i class="fas fa-circle" style="font-size: 8px; margin-right: 5px;"></i>
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="order-detail">
                            <div class="order-detail-label">Total Amount</div>
                            <div class="order-detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                        </div>
                        
                        <div class="order-detail">
                            <div class="order-detail-label">Fulfillment Method</div>
                            <div class="order-detail-value">
                                <?php if($order['fulfillment_option'] == 'delivery'): ?>
                                    <i class="fas fa-truck" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php else: ?>
                                    <i class="fas fa-store" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php endif; ?>
                                <?php echo ucfirst($order['fulfillment_option']); ?>
                            </div>
                        </div>
                        <div class="order-detail">
                            <div class="order-detail-label">Payment Method</div>
                            <div class="order-detail-value">
                                <?php if(strtolower($order['payment_method']) == 'cash on delivery' || strtolower($order['payment_method']) == 'cod'): ?>
                                    <i class="fas fa-money-bill-wave" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php elseif(strpos(strtolower($order['payment_method']), 'gcash') !== false): ?>
                                    <i class="fas fa-mobile-alt" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php elseif(strpos(strtolower($order['payment_method']), 'bank') !== false): ?>
                                    <i class="fas fa-university" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php else: ?>
                                    <i class="fas fa-credit-card" style="color: var(--primary); margin-right: 5px;"></i>
                                <?php endif; ?>
                                <?php echo $order['payment_method']; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Display order items/products -->
                    <?php if (!empty($order['items'])): ?>
                    <div class="order-products">
                        <div class="order-products-title">
                            <i class="fas fa-box-open" style="margin-right: 8px; color: var(--primary);"></i>
                            Products
                        </div>
                        <ul class="product-list">
                            <?php foreach ($order['items'] as $item): ?>
                            <li class="product-item">
                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-image">
                                <div class="product-info">
                                    <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="product-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                </div>
                                <span class="product-quantity">x<?php echo $item['quantity']; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-actions">
                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        
                        <?php if (strtolower($order['status']) == 'pending' || strtolower($order['status']) == 'processing'): ?>
                            <a href="edit_order.php?id=<?php echo $order['id']; ?>" class="btn btn-edit">
                                <i class="fas fa-edit"></i> Edit Order
                            </a>
                            <a href="cancel_order.php?id=<?php echo $order['id']; ?>" class="btn btn-cancel" onclick="return confirm('Are you sure you want to cancel this order?');">
                                <i class="fas fa-times"></i> Cancel Order
                            </a>
                        <?php endif; ?>
                        
                        <?php if (strtolower($order['status']) == 'cancelled' || strtolower($order['status']) == 'completed' || strtolower($order['status']) == 'delivered'): ?>
                            <a href="delete_order.php?id=<?php echo $order['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this order? This action cannot be undone.');">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (strtolower($order['status']) == 'completed' || strtolower($order['status']) == 'delivered'): ?>
                            <a href="review_order.php?id=<?php echo $order['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-star"></i> Write Review
                            </a>
                            <a href="reorder.php?id=<?php echo $order['id']; ?>" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reorder
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>About Us</h3>
                    <p style="color: #aaa; margin-bottom: 20px;">BLASTICAKES & CRAFTS specializes in custom cakes, pastries, and handcrafted items for all your special occasions.</p>
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
                        <li><a href="products.php"><i class="fas fa-chevron-right"></i> Shop</a></li>
                        <li><a href="about.php"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact Us</a></li>
                        <li><a href="faq.php"><i class="fas fa-chevron-right"></i> FAQs</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Categories</h3>
                    <ul class="footer-links">
                        <?php foreach($categories as $category): ?>
                        <li>
                            <a href="products.php?category=<?php echo urlencode($category); ?>">
                                <i class="fas fa-chevron-right"></i> <?php echo htmlspecialchars($category); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <div class="footer-contact">
                        <p><i class="fas fa-map-marker-alt"></i> 123 Bakery Street, Quezon City, Philippines</p>
                        <p><i class="fas fa-phone"></i> +63 912 345 6789</p>
                        <p><i class="fas fa-envelope"></i> info@blasticakes.com</p>
                        <p><i class="fas fa-clock"></i> Monday-Saturday: 8:00 AM - 8:00 PM</p>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> BLASTICAKES & CRAFTS. All rights reserved.</p>
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
            once: true,
            offset: 100
        });
        
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            document.getElementById('navMenu').classList.toggle('active');
            
            // Change icon based on menu state
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-bars')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    </script>
</body>
</html>