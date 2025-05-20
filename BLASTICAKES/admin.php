<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Handle product deletion
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    
    // Check if product exists
    $check_sql = "SELECT * FROM products WHERE id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $product_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete the product
        $delete_sql = "DELETE FROM products WHERE id = ?";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $product_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "Product deleted successfully!";
        } else {
            $error_message = "Error deleting product: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Product not found!";
    }
}

// Get date range for revenue filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Default to today if no dates are provided
if (empty($start_date)) {
    $start_date = date('Y-m-d');
}
if (empty($end_date)) {
    $end_date = date('Y-m-d');
}

// Get total number of orders
$orders_sql = "SELECT COUNT(*) as total FROM orders";
$orders_result = mysqli_query($conn, $orders_sql);
$total_orders = mysqli_fetch_assoc($orders_result)['total'];

// Get total number of products
$products_sql = "SELECT COUNT(*) as total FROM products";
$products_result = mysqli_query($conn, $products_sql);
$total_products = mysqli_fetch_assoc($products_result)['total'];

// Get total number of users
$users_sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0";
$users_result = mysqli_query($conn, $users_sql);
$total_users = mysqli_fetch_assoc($users_result)['total'];

// Get total revenue with date filter
$revenue_sql = "SELECT SUM(total_amount) as total FROM orders WHERE status != 'Cancelled'";
if (!empty($start_date) && !empty($end_date)) {
    $revenue_sql .= " AND DATE(order_date) BETWEEN ? AND ?";
}
$stmt = mysqli_prepare($conn, $revenue_sql);
if (!empty($start_date) && !empty($end_date)) {
    mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
}
mysqli_stmt_execute($stmt);
$revenue_result = mysqli_stmt_get_result($stmt);
$total_revenue = mysqli_fetch_assoc($revenue_result)['total'] ?? 0;

// Get recent orders
$recent_orders_sql = "SELECT o.*, u.username FROM orders o
                      JOIN users u ON o.user_id = u.id
                      ORDER BY o.order_date DESC
                      LIMIT 5";
$recent_orders_result = mysqli_query($conn, $recent_orders_sql);

// Get orders by status
$status_sql = "SELECT status, COUNT(*) as count FROM orders GROUP BY status";
$status_result = mysqli_query($conn, $status_sql);
$status_counts = [];
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $status_counts[$row['status']] = $row['count'];
    }
}

// Get top selling products
$top_products_sql = "SELECT p.id, p.name, p.image, SUM(oi.quantity) as total_sold
                     FROM products p
                     JOIN order_items oi ON p.id = oi.product_id
                     JOIN orders o ON oi.order_id = o.id
                     WHERE o.status != 'Cancelled'
                     GROUP BY p.id
                     ORDER BY total_sold DESC
                     LIMIT 5";
$top_products_result = mysqli_query($conn, $top_products_sql);

// Get recent reviews
$reviews_sql = "SELECT r.*, o.id as order_id, u.username, p.name as product_name
                FROM reviews r
                JOIN orders o ON r.order_id = o.id
                JOIN users u ON r.user_id = u.id
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                GROUP BY r.id
                ORDER BY r.created_at DESC
                LIMIT 5";
$reviews_result = mysqli_query($conn, $reviews_sql);

