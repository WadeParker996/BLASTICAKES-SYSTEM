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

// Check if order status allows deletion (only cancelled, completed, or delivered orders can be deleted)
$status = strtolower($order['status']);
if ($status != 'cancelled' && $status != 'completed' && $status != 'delivered') {
    $_SESSION['error'] = "Only cancelled, completed, or delivered orders can be deleted.";
    header("Location: my_orders.php");
    exit;
}

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // Delete order items first
    $delete_items_query = "DELETE FROM order_items WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $delete_items_query);
    mysqli_stmt_bind_param($stmt, "i", $order_id);
    mysqli_stmt_execute($stmt);
    
    // Delete order
    $delete_order_query = "DELETE FROM orders WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $delete_order_query);
    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = "Order #$order_id has been deleted successfully.";
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to delete the order. Please try again.";
}

header("Location: my_orders.php");
exit;
?>
