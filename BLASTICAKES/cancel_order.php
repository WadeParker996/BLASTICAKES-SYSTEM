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

$order_id = intval($_GET['id']);

// Get order details
$order_query = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $order_query);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$order_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($order_result) == 0) {
    // Order not found or doesn't belong to user
    $_SESSION['error'] = "Order not found.";
    header("Location: my_orders.php");
    exit;
}

$order = mysqli_fetch_assoc($order_result);

// Check if order status allows cancellation
if (strtolower($order['status']) != 'pending' && strtolower($order['status']) != 'processing') {
    $_SESSION['error'] = "This order cannot be cancelled because it has already been " . strtolower($order['status']) . ".";
    header("Location: my_orders.php");
    exit;
}

// Update order status to cancelled
$update_query = "UPDATE orders SET status = 'cancelled' WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "i", $order_id);
$result = mysqli_stmt_execute($stmt);

if ($result) {
    // Add notification for admin
    $notification_title = "Order #$order_id Cancelled";
    $notification_message = "Order #$order_id has been cancelled by $username.";
    
    $insert_notification = "INSERT INTO notifications (user_id, title, message, created_at) 
                           VALUES (1, ?, ?, NOW())";
    $stmt = mysqli_prepare($conn, $insert_notification);
    mysqli_stmt_bind_param($stmt, "ss", $notification_title, $notification_message);
    mysqli_stmt_execute($stmt);
    
    $_SESSION['success'] = "Order #$order_id has been cancelled successfully.";
} else {
    $_SESSION['error'] = "Failed to cancel the order. Please try again.";
}

header("Location: my_orders.php");
exit;
?>