// Get all products for management
$all_products_sql = "SELECT * FROM products ORDER BY id DESC LIMIT 10";
$all_products_result = mysqli_query($conn, $all_products_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        nav a:hover {
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
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
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
        }
        
        .card-body {
            padding: 20px;
        }
        
        .stat-card {
            text-align: center;
            padding: 30px 20px;
        }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #777;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .grid-span-2 {
            grid-column: span 2;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
            padding: 20px;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #d32f2f;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .date-filter {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .date-filter form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 500;
            font-size: 0.9rem;
            color: #555;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--accent-color);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .order-status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
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
        
        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .product-card {
            background-color: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        
        .product-image-container {
            height: 120px;
            overflow: hidden;
            position: relative;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.1);
        }
        
        .product-details {
            padding: 15px;
            text-align: center;
        }
        
        .product-name {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
            display: -webkit-box;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 42px;
        }
        
        .product-sold {
            font-size: 0.8rem;
            color: #777;
            font-weight: 500;
        }
        
        .view-all {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .view-all:hover {
            color: var(--secondary-color);
        }
        
        .reviews-list {
            margin-top: 20px;
        }
        
        .review-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: var(--transition);
        }
        
        .review-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .review-rating i {
            color: #ffb300;
            margin-right: 2px;
        }
        
        .review-meta {
            color: #777;
            font-size: 0.8rem;
        }
        
        .review-content {
            margin-bottom: 15px;
            color: #555;
            font-size: 0.95rem;
        }
        
        .review-footer {
            text-align: right;
        }
        
        .review-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .review-footer a:hover {
            color: var(--secondary-color);
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .product-table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 1px solid #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            width: 400px;
            max-width: 90%;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            padding: 20px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            border-top-left-radius: var(--border-radius);
            border-top-right-radius: var(--border-radius);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close:hover {
            color: #f8f9fa;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .dropdown-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            border-radius: var(--border-radius);
            z-index: 1000;
            animation: fadeIn 0.2s;
        }
        
        .dropdown-content a {
            color: var(--dark-color) !important;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .dropdown-content a i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .dropdown-content a:hover {
            background-color: #f5f5f5;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .grid-span-2 {
                grid-column: span 1;
            }
            
            .dashboard {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            nav li {
                margin: 0;
            }
            
            .date-filter form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }
        
        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="admin.php" class="logo">
                    <i class="fas fa-birthday-cake"></i> BLASTICAKES & CRAFTS
                </a>
                <nav>
                    <ul>
                        <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="add_product.php"><i class="fas fa-box"></i> Products</a></li>
                        <li><a href="admin_orders.php"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="scan_order.php"><i class="fas fa-qrcode"></i> Scan</a></li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-btn">
                                <i class="fas fa-user-circle"></i> <?php echo $username; ?> <i class="fas fa-caret-down"></i>
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
        <h1 class="page-title">Admin Dashboard</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Date Filter for Revenue -->
        <div class="date-filter">
            <form action="admin.php" method="GET">
                <div class="form-group">
                    <label for="start_date">Revenue From:</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="form-group">
                    <label for="end_date">To:</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="admin.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="dashboard">
            <!-- Stats Cards -->
            <div class="card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            
            <div class="card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-birthday-cake"></i>
                </div>
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Products</div>
            </div>
            
            <div class="card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $total_users; ?></div>
                <div class="stat-label">Customers</div>
            </div>
            
            <div class="card stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">₱<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">
                    Total Revenue
                    <?php if (!empty($start_date) && !empty($end_date)): ?>
                        <br><small>(<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)</small>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Orders by Status Chart -->
            <div class="card grid-span-2">
                <div class="card-header">
                    <h3 class="card-title">Orders by Status</h3>
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="chart-container">
                    <canvas id="ordersChart"></canvas>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="card grid-span-2">
                <div class="card-header">
                    <h3 class="card-title">Recent Orders</h3>
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="card-body">
                    <?php if ($recent_orders_result && mysqli_num_rows($recent_orders_result) > 0): ?>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = mysqli_fetch_assoc($recent_orders_result)): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['username']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                                    <?php echo $order['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="admin_orders.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="admin_orders.php" class="view-all">
                            <i class="fas fa-arrow-right"></i> View All Orders
                        </a>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                            <p>No recent orders.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Selling Products -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Top Selling Products</h3>
                    <i class="fas fa-crown"></i>
                </div>
                <div class="card-body">
                    <?php if ($top_products_result && mysqli_num_rows($top_products_result) > 0): ?>
                        <div class="product-list">
                            <?php while ($product = mysqli_fetch_assoc($top_products_result)): ?>
                                <div class="product-card">
                                    <div class="product-image-container">
                                        <img src="images/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="product-image">
                                    </div>
                                    <div class="product-details">
                                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div class="product-sold">
                                            <i class="fas fa-shopping-cart"></i> <?php echo $product['total_sold']; ?> sold
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <a href="add_product.php" class="view-all">
                            <i class="fas fa-arrow-right"></i> View All Products
                        </a>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                            <p>No product sales data available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Reviews -->
            <div class="card grid-span-2">
                <div class="card-header">
                    <h3 class="card-title">Recent Customer Reviews</h3>
                    <i class="fas fa-star"></i>
                </div>
                <div class="card-body">
                    <?php if ($reviews_result && mysqli_num_rows($reviews_result) > 0): ?>
                        <div class="reviews-list">
                            <?php while ($review = mysqli_fetch_assoc($reviews_result)): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-meta">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($review['username']); ?> | 
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <p><?php echo htmlspecialchars($review['comment']); ?></p>
                                    </div>
                                    <div class="review-footer">
                                        <a href="admin_orders.php?order_id=<?php echo $review['order_id']; ?>">
                                            <i class="fas fa-eye"></i> View Order #<?php echo $review['order_id']; ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-comment-alt" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                            <p>No reviews yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Product Management Section -->
        <div class="product-management">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Product Management</h3>
                    <a href="add_product.php" class="btn">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($all_products_result && mysqli_num_rows($all_products_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="product-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Name</th>
                                        <th>Price</th>
                                        <th>Category</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($product = mysqli_fetch_assoc($all_products_result)): ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td><img src="images/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>"></td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td>₱<?php echo number_format($product['price'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                        <i class="fas fa-trash-alt"></i> Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="add_product.php" class="view-all">
                            <i class="fas fa-arrow-right"></i> Manage All Products
                        </a>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-box" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                            <p>No products available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Delete</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p><i class="fas fa-exclamation-triangle" style="color: var(--danger-color); margin-right: 10px;"></i> Are you sure you want to delete the product: <strong><span id="productName"></span></strong>?</p>
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="delete_product" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Orders by Status Chart
        const statusLabels = <?php echo json_encode(array_keys($status_counts)); ?>;
        const statusData = <?php echo json_encode(array_values($status_counts)); ?>;
        const statusColors = {
            'Pending': '#ffc107',
            'Processing': '#007bff',
            'Completed': '#28a745',
            'Cancelled': '#dc3545'
        };
        
        const ctx = document.getElementById('ordersChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusLabels.map(label => statusColors[label] || '#6c757d'),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                cutout: '65%'
            }
        });
        
        // Delete confirmation modal
        const modal = document.getElementById('deleteModal');
        
        function confirmDelete(productId, productName) {
            document.getElementById('productName').textContent = productName;
            document.getElementById('deleteProductId').value = productId;
            modal.style.display = 'block';
        }
        
        function closeModal() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>