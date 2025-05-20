<?php
/**
 * Updates the order status and sends notification to the customer
 * 
 * @param int $order_id The ID of the order to update
 * @param string $new_status The new status for the order
 * @return bool True if update was successful, false otherwise
 */
function update_order_status($order_id, $new_status) {
    global $conn;
    
    // Sanitize inputs
    $order_id = intval($order_id);
    $new_status = mysqli_real_escape_string($conn, $new_status);
    
    // Update the order status
    $update_sql = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
    $result = mysqli_query($conn, $update_sql);
    
    if ($result) {
        // Get customer email for notification
        $email_sql = "SELECT u.email, u.username FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      WHERE o.id = $order_id";
        $email_result = mysqli_query($conn, $email_sql);
        
        if ($email_result && mysqli_num_rows($email_result) > 0) {
            $customer = mysqli_fetch_assoc($email_result);
            
            // Send email notification (this is a placeholder - implement actual email sending)
            $to = $customer['email'];
            $subject = "Order #$order_id Status Update - BLASTICAKES & CRAFTS";
            $message = "Dear " . $customer['username'] . ",\n\n";
            $message .= "Your order #$order_id status has been updated to: $new_status.\n\n";
            $message .= "Thank you for shopping with BLASTICAKES & CRAFTS!\n\n";
            $message .= "If you have any questions, please contact us.";
            $headers = "From: noreply@blasticakes.com";
            
            // Uncomment the line below to actually send emails when ready
            // mail($to, $subject, $message, $headers);
            
            // For now, we'll just return true without actually sending emails
            return true;
        }
        
        return true;
    }
    
    return false;
}

/**
 * Gets the total number of items in a user's cart
 * 
 * @param int $user_id The ID of the user
 * @return int The number of items in the cart
 */
function get_cart_count($user_id) {
    global $conn;
    
    $user_id = intval($user_id);
    $sql = "SELECT SUM(quantity) as total FROM cart WHERE user_id = $user_id";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['total'] ? $row['total'] : 0;
    }
    
    return 0;
}

/**
 * Formats a price with the Philippine Peso symbol
 * 
 * @param float $price The price to format
 * @return string The formatted price
 */
function format_price($price) {
    return '₱' . number_format($price, 2);
}

/**
 * Sanitizes user input to prevent XSS attacks
 * 
 * @param string $data The data to sanitize
 * @return string The sanitized data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
