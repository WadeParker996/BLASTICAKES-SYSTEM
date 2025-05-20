<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$success_message = '';
$error_message = '';

// Handle order status update
if (isset($_POST['update_status']) && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Update order status and notify customer
    if (update_order_status($order_id, $new_status)) {
        $success_message = "Order status updated successfully! Customer has been notified.";
    } else {
        $error_message = "Error updating order status: " . mysqli_error($conn);
    }
}

// Get order details if order_id is provided
if (isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    
    // Get order information
    $order_sql = "SELECT o.*, u.username, u.email FROM orders o
                  JOIN users u ON o.user_id = u.id
                  WHERE o.id = $order_id";
    $order_result = mysqli_query($conn, $order_sql);
    
    if ($order_result && mysqli_num_rows($order_result) > 0) {
        $order = mysqli_fetch_assoc($order_result);
        
        // Get order items
        $items_sql = "SELECT oi.*, p.name, p.image, p.price FROM order_items oi
                      JOIN products p ON oi.product_id = p.id
                      WHERE oi.order_id = $order_id";
        $items_result = mysqli_query($conn, $items_sql);
        $order_items = [];
        
        if ($items_result) {
            while ($item = mysqli_fetch_assoc($items_result)) {
                $order_items[] = $item;
            }
        }
    } else {
        $error_message = "Order not found.";
    }
}

// Get all orders for the orders list page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Filter by status if provided
$status_filter = "";
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $status_filter = "WHERE o.status = '$status'";
}

// Search by order ID or customer name
$search_filter = "";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    if (empty($status_filter)) {
        $search_filter = "WHERE (o.id LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
    } else {
        $search_filter = "AND (o.id LIKE '%$search%' OR u.username LIKE '%$search%' OR u.email LIKE '%$search%')";
    }
}

// Get total number of orders for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o
              JOIN users u ON o.user_id = u.id
              $status_filter $search_filter";
$count_result = mysqli_query($conn, $count_sql);
$total_orders = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_orders / $items_per_page);

// Get orders with pagination
$orders_sql = "SELECT o.*, u.username, u.email FROM orders o
               JOIN users u ON o.user_id = u.id
               $status_filter $search_filter
              ORDER BY o.order_date DESC 
               LIMIT $offset, $items_per_page";
