<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Initialize variables
$success_message = '';
$error_message = '';

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    
    // Check if user exists and is not an admin
    $check_sql = "SELECT * FROM users WHERE id = ? AND is_admin = 0";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "i", $user_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        // Delete the user
        $delete_sql = "DELETE FROM users WHERE id = ? AND is_admin = 0";
        $delete_stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
        
        if (mysqli_stmt_execute($delete_stmt)) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Error deleting user: " . mysqli_error($conn);
        }
    } else {
        $error_message = "User not found or cannot delete admin users!";
    }
}

// Handle user status toggle (active/inactive)
if (isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $new_status = ($status == 1) ? 0 : 1;
    
    $update_sql = "UPDATE users SET is_active = ? WHERE id = ? AND is_admin = 0";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $new_status, $user_id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $status_text = ($new_status == 1) ? "activated" : "deactivated";
        $success_message = "User account $status_text successfully!";
    } else {
        $error_message = "Error updating user status: " . mysqli_error($conn);
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = " AND (username LIKE '%$search%' OR email LIKE '%$search%')";
}

// Get total number of users for pagination
$count_sql = "SELECT COUNT(*) as total FROM users WHERE is_admin = 0" . $search_condition;
$count_result = mysqli_query($conn, $count_sql);
$total_users = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_users / $limit);

// Get users with pagination and search
$users_sql = "SELECT * FROM users WHERE is_admin = 0" . $search_condition . " ORDER BY id DESC LIMIT ?, ?";
$users_stmt = mysqli_prepare($conn, $users_sql);
mysqli_stmt_bind_param($users_stmt, "ii", $offset, $limit);
mysqli_stmt_execute($users_stmt);
$users_result = mysqli_stmt_get_result($users_stmt);

