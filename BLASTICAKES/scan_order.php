<?php
// Start session
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';

// Get user info
$username = $_SESSION['username'];

// Initialize variables
$order_data = null;
$order_details = null;
$order_items = [];
$customer_info = null;
$scan_result = '';

// Process scanned QR code data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = trim($_POST['qr_data']);
    
    // Try to decode the JSON data from QR code
    $decoded_data = json_decode($qr_data, true);
    
    if ($decoded_data && isset($decoded_data['order_id'])) {
        $order_id = $decoded_data['order_id'];
        
        // Get order details
        $sql = "SELECT o.*, u.username, u.email as user_email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $order_details = mysqli_fetch_assoc($result);
            
            // Get order items
            $sql = "SELECT oi.*, p.name, p.image 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($row = mysqli_fetch_assoc($result)) {
                $order_items[] = $row;
            }
            
            $scan_result = 'success';
            $order_data = $decoded_data;
        } else {
            $scan_result = 'error';
        }
    } else {
        $scan_result = 'invalid';
    }
}

// Process order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Refresh order details
        $sql = "SELECT o.*, u.username, u.email as user_email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $order_details = mysqli_fetch_assoc($result);
            
            // Get order items again
            $sql = "SELECT oi.*, p.name, p.image 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $order_items = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $order_items[] = $row;
            }
            
            $scan_result = 'success';
            $status_updated = true;
        }
    }
}

