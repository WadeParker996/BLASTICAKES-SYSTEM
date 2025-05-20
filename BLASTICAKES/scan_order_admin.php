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
$order = null;
$order_items = [];
$message = '';
$order_data = null;

// Process QR code data if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = $_POST['qr_data'];
    
    try {
        // Decode the QR data
        $order_data = json_decode($qr_data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid QR code data format");
        }
        
        if (!isset($order_data['order_id'])) {
            throw new Exception("Order ID not found in QR code data");
        }
        
        $order_id = $order_data['order_id'];
        
        // Get order details
        $sql = "SELECT o.*, u.username, u.email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $order = mysqli_fetch_assoc($result);
            
            // Get order items
            $sql = "SELECT oi.*, p.name, p.image 
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            while ($item = mysqli_fetch_assoc($result)) {
                $order_items[] = $item;
            }
        } else {
            throw new Exception("Order not found");
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Process order status update if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    
    $sql = "UPDATE orders SET status = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $message = "Order status updated successfully";
        
        // Refresh order data
        $sql = "SELECT o.*, u.username, u.email 
                FROM orders o 
                JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $order_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $order = mysqli_fetch_assoc($result);
        }
    } else {
        $message = "Error updating order status";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Order QR - BLASTICAKES & CRAFTS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        header {
            background-color: #ff6b6b;
            color: white;
            padding: 1rem;
        }
        .container {
            width: 90%;
            margin: 0 auto;
            overflow: hidden;
        }
        nav {
            float: right;
        }
        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        nav li {
            display: inline;
            margin-left: 15px;
        }
        nav a {
            color: white;
            text-decoration: none;
        }
        .admin-container {
            background: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #ff6b6b;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #ff5252;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
        }
        .scanner-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
        }
        #reader {
            width: 100%;
            max-width: 500px;
            margin-bottom: 20px;
        }
        .manual-input {
            width: 100%;
            max-width: 500px;
            margin-top: 20px;
            padding: 20px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .manual-input textarea {
            width: 100%;
            height: 100px;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .order-details {
            margin-top: 20px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .order-info {
            margin-bottom: 20px;
        }
        .order-info-row {
            display: flex;
            margin-bottom: 10px;
        }
        .order-info-label {
            width: 150px;
            font-weight: bold;
        }
        .order-items {
            margin-top: 20px;
        }
        .order-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        .order-item-details {
            flex-grow: 1;
        }
        .order-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .order-item-price {
            color: #666;
        }
        .order-item-quantity {
            color: #666;
        }
        .order-total {
            text-align: right;
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 20px;
        }
        .status-form {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        .status-pending {
            color: #f39c12;
        }
        .status-processing {
            color: #3498db;
        }
        .status-completed {
            color: #27ae60;
        }
        .status-cancelled {
            color: #e74c3c;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>BLASTICAKES & CRAFTS</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="admin.php">Admin Panel</a></li>
                    <li><a href="logout.php">Logout (<?php echo $username; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>Scan Order QR Code</h2>
        
        <div class="admin-container">
            <?php if (!empty($message)): ?>
                <div class="message <?php echo strpos($message, 'Error') !== false ? 'message-error' : 'message-success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="scanner-container">
                <div id="reader"></div>
                
                <div class="manual-input">
                    <h3>Or Enter QR Code Data Manually</h3>
                    <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <textarea name="qr_data" placeholder="Paste QR code data here..."><?php echo isset($_POST['qr_data']) ? htmlspecialchars($_POST['qr_data']) : ''; ?></textarea>
                        <button type="submit" class="btn">Process Order</button>
                    </form>
                </div>
            </div>
            
            <?php if ($order): ?>
                <div class="order-details">
                    <div class="order-header">
                        <h3>Order #<?php echo $order['id']; ?></h3>
                        <span class="status-<?php echo strtolower($order['status']); ?>">
                            <?php echo $order['status']; ?>
                        </span>
                    </div>
                    
                    <div class="order-info">
                        <div class="order-info-row">
                            <div class="order-info-label">Customer:</div>
                            <div><?php echo $order['username']; ?> (<?php echo $order['email']; ?>)</div>
                        </div>
                        <div class="order-info-row">
                            <div class="order-info-label">Full Name:</div>
                            <div><?php echo $order['full_name']; ?></div>
                        </div>
                        <div class="order-info-row">
                            <div class="order-info-label">Address:</div>
                            <div><?php echo $order['address']; ?>, <?php echo $order['city']; ?></div>
                        </div>
                        <div class="order-info-row">
                            <div class="order-info-label">Phone:</div>
                            <div><?php echo $order['phone']; ?></div>
                        </div>
                        <div class="order-info-row">
                            <div class="order-info-label">Payment Method:</div>
                            <div><?php echo $order['payment_method']; ?></div>
                        </div>
                        <div class="order-info-row">
                            <div class="order-info-label">Order Date:</div>
                            <div><?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="order-items">
                        <h4>Order Items</h4>
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item">
                                <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="order-item-image">
                                <div class="order-item-details">
                                    <div class="order-item-name"><?php echo $item['name']; ?></div>
                                    <div class="order-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                    <div class="order-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-total">
                        Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                    </div>
                    
                    <div class="status-form">
                        <h4>Update Order Status</h4>
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <input type="hidden" name="qr_data" value="<?php echo isset($_POST['qr_data']) ? htmlspecialchars($_POST['qr_data']) : ''; ?>">
                            <select name="new_status" required>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status" class="btn">Update Status</button>
                        </form>
                    </div>
                </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Include the HTML5-QRCode library -->
                    <script src="https://unpkg.com/html5-qrcode"></script>
                    <script>
                        // Function to initialize QR scanner
                        function initQRScanner() {
                            const html5QrCode = new Html5Qrcode("reader");
                            const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                                // Handle the scanned code
                                document.querySelector('textarea[name="qr_data"]').value = decodedText;
                                
                                // Stop scanning
                                html5QrCode.stop().then(() => {
                                    console.log("QR Code scanning stopped.");
                                    
                                    // Submit the form automatically
                                    document.querySelector('form').submit();
                                }).catch((err) => {
                                    console.error("Failed to stop QR Code scanning:", err);
                                });
                            };
                            
                            const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                            
                            // Start scanning
                            html5QrCode.start(
                                { facingMode: "environment" }, 
                                config, 
                                qrCodeSuccessCallback
                            ).catch((err) => {
                                console.error("Error starting QR Code scanner:", err);
                                
                                // Show a message if camera access fails
                                document.getElementById("reader").innerHTML = 
                                    '<div style="color: red; padding: 20px;">' +
                                    'Could not access camera. Please ensure you have granted camera permissions, ' +
                                    'or use the manual input option below.</div>';
                            });
                        }
                        
                        // Initialize QR scanner when the page loads
                        document.addEventListener('DOMContentLoaded', function() {
                            initQRScanner();
                        });
                    </script>
                </body>
                </html>
                