// Get user details if viewing a specific user
$user = null;
$user_orders = null;
if (isset($_GET['user_id'])) {
    $user_id = mysqli_real_escape_string($conn, $_GET['user_id']);
    
    // Get user details
    $user_sql = "SELECT * FROM users WHERE id = ? AND is_admin = 0";
    $user_stmt = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    
    if (mysqli_num_rows($user_result) > 0) {
        $user = mysqli_fetch_assoc($user_result);
        
        // Get user's orders
        $orders_sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 10";
        $orders_stmt = mysqli_prepare($conn, $orders_sql);
        mysqli_stmt_bind_param($orders_stmt, "i", $user_id);
        mysqli_stmt_execute($orders_stmt);
        $user_orders = mysqli_stmt_get_result($orders_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --dark-color: #292f36;
            --light-color: #f7f7f7;
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
            color: #333;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 0;
        }
        
        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        nav ul {
            display: flex;
            list-style: none;
            gap: 20px;
        }
        
        nav a {
            text-decoration: none;
            color: #555;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        nav a:hover {
            color: var(--primary-color);
            background-color: rgba(255, 107, 107, 0.1);
        }
        
        nav a.active {
            color: var(--primary-color);
            background-color: rgba(255, 107, 107, 0.1);
            font-weight: 600;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin: 30px 0 20px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px 25px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: #fff;
        }
        
        .btn-info {
            background-color: var(--info-color);
        }
        
        .success-message {
            background-color: rgba(46, 204, 113, 0.1);
            border-left: 4px solid var(--success-color);
            color: #2ecc71;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error-message {
            background-color: rgba(231, 76, 60, 0.1);
            border-left: 4px solid var(--danger-color);
            color: #e74c3c;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #555;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-link:hover {
            color: var(--primary-color);
        }
        
        .users-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 25px;
        }
        
        .users-table th, .users-table td {
            padding: 15px;
            text-align: left;
        }
        
        .users-table th {
            background-color: #f5f5f5;
            color: #444;
            font-weight: 600;
            border-bottom: 2px solid #eee;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .users-table tr {
            transition: var(--transition);
        }
        
        .users-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .users-table td {
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .user-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success-color);
        }
        
        .status-inactive {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
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
        
        .filter-form input {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            flex: 1;
            min-width: 200px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }
        
        .filter-form input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
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
        
        .user-details {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        .user-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 20px;
            background-color: #f9f9f9;
            border-radius: var(--border-radius);
            text-align: center;
        }
        
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 20px;
        }
        
        .user-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: #777;
            margin-bottom: 20px;
        }
        
        .user-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .user-meta-item {
            background-color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .user-meta-label {
            font-size: 0.85rem;
            color: #777;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-meta-value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
        }
        
        .user-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            justify-content: center;
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
        
        .order-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: rgba(243, 156, 18, 0.15);
            color: var(--warning-color);
        }
        
        .status-processing {
            background-color: rgba(52, 152, 219, 0.15);
            color: var(--info-color);
        }
        
        .status-completed {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success-color);
        }
        
        .status-cancelled {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
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
            border-radius: var(--border-radius);
            width: 500px;
            max-width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: slideIn 0.3s;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close:hover {
            color: var(--danger-color);
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 25px;
            border-top: 1px solid #eee;
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
            
            .user-meta {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form input, .filter-form button {
                width: 100%;
            }
            
            .user-actions {
                flex-direction: column;
            }
            
            .user-actions .btn {
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
                        <li><a href="admin_orders.php"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="admin_users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
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
        <?php if (isset($_GET['user_id']) && $user): ?>
            <!-- User Detail View -->
            <a href="admin_users.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Users List
            </a>
            
            <h1 class="page-title">User Profile</h1>
            
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
                    <h3 class="card-title"><i class="fas fa-user"></i> User Information</h3>
                    <div>
                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                            <i class="fas fa-trash-alt"></i> Delete User
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="user-details">
                        <div class="user-profile">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <h2 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
<td>
    <span class="user-status status-<?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'active' : 'inactive'; ?>">
        <i class="fas fa-circle"></i>
        <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
    </span>
</td>
                            
                            <div class="user-actions">
    <form method="POST" action="admin_users.php?user_id=<?php echo $user['id']; ?>">
        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
        <input type="hidden" name="status" value="<?php echo isset($user['is_active']) ? $user['is_active'] : 0; ?>">
        <button type="submit" name="toggle_status" class="btn <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'btn-warning' : 'btn-success'; ?>">
            <i class="fas <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
            <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'Deactivate Account' : 'Activate Account'; ?>
        </button>
    </form>
</div>
                        
                        <div class="user-meta">
                            <div class="user-meta-item">
                                <div class="user-meta-label">
                                    <i class="fas fa-calendar-alt"></i> Registration Date
                                </div>
                                <div class="user-meta-value">
                                    <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                                </div>
                            </div>
                            
                            <div class="user-meta-item">
    <div class="user-meta-label">
        <i class="fas fa-shopping-bag"></i> Total Orders
    </div>
    <div class="user-meta-value">
        <?php 
            // Make sure $user_orders is defined
            if (isset($user_orders) && $user_orders) {
                $order_count = mysqli_num_rows($user_orders);
            } else {
                $order_count = 0;
            }
            echo $order_count;
        ?>
    </div>
</div>

                            
                            <div class="user-meta-item">
                                <div class="user-meta-label">
                                    <i class="fas fa-clock"></i> Last Login
                                </div>
                                <div class="user-meta-value">
                                    <?php 
                                        echo isset($user['last_login']) && $user['last_login'] ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never';
                                    ?>
                                </div>
                            </div>
                            
                            <div class="user-meta-item">
                                <div class="user-meta-label">
                                    <i class="fas fa-map-marker-alt"></i> Address
                                </div>
                                <div class="user-meta-value">
                                    <?php 
                                        echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not provided';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 40px;">
                        <h3 class="card-title" style="margin-bottom: 20px;"><i class="fas fa-shopping-bag"></i> Order History</h3>
                        
                        <?php if ($user_orders && mysqli_num_rows($user_orders) > 0): ?>
                            <div class="table-responsive">
                                <table class="orders-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Fulfillment</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($order = mysqli_fetch_assoc($user_orders)): ?>
                                            <tr>
                                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                                <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
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
                            
                            <?php if (mysqli_num_rows($user_orders) >= 10): ?>
                                <div style="text-align: center; margin-top: 20px;">
                                    <a href="admin_orders.php?user_id=<?php echo $user['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-list"></i> View All Orders
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <p>This user hasn't placed any orders yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Users List View -->
            <h1 class="page-title">Manage Users</h1>
            
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
                    <h3 class="card-title"><i class="fas fa-users"></i> Users List</h3>
                    <div>
                        <a href="export_users.php" class="btn btn-sm">
                            <i class="fas fa-file-export"></i> Export Users
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="get" action="admin_users.php" class="filter-form">
                        <input type="text" name="search" placeholder="Search by username or email" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="admin_users.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </form>
                    
                    <?php if ($users_result && mysqli_num_rows($users_result) > 0): ?>
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <div style="width: 35px; height: 35px; background-color: var(--primary-color); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                </div>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
<td>
    <span class="user-status status-<?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'active' : 'inactive'; ?>">
        <i class="fas fa-circle"></i>
        <?php echo isset($user['is_active']) && $user['is_active'] == 1 ? 'Active' : 'Inactive'; ?>
    </span>
</td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="admin_users.php?user_id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
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
                                    <a href="admin_users.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
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
                                    echo '<a href="admin_users.php?page=1' . 
                                        (!empty($search) ? '&search=' . urlencode($search) : '') . 
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
                                        echo '<a href="admin_users.php?page=' . $i . 
                                            (!empty($search) ? '&search=' . urlencode($search) : '') . 
                                            '">' . $i . '</a>';
                                    }
                                }
                                
                                // Always show last page
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="disabled">...</span>';
                                    }
                                    
                                    echo '<a href="admin_users.php?page=' . $total_pages . 
                                        (!empty($search) ? '&search=' . urlencode($search) : '') . 
                                        '">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="admin_users.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>No users found.</p>
                            <?php if (!empty($search)): ?>
                                <a href="admin_users.php" class="btn btn-secondary">
                                    <i class="fas fa-sync-alt"></i> Clear Search
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the user: <strong><span id="userName"></span></strong>?</p>
                <p>This action cannot be undone. All user data including order history will be permanently deleted.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="admin_users.php<?php echo isset($_GET['user_id']) ? '?user_id=' . $_GET['user_id'] : ''; ?>">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation modal
        const modal = document.getElementById('deleteModal');
        
        function confirmDelete(userId, userName) {
            document.getElementById('userName').textContent = userName;
            document.getElementById('deleteUserId').value = userId;
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