// Set page title
$page_title = "Scan Order QR";
include 'includes/admin_header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><i class="fas fa-qrcode"></i> Scan Order QR Code</h1>
        <div class="page-actions">
            <a href="admin.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Admin Panel
            </a>
        </div>
    </div>

    <div class="card scanner-card">
        <div class="card-header">
            <h2><i class="fas fa-camera"></i> QR Code Scanner</h2>
        </div>
        <div class="card-body">
            <?php if (isset($status_updated) && $status_updated): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Order status has been updated successfully.
                </div>
            <?php endif; ?>

            <div class="scanner-tabs">
                <div class="tab active" data-tab="camera">
                    <i class="fas fa-camera"></i> Camera Scanner
                </div>
                <div class="tab" data-tab="manual">
                    <i class="fas fa-keyboard"></i> Manual Entry
                </div>
            </div>

            <div class="tab-content">
                <div class="tab-pane active" id="camera-tab">
                    <div class="scanner-container">
                        <div class="video-container">
                            <video id="qr-video" playsinline></video>
                            <div class="scanner-overlay">
                                <div class="scanner-frame"></div>
                                <div class="scanner-info">Position QR code within the frame</div>
                            </div>
                        </div>
                        <div class="scanner-controls">
                            <button id="start-button" class="btn btn-primary">
                                <i class="fas fa-play"></i> Start Scanner
                            </button>
                            <button id="stop-button" class="btn btn-secondary" disabled>
                                <i class="fas fa-stop"></i> Stop Scanner
                            </button>
                            <div class="camera-select-container">
                                <select id="camera-select" class="form-control">
                                    <option value="">Loading cameras...</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane" id="manual-tab">
                    <form action="scan_order.php" method="post" class="manual-form">
                        <div class="form-group">
                            <label for="qr_data">Enter QR Code Data:</label>
                            <textarea id="qr_data" name="qr_data" class="form-control" placeholder="Paste QR code data here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Process QR Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($scan_result === 'success'): ?>
        <div class="order-result-container">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Order #<?php echo $order_details['id']; ?> found and loaded successfully.
            </div>
            
            <div class="card order-card">
                <div class="card-header">
                    <div class="order-header">
                        <h2><i class="fas fa-shopping-bag"></i> Order #<?php echo $order_details['id']; ?></h2>
                        <div class="order-status status-<?php echo $order_details['status']; ?>">
                            <?php echo ucfirst($order_details['status']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <div class="order-meta">
                        <div class="order-meta-item">
                            <i class="far fa-calendar-alt"></i>
                            <div class="meta-label">Order Date</div>
                            <div class="meta-value"><?php echo date('F j, Y, g:i a', strtotime($order_details['order_date'])); ?></div>
                        </div>
                        
                        <div class="order-meta-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div class="meta-label">Total Amount</div>
                            <div class="meta-value">₱<?php echo number_format($order_details['total_amount'], 2); ?></div>
                        </div>
                        
                        <div class="order-meta-item">
                            <i class="fas fa-credit-card"></i>
                            <div class="meta-label">Payment Method</div>
                            <div class="meta-value"><?php echo $order_details['payment_method']; ?></div>
                        </div>
                    </div>
                    
                    <div class="customer-info-card">
                        <div class="customer-info-header">
                            <i class="fas fa-user"></i> Customer Information
                        </div>
                        <div class="customer-info-body">
                            <div class="customer-info-grid">
                                <div class="customer-info-item">
                                    <div class="info-label">Username</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order_details['username']); ?></div>
                                </div>
                                
                                <div class="customer-info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order_details['user_email']); ?></div>
                                </div>
                                
                                <div class="customer-info-item">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order_details['phone']); ?></div>
                                </div>
                                
                                <div class="customer-info-item">
                                    <div class="info-label">Address</div>
                                    <div class="info-value"><?php echo htmlspecialchars($order_details['address']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="order-items-section">
                        <h3><i class="fas fa-box-open"></i> Order Items</h3>
                        <div class="table-responsive">
                            <table class="table order-items-table">
                                <thead>
                                    <tr>
                                        <th>Image</th>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td class="product-image-cell">
                                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" class="product-img">
                                            </td>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" class="text-right"><strong>Total:</strong></td>
                                        <td><strong>₱<?php echo number_format($order_details['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="status-update-section">
                        <h3><i class="fas fa-sync-alt"></i> Update Order Status</h3>
                        <form action="scan_order.php" method="post" class="status-form">
                            <input type="hidden" name="order_id" value="<?php echo $order_details['id']; ?>">
                            <div class="status-form-controls">
                                <div class="status-select-container">
                                    <select name="status" class="form-control status-select">
                                        <option value="pending" <?php echo $order_details['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order_details['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo $order_details['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $order_details['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                                <button type="button" class="btn btn-secondary" id="print-order">
                                    <i class="fas fa-print"></i> Print Order
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($scan_result === 'error'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> Order not found in the database.
        </div>
    <?php elseif ($scan_result === 'invalid'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Warning!</strong> Invalid QR code data. Please scan a valid order QR code.
        </div>
    <?php endif; ?>
</div>

<style>
    :root {
        --primary: #ff6b6b;
        --primary-dark: #ff5252;
        --secondary: #6c757d;
        --success: #28a745;
        --danger: #dc3545;
        --warning: #ffc107;
        --info: #17a2b8;
        --light: #f8f9fa;
        --dark: #343a40;
        --border-radius: 0.25rem;
        --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        --transition: all 0.3s ease;
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

    /* Page Layout */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.5rem;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .page-header h1 {
        font-size: 1.8rem;
        margin: 0;
        color: #333;
        display: flex;
        align-items: center;
    }

    .page-header h1 i {
        margin-right: 0.5rem;
        color: var(--primary);
    }

    .page-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Card Styles */
    .card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1rem 1.5rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #eee;
    }

    .card-header h2 {
        margin: 0;
        font-size: 1.25rem;
        color: #333;
        display: flex;
        align-items: center;
    }

    .card-header h2 i {
        margin-right: 0.5rem;
        color: var(--primary);
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Scanner Card */
    .scanner-card {
        max-width: 800px;
        margin: 0 auto 2rem;
    }

    .scanner-tabs {
        display: flex;
        border-bottom: 1px solid #eee;
        margin-bottom: 1.5rem;
    }

    .tab {
        padding: 0.75rem 1.25rem;
        cursor: pointer;
        font-weight: 500;
        color: #666;
        border-bottom: 2px solid transparent;
        transition: var(--transition);
        display: flex;
        align-items: center;
    }

    .tab i {
        margin-right: 0.5rem;
    }

    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }

    .tab:hover:not(.active) {
        color: var(--primary-dark);
        background-color: rgba(255, 107, 107, 0.05);
    }

    .tab-content {
        position: relative;
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
        animation: fadeIn 0.3s;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Scanner Container */
    .scanner-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .video-container {
        position: relative;
        width: 100%;
        max-width: 500px;
        margin: 0 auto;
        border-radius: var(--border-radius);
        overflow: hidden;
        border: 1px solid #ddd;
        aspect-ratio: 4/3;
    }

    #qr-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .scanner-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .scanner-frame {
        width: 200px;
        height: 200px;
        border: 2px solid var(--primary);
        border-radius: 10px;
        box-shadow: 0 0 0 5000px rgba(0, 0, 0, 0.3);
        position: relative;
    }

    .scanner-frame::before,
    .scanner-frame::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        border-color: var(--primary);
        border-style: solid;
    }

    .scanner-frame::before {
        top: -2px;
        left: -2px;
        border-width: 2px 0 0 2px;
        border-radius: 5px 0 0 0;
    }

    .scanner-frame::after {
        bottom: -2px;
        right: -2px;
        border-width: 0 2px 2px 0;
        border-radius: 0 0 5px 0;
    }

    .scanner-info {
        color: white;
        background-color: rgba(0, 0, 0, 0.7);
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        margin-top: 1rem;
    }

    .scanner-controls {
        display: flex;
        justify-content: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .camera-select-container {
        min-width: 200px;
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-control {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: var(--border-radius);
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus {
        color: #495057;
        background-color: #fff;
        border-color: var(--primary);
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(255, 107, 107, 0.25);
    }

    textarea.form-control {
        height: 120px;
        resize: vertical;
    }

    label {
        display: inline-block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #555;
    }

    /* Button Styles */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.5rem 1rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: var(--border-radius);
        transition: var(--transition);
        cursor: pointer;
        text-decoration: none;
    }

    .btn i {
        margin-right: 0.5rem;
    }

    .btn-primary {
        color: #fff;
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    .btn-secondary {
        color: #fff;
        background-color: var(--secondary);
        border-color: var(--secondary);
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }

    .btn:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    /* Alert Styles */
    .alert {
        position: relative;
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
        border: 1px solid transparent;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
    }

    .alert i {
        margin-right: 0.75rem;
        font-size: 1.25rem;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }

    .alert-danger {
        color: #721c24;
        background-color: #f8d7da;
        border-color: #f5c6cb;
    }

    .alert-warning {
        color: #856404;
        background-color: #fff3cd;
        border-color: #ffeeba;
    }

    /* Order Result Styles */
    .order-result-container {
        animation: slideUp 0.4s;
    }

    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .order-card {
        margin-top: 1.5rem;
    }

    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .order-status {
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
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

    .order-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .order-meta-item {
        display: flex;
        flex-direction: column;
        padding: 1rem;
        background-color: #f8f9fa;
        border-radius: var(--border-radius);
        position: relative;
    }

    .order-meta-item i {
        position: absolute;
        top: 1rem;
        right: 1rem;
        color: rgba(0, 0, 0, 0.1);
        font-size: 1.5rem;
    }

    .meta-label {
        font-size: 0.85rem;
        color: #6c757d;
        margin-bottom: 0.5rem;
    }

    .meta-value {
        font-size: 1.1rem;
        font-weight: 500;
        color: #333;
    }

    /* Customer Info Styles */
    .customer-info-card {
        background-color: #e9f7fe;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }

    .customer-info-header {
        background-color: #17a2b8;
        color: white;
        padding: 0.75rem 1rem;
        font-weight: 500;
        display: flex;
        align-items: center;
    }

    .customer-info-header i {
        margin-right: 0.5rem;
    }

    .customer-info-body {
        padding: 1rem;
    }

    .customer-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .customer-info-item {
        padding: 0.5rem;
    }

    .info-label {
        font-size: 0.85rem;
        color: #17a2b8;
        margin-bottom: 0.25rem;
        font-weight: 500;
    }

    .info-value {
        color: #333;
    }

    /* Order Items Section */
    .order-items-section {
        margin-bottom: 1.5rem;
    }

    .order-items-section h3 {
        font-size: 1.1rem;
        margin-bottom: 1rem;
        color: #333;
        display: flex;
        align-items: center;
    }

    .order-items-section h3 i {
        margin-right: 0.5rem;
        color: var(--primary);
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
        border-collapse: collapse;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        vertical-align: middle;
        border-top: 1px solid #dee2e6;
    }

    .table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 500;
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .product-image-cell {
        width: 80px;
    }

    .product-img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: var(--border-radius);
        border: 1px solid #eee;
    }

    .text-right {
        text-align: right;
    }

    /* Status Update Section */
    .status-update-section {
        background-color: #f8f9fa;
        padding: 1.5rem;
        border-radius: var(--border-radius);
    }

    .status-update-section h3 {
        font-size: 1.1rem;
        margin-bottom: 1rem;
        color: #333;
        display: flex;
        align-items: center;
    }

    .status-update-section h3 i {
         margin-right: 0.5rem;
        color: var(--primary);
    }

    .status-form-controls {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .status-select-container {
        flex: 1;
        min-width: 200px;
    }

    .status-select {
        height: 42px;
        font-weight: 500;
    }

    .status-select option[value="pending"] {
        color: #856404;
    }

    .status-select option[value="processing"] {
        color: #004085;
    }

    .status-select option[value="completed"] {
        color: #155724;
    }

    .status-select option[value="cancelled"] {
        color: #721c24;
    }

    /* Manual Form */
    .manual-form {
        max-width: 600px;
        margin: 0 auto;
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .order-meta {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .status-form-controls {
            flex-direction: column;
            align-items: stretch;
        }

        .scanner-controls {
            flex-direction: column;
            align-items: stretch;
        }

        .scanner-frame {
            width: 180px;
            height: 180px;
        }
    }

    @media (max-width: 576px) {
        .container {
            padding: 1rem;
        }

        .card-body {
            padding: 1rem;
        }

        .scanner-tabs {
            flex-direction: column;
            border-bottom: none;
        }

        .tab {
            border-bottom: none;
            border-left: 2px solid transparent;
        }

        .tab.active {
            border-bottom-color: transparent;
            border-left-color: var(--primary);
            background-color: rgba(255, 107, 107, 0.05);
        }

        .customer-info-grid {
            grid-template-columns: 1fr;
        }

        .scanner-frame {
            width: 150px;
            height: 150px;
        }
    }
</style>

<script src="https://unpkg.com/@zxing/library@latest/umd/index.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching functionality
        const tabs = document.querySelectorAll('.tab');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and panes
                tabs.forEach(t => t.classList.remove('active'));
                tabPanes.forEach(p => p.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding pane
                this.classList.add('active');
                document.getElementById(`${targetTab}-tab`).classList.add('active');
            });
        });
        
        // QR Code Scanner functionality
        const video = document.getElementById('qr-video');
        const startButton = document.getElementById('start-button');
        const stopButton = document.getElementById('stop-button');
        const cameraSelect = document.getElementById('camera-select');
        
        let selectedDeviceId;
        let codeReader;
        
        // Initialize camera selection
        function populateCameraOptions() {
            codeReader = new ZXing.BrowserQRCodeReader();
            
            codeReader.getVideoInputDevices()
                .then((videoInputDevices) => {
                    if (videoInputDevices.length === 0) {
                        cameraSelect.innerHTML = '<option value="">No cameras found</option>';
                    } else {
                        cameraSelect.innerHTML = '';
                        videoInputDevices.forEach((device) => {
                            const option = document.createElement('option');
                            option.value = device.deviceId;
                            option.text = device.label || `Camera ${cameraSelect.options.length + 1}`;
                            cameraSelect.appendChild(option);
                            
                            // Select back camera by default if available
                            if (device.label.toLowerCase().includes('back')) {
                                selectedDeviceId = device.deviceId;
                                option.selected = true;
                            }
                        });
                        
                        // If no back camera was found, use the first camera
                        if (!selectedDeviceId && videoInputDevices.length > 0) {
                            selectedDeviceId = videoInputDevices[0].deviceId;
                        }
                    }
                })
                .catch((err) => {
                    console.error('Error getting video input devices:', err);
                    cameraSelect.innerHTML = '<option value="">Error loading cameras</option>';
                });
        }
        
        // Start scanner with selected camera
        function startScanner() {
            const selectedDeviceId = cameraSelect.value;
            
            if (!selectedDeviceId) {
                alert('Please select a camera or make sure camera permissions are granted');
                return;
            }
            
            codeReader.decodeFromVideoDevice(selectedDeviceId, 'qr-video', (result, err) => {
                if (result) {
                    console.log('QR Code detected:', result.text);
                    
                    // Play success sound
                    const successSound = new Audio('assets/sounds/beep.mp3');
                    successSound.play().catch(e => console.log('Sound play error:', e));
                    
                    // Submit the QR code data to the server
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'scan_order.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'qr_data';
                    input.value = result.text;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                    
                    stopScanner();
                }
                
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error('QR Code scanning error:', err);
                }
            }).catch(err => {
                console.error('Error starting scanner:', err);
                alert('Error starting scanner: ' + err.message);
            });
            
            startButton.disabled = true;
            stopButton.disabled = false;
        }
        
        // Stop the scanner
        function stopScanner() {
            if (codeReader) {
                codeReader.reset();
                startButton.disabled = false;
                stopButton.disabled = true;
            }
        }
        
        // Event listeners
        startButton.addEventListener('click', startScanner);
        stopButton.addEventListener('click', stopScanner);
        
        cameraSelect.addEventListener('change', function() {
            if (!stopButton.disabled) {
                stopScanner();
                startScanner();
            }
        });
        
        // Initialize camera options when page loads
        populateCameraOptions();
        
        // Print order functionality
        const printOrderButton = document.getElementById('print-order');
        if (printOrderButton) {
            printOrderButton.addEventListener('click', function() {
                window.print();
            });
        }
        
        // Clean up when page is unloaded
        window.addEventListener('beforeunload', () => {
            if (codeReader) {
                codeReader.reset();
            }
        });
    });
</script>

<style media="print">
    /* Print styles */
    header, .page-actions, .scanner-card, .status-update-section, .alert {
        display: none !important;
    }
    
    .order-card {
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
    
    .order-header {
        background-color: #f8f9fa !important;
        print-color-adjust: exact;
    }
    
    .order-status {
        print-color-adjust: exact;
    }
    
    .status-pending {
        background-color: #fff3cd !important;
        color: #856404 !important;
        print-color-adjust: exact;
    }
    
    .status-processing {
        background-color: #cce5ff !important;
        color: #004085 !important;
        print-color-adjust: exact;
    }
    
    .status-completed {
        background-color: #d4edda !important;
        color: #155724 !important;
        print-color-adjust: exact;
    }
    
    .status-cancelled {
        background-color: #f8d7da !important;
        color: #721c24 !important;
        print-color-adjust: exact;
    }
    
    .customer-info-header {
        background-color: #17a2b8 !important;
        color: white !important;
        print-color-adjust: exact;
    }
    
    .table thead th {
        background-color: #f8f9fa !important;
        print-color-adjust: exact;
    }
    
    @page {
        size: portrait;
        margin: 0.5cm;
    }
</style>

<?php include 'includes/admin_footer.php'; ?>