$orders_result = mysqli_query($conn, $orders_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - BLASTICAKES & CRAFTS Admin</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ffa5a5;
            --accent-color: #ffd3d3;
            --dark-color: #333333;
            --light-color: #f9f9f9;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            background-color: #f5f5f5;
            color: var(--dark-color);
        }
        
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 2rem;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
        }
        
        nav li {
            margin-left: 20px;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        nav a:hover, nav a.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .page-title {
            margin: 30px 0;
            text-align: center;
            color: var(--primary-color);
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
        }
        
        .page-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-color);
            margin: 10px auto;
            border-radius: 2px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.95rem;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #3d8b40;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--success-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--danger-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-link:hover {
            color: var(--secondary-color);
            transform: translateX(-5px);
        }
        
        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .order-meta-item {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .order-meta-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .order-meta-label i {
            color: var(--primary-color);
        }
        
        .order-meta-value {
            color: #333;
            font-size: 1.05rem;
        }
        
        .order-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
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
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .order-items {
            margin-top: 30px;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #444;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-color);
        }
        
        .order-item {
            display: flex;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .order-item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .order-item-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .order-item-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #333;
        }
        
        .order-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 5px;
        }
        
        .order-item-price, .order-item-quantity {
            color: #666;
            font-size: 0.95rem;
        }
        
        .order-item-total {
                        font-weight: 600;
            margin-top: 10px;
            color: var(--primary-color);
            font-size: 1.05rem;
        }
        
        .order-total {
            font-size: 1.3rem;
            font-weight: 700;
            text-align: right;
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            color: var(--primary-color);
        }
        
        .status-form {
            margin-top: 30px;
            padding: 25px;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            border: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        select, input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            color: #333;
            transition: var(--transition);
            background-color: white;
        }
        
        select:focus, input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        }
        
        .orders-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
        }
        
        .orders-table th, .orders-table td {
            padding: 15px;
            text-align: left;
        }
        
        .orders-table th {
            background-color: #f5f5f5;
            color: #444;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .orders-table tr {
            transition: var(--transition);
        }
        
        .orders-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .orders-table td {
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .orders-table tr:last-child td {
            border-bottom: none;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            text-decoration: none;
            color: #555;
            transition: var(--transition);
            min-width: 40px;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
            border-color: #ccc;
        }
        
        .pagination .active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .disabled {
            color: #aaa;
            cursor: not-allowed;
            background-color: #f9f9f9;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            align-items: flex-end;
        }
        
        .filter-form select, .filter-form input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            flex: 1;
            min-width: 150px;
        }
        
        .filter-form button {
            padding: 10px 20px;
        }
        
        .filter-form .btn {
            margin-top: 0;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-btn {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: var(--border-radius);
            z-index: 1000;
            margin-top: 10px;
        }
        
        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: var(--transition);
        }
        
        .dropdown-content a:hover {
            background-color: #f5f5f5;
            color: var(--primary-color);
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #777;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            nav li {
                margin: 5px;
            }
            
            .order-meta {
                grid-template-columns: 1fr;
            }
            
            .order-item {
                flex-direction: column;
            }
            
            .order-item-image {
                width: 100%;
                height: auto;
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form select, .filter-form input, .filter-form button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="admin.php" class="logo">
                    <i class="fas fa-birthday-cake"></i>
                    BLASTICAKES & CRAFTS
                </a>
                <nav>
                    <ul>
                        <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="add_product.php"><i class="fas fa-box"></i> Products</a></li>
                        <li><a href="admin_orders.php" class="active"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="scan_order.php"><i class="fas fa-qrcode"></i> Scan</a></li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-btn">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?> <i class="fas fa-caret-down"></i>
                            </a>
                            <div class="dropdown-content">
                                <a href="admin_change_password.php"><i class="fas fa-key"></i> Change Password</a>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($_GET['order_id'])): ?>
            <!-- Order Details View -->
            <a href="admin_orders.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Orders List
            </a>
            
            <h1 class="page-title">Order #<?php echo $order_id; ?> Details</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($order)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle"></i> Order Information</h3>
                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <i class="fas fa-circle"></i> <?php echo $order['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="order-meta">
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-calendar-alt"></i> Order Date
                                </div>
                                <div class="order-meta-value">
                                    <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-user"></i> Customer
                                </div>
                                <div class="order-meta-value">
                                    <?php echo htmlspecialchars($order['username']); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-envelope"></i> Email
                                </div>
                                <div class="order-meta-value">
                                    <?php echo htmlspecialchars($order['email']); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-money-bill-wave"></i> Total Amount
                                </div>
                                <div class="order-meta-value">
                                    ₱<?php echo number_format($order['total_amount'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-truck"></i> Fulfillment Method
                                </div>
                                <div class="order-meta-value">
                                    <?php echo ucfirst(htmlspecialchars($order['fulfillment_option'])); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-credit-card"></i> Payment Method
                                </div>
                                <div class="order-meta-value">
                                    <?php echo htmlspecialchars($order['payment_method']); ?>
                                </div>
                            </div>
                            
                            <div class="order-meta-item">
                                <div class="order-meta-label">
                                    <i class="fas fa-phone"></i> Phone
                                </div>
                                <div class="order-meta-value">
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </div>
                            </div>
                            
                            <?php if ($order['fulfillment_option'] == 'delivery'): ?>
                                <div class="order-meta-item">
                                    <div class="order-meta-label">
                                        <i class="fas fa-map-marker-alt"></i> Delivery Address
                                    </div>
                                    <div class="order-meta-value">
                                        <?php echo nl2br(htmlspecialchars($order['address'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="order-items">
                            <h3 class="section-title"><i class="fas fa-shopping-cart"></i> Order Items</h3>
                            
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item">
                                    <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="order-item-image">
                                    <div class="order-item-details">
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="order-item-meta">
                                            <div class="order-item-price"><i class="fas fa-tag"></i> ₱<?php echo number_format($item['price'], 2); ?></div>
                                            <div class="order-item-quantity"><i class="fas fa-cubes"></i> Quantity: <?php echo $item['quantity']; ?></div>
                                        </div>
                                        <div class="order-item-total">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="order-total">
                                <i class="fas fa-receipt"></i> Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                        
                        <div class="status-form">
                            <h3 class="section-title"><i class="fas fa-edit"></i> Update Order Status</h3>
                                                        <form method="post" action="admin_orders.php?order_id=<?php echo $order_id; ?>">
                                <div class="form-group">
                                    <label for="status">New Status:</label>
                                    <select id="status" name="status" required>
                                        <option value="Pending" <?php echo $order['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Processing" <?php echo $order['status'] == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Completed" <?php echo $order['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="Cancelled" <?php echo $order['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-sync-alt"></i> Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Orders List View -->
            <h1 class="page-title">Manage Orders</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Orders List</h3>
                    <div>
                        <a href="export_orders.php" class="btn btn-sm">
                            <i class="fas fa-file-export"></i> Export Orders
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" action="admin_orders.php" class="filter-form">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="status">Filter by Status</label>
                            <select name="status" id="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo isset($_GET['status']) && $_GET['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Processing" <?php echo isset($_GET['status']) && $_GET['status'] == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Completed" <?php echo isset($_GET['status']) && $_GET['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="search">Search</label>
                            <input type="text" name="search" id="search" placeholder="Order ID, customer name or email" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="admin_orders.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Reset
                            </a>
                        </div>
                    </form>
                    
                    <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Fulfillment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                                        <tr>
                                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($order['username']); ?></div>
                                                <small><?php echo htmlspecialchars($order['email']); ?></small>
                                            </td>
                                            <td><?php echo date('M j, Y, g:i a', strtotime($order['order_date'])); ?></td>
                                            <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                    <i class="fas fa-circle"></i> <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo ucfirst($order['fulfillment_option']); ?></td>
                                            <td>
                                                <a href="admin_orders.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="admin_orders.php?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php else: ?>
                                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                                <?php endif; ?>
                                
                                <?php
                                // Calculate range of page numbers to display
                                $range = 2; // Display 2 pages before and after current page
                                $start_page = max(1, $page - $range);
                                $end_page = min($total_pages, $page + $range);
                                
                                // Always show first page
                                if ($start_page > 1) {
                                    echo '<a href="admin_orders.php?page=1' . 
                                        (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . 
                                        (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') . 
                                        '">1</a>';
                                    
                                    if ($start_page > 2) {
                                        echo '<span class="disabled">...</span>';
                                    }
                                }
                                
                                // Display page numbers
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    if ($i == $page) {
                                        echo '<span class="active">' . $i . '</span>';
                                    } else {
                                        echo '<a href="admin_orders.php?page=' . $i . 
                                            (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . 
                                            (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') . 
                                            '">' . $i . '</a>';
                                    }
                                }
                                
                                // Always show last page
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="disabled">...</span>';
                                    }
                                    
                                    echo '<a href="admin_orders.php?page=' . $total_pages . 
                                        (isset($_GET['status']) ? '&status=' . $_GET['status'] : '') . 
                                        (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') . 
                                        '">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="admin_orders.php?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <p>No orders found.</p>
                            <?php if (isset($_GET['status']) || isset($_GET['search'])): ?>
                                <a href="admin_orders.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-hide success message after 5 seconds
        setTimeout(function() {
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                successMessage.style.opacity = '0';
                successMessage.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 500);
            }
        }, 5000);
    </script>
</body>
</html>


