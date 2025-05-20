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

// Initialize variables
$order = null;
$order_items = [];
$review = null;
$success_message = '';
$error_message = '';

// Check if order ID is provided
if (!isset($_GET['id'])) {
    header("Location: my_orders.php");
    exit;
}

$order_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get order details
$order_sql = "SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id";
$order_result = mysqli_query($conn, $order_sql);

if ($order_result && mysqli_num_rows($order_result) > 0) {
    $order = mysqli_fetch_assoc($order_result);
    
    // Get order items
    $items_sql = "SELECT oi.*, p.name, p.image 
                 FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = $order_id";
    $items_result = mysqli_query($conn, $items_sql);
    
    if ($items_result) {
        while ($item = mysqli_fetch_assoc($items_result)) {
            $order_items[] = $item;
        }
    }
    
    // Check if review exists
    $review_sql = "SELECT * FROM reviews WHERE order_id = $order_id AND user_id = $user_id";
    $review_result = mysqli_query($conn, $review_sql);
    
    if ($review_result && mysqli_num_rows($review_result) > 0) {
        $review = mysqli_fetch_assoc($review_result);
    }
} else {
    header("Location: my_orders.php");
    exit;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = mysqli_real_escape_string($conn, $_POST['rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    // Check if review already exists
    if ($review) {
        // Update existing review
        $update_sql = "UPDATE reviews SET rating = $rating, comment = '$comment', updated_at = NOW() 
                      WHERE id = " . $review['id'];
        
        if (mysqli_query($conn, $update_sql)) {
            $success_message = "Your review has been updated!";
            // Refresh review data
            $review_result = mysqli_query($conn, $review_sql);
            if ($review_result && mysqli_num_rows($review_result) > 0) {
                $review = mysqli_fetch_assoc($review_result);
            }
        } else {
            $error_message = "Error updating review: " . mysqli_error($conn);
        }
    } else {
        // Create new review
        $insert_sql = "INSERT INTO reviews (user_id, order_id, rating, comment, created_at) 
                      VALUES ($user_id, $order_id, $rating, '$comment', NOW())";
        
        if (mysqli_query($conn, $insert_sql)) {
            $success_message = "Thank you for your review!";
            // Get the new review
            $review_result = mysqli_query($conn, $review_sql);
                        if ($review_result && mysqli_num_rows($review_result) > 0) {
                $review = mysqli_fetch_assoc($review_result);
            }
        } else {
            $error_message = "Error submitting review: " . mysqli_error($conn);
        }
    }
}

// Create reviews table if it doesn't exist
$create_reviews_table = "CREATE TABLE IF NOT EXISTS reviews (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    order_id INT(11) NOT NULL,
    rating INT(1) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY (user_id, order_id)
)";
mysqli_query($conn, $create_reviews_table);

// Create notifications table if it doesn't exist
$create_notifications_table = "CREATE TABLE IF NOT EXISTS notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_notifications_table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?php echo $order_id; ?> - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
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
            width: 80%;
            margin: 0 auto;
            padding: 20px;
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
        h2 {
            color: #333;
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
            font-size: 14px;
        }
        .btn:hover {
            background-color: #ff5252;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #ff6b6b;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .order-details {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .order-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .order-meta-item {
            margin-bottom: 10px;
            flex-basis: 48%;
        }
        .order-meta-label {
            font-weight: bold;
            color: #666;
        }
        .order-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #ffeeba;
            color: #856404;
        }
        .status-processing {
            background-color: #b8daff;
            color: #004085;
        }
        .status-completed {
            background-color: #c3e6cb;
            color: #155724;
        }
        .status-cancelled {
            background-color: #f5c6cb;
            color: #721c24;
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
            font-size: 16px;
            margin-bottom: 5px;
        }
        .order-item-price {
            color: #666;
        }
        .order-item-quantity {
            color: #666;
        }
        .order-item-total {
            font-weight: bold;
            margin-top: 5px;
        }
        .order-total {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .review-section {
            margin-top: 30px;
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .review-form {
            margin-top: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        .rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }
        .rating input {
            display: none;
        }
        .rating label {
            cursor: pointer;
            width: 30px;
            height: 30px;
            margin-right: 5px;
            position: relative;
            font-size: 30px;
            color: #ddd;
        }
        .rating label:before {
            content: '\2605';
            position: absolute;
            top: 0;
            left: 0;
        }
        .rating input:checked ~ label {
            color: #ffb300;
        }
        .rating label:hover,
        .rating label:hover ~ label {
            color: #ffb300;
        }
        .rating input:checked + label:hover,
        .rating input:checked ~ label:hover,
        .rating label:hover ~ input:checked ~ label,
        .rating input:checked ~ label:hover ~ label {
            color: #ffb300;
        }
        .existing-review {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .review-rating {
            margin-bottom: 10px;
        }
        .review-rating i {
            color: #ffb300;
            margin-right: 2px;
        }
        .review-comment {
            font-style: italic;
        }
        .review-date {
            color: #999;
            font-size: 12px;
            margin-top: 10px;
        }
        .tracking-section {
            margin-top: 30px;
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .tracking-timeline {
            margin-top: 20px;
            position: relative;
            padding-left: 30px;
        }
        .tracking-timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #ddd;
        }
        .tracking-event {
            position: relative;
            margin-bottom: 20px;
        }
        .tracking-event:last-child {
            margin-bottom: 0;
        }
        .tracking-event:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #ddd;
        }
        .tracking-event.active:before {
            background: #ff6b6b;
            border-color: #ff6b6b;
        }
        .tracking-event-time {
            color: #999;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .tracking-event-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .tracking-event-description {
            color: #666;
            font-size: 14px;
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
                    <li><a href="my_orders.php">My Orders</a></li>
                    <li><a href="logout.php">Logout (<?php echo $username; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <a href="my_orders.php" class="back-link">← Back to My Orders</a>
        
        <h2>Order #<?php echo $order_id; ?> Details</h2>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="order-details">
            <div class="order-meta">
                <div class="order-meta-item">
                    <div class="order-meta-label">Order Date:</div>
                    <div><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></div>
                </div>
                
                <div class="order-meta-item">
                    <div class="order-meta-label">Status:</div>
                    <div>
                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <?php echo $order['status']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="order-meta-item">
                    <div class="order-meta-label">Total Amount:</div>
                    <div>₱<?php echo number_format($order['total_amount'], 2); ?></div>
                </div>
                
                <div class="order-meta-item">
                    <div class="order-meta-label">Fulfillment Method:</div>
                    <div><?php echo ucfirst(htmlspecialchars($order['fulfillment_option'])); ?></div>
                </div>
                
                <div class="order-meta-item">
                    <div class="order-meta-label">Payment Method:</div>
                    <div><?php echo htmlspecialchars($order['payment_method']); ?></div>
                </div>
                
                <div class="order-meta-item">
                    <div class="order-meta-label">Phone:</div>
                    <div><?php echo htmlspecialchars($order['phone']); ?></div>
                </div>
                
                <?php if ($order['fulfillment_option'] == 'delivery'): ?>
                    <div class="order-meta-item">
                        <div class="order-meta-label">Delivery Address:</div>
                        <div><?php echo nl2br(htmlspecialchars($order['address'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="order-items">
                <h3>Order Items</h3>
                
                <?php foreach ($order_items as $item): ?>
                    <div class="order-item">
                        <img src="images/<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" class="order-item-image">
                        <div class="order-item-details">
                            <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="order-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                            <div class="order-item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                                        <div class="order-item-total">Subtotal: ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="order-total">
                    Total: ₱<?php echo number_format($order['total_amount'], 2); ?>
                </div>
            </div>
        </div>
        
        <!-- Order Tracking Section -->
        <div class="tracking-section">
            <h3>Order Status Tracking</h3>
            
            <div class="tracking-timeline">
                <div class="tracking-event <?php echo $order['status'] == 'Pending' || $order['status'] == 'Processing' || $order['status'] == 'Completed' ? 'active' : ''; ?>">
                    <div class="tracking-event-time"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></div>
                    <div class="tracking-event-title">Order Placed</div>
                    <div class="tracking-event-description">Your order has been received and is awaiting confirmation.</div>
                </div>
                
                <div class="tracking-event <?php echo $order['status'] == 'Processing' || $order['status'] == 'Completed' ? 'active' : ''; ?>">
                    <div class="tracking-event-time">
                        <?php 
                        // This would ideally come from a status history table
                        echo $order['status'] == 'Processing' || $order['status'] == 'Completed' ? 
                             date('F j, Y, g:i a', strtotime('+1 hour', strtotime($order['order_date']))) : 
                             'Pending';
                        ?>
                    </div>
                    <div class="tracking-event-title">Order Processing</div>
                    <div class="tracking-event-description">We're preparing your order and getting it ready.</div>
                </div>
                
                <?php if ($order['fulfillment_option'] == 'delivery'): ?>
                    <div class="tracking-event <?php echo $order['status'] == 'Completed' ? 'active' : ''; ?>">
                        <div class="tracking-event-time">
                            <?php 
                            echo $order['status'] == 'Completed' ? 
                                 date('F j, Y, g:i a', strtotime('+1 day', strtotime($order['order_date']))) : 
                                 'Pending';
                            ?>
                        </div>
                        <div class="tracking-event-title">Out for Delivery</div>
                        <div class="tracking-event-description">Your order is on its way to you.</div>
                    </div>
                <?php else: ?>
                    <div class="tracking-event <?php echo $order['status'] == 'Completed' ? 'active' : ''; ?>">
                        <div class="tracking-event-time">
                            <?php 
                            echo $order['status'] == 'Completed' ? 
                                 date('F j, Y, g:i a', strtotime('+1 day', strtotime($order['order_date']))) : 
                                 'Pending';
                            ?>
                        </div>
                        <div class="tracking-event-title">Ready for Pickup</div>
                        <div class="tracking-event-description">Your order is ready for pickup at our store.</div>
                    </div>
                <?php endif; ?>
                
                <div class="tracking-event <?php echo $order['status'] == 'Completed' ? 'active' : ''; ?>">
                    <div class="tracking-event-time">
                        <?php 
                        echo $order['status'] == 'Completed' ? 
                             date('F j, Y, g:i a', strtotime('+1 day +2 hours', strtotime($order['order_date']))) : 
                             'Pending';
                        ?>
                    </div>
                    <div class="tracking-event-title">Order Completed</div>
                    <div class="tracking-event-description">Your order has been delivered/picked up successfully.</div>
                </div>
                
                <?php if ($order['status'] == 'Cancelled'): ?>
                    <div class="tracking-event active">
                        <div class="tracking-event-time">
                            <?php echo date('F j, Y, g:i a', strtotime('+2 hours', strtotime($order['order_date']))); ?>
                        </div>
                        <div class="tracking-event-title">Order Cancelled</div>
                        <div class="tracking-event-description">This order has been cancelled.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Review Section - Only show for completed orders -->
        <?php if ($order['status'] == 'Completed'): ?>
            <div class="review-section">
                <h3>Order Review</h3>
                
                <?php if ($review): ?>
                    <div class="existing-review">
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></div>
                        <div class="review-date">
                            Submitted on <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                            <?php if ($review['updated_at']): ?>
                                (Updated on <?php echo date('F j, Y', strtotime($review['updated_at'])); ?>)
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button class="btn" id="edit-review-btn">Edit My Review</button>
                    
                    <div id="edit-review-form" style="display: none;">
                        <h4>Edit Your Review</h4>
                        <form class="review-form" method="post" action="">
                            <div class="form-group">
                                <label>Rating:</label>
                                <div class="rating">
                                    <input type="radio" id="star5" name="rating" value="5" <?php echo $review['rating'] == 5 ? 'checked' : ''; ?>>
                                    <label for="star5" title="5 stars"></label>
                                    <input type="radio" id="star4" name="rating" value="4" <?php echo $review['rating'] == 4 ? 'checked' : ''; ?>>
                                    <label for="star4" title="4 stars"></label>
                                    <input type="radio" id="star3" name="rating" value="3" <?php echo $review['rating'] == 3 ? 'checked' : ''; ?>>
                                    <label for="star3" title="3 stars"></label>
                                    <input type="radio" id="star2" name="rating" value="2" <?php echo $review['rating'] == 2 ? 'checked' : ''; ?>>
                                    <label for="star2" title="2 stars"></label>
                                    <input type="radio" id="star1" name="rating" value="1" <?php echo $review['rating'] == 1 ? 'checked' : ''; ?>>
                                    <label for="star1" title="1 star"></label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="comment">Your Review:</label>
                                <textarea id="comment" name="comment" required><?php echo htmlspecialchars($review['comment']); ?></textarea>
                            </div>
                            
                            <button type="submit" name="submit_review" class="btn">Update Review</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p>Share your experience with this order. Your feedback helps us improve!</p>
                    
                    <form class="review-form" method="post" action="">
                        <div class="form-group">
                            <label>Rating:</label>
                            <div class="rating">
                                <input type="radio" id="star5" name="rating" value="5">
                                <label for="star5" title="5 stars"></label>
                                <input type="radio" id="star4" name="rating" value="4">
                                <label for="star4" title="4 stars"></label>
                                <input type="radio" id="star3" name="rating" value="3">
                                <label for="star3" title="3 stars"></label>
                                <input type="radio" id="star2" name="rating" value="2">
                                <label for="star2" title="2 stars"></label>
                                <input type="radio" id="star1" name="rating" value="1">
                                <label for="star1" title="1 star"></label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="comment">Your Review:</label>
                            <textarea id="comment" name="comment" placeholder="Tell us about your experience with this order..." required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_review" class="btn">Submit Review</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Toggle edit review form
        document.getElementById('edit-review-btn')?.addEventListener('click', function() {
            const form = document.getElementById('edit-review-form');
            if (form.style.display === 'none') {
                form.style.display = 'block';
                this.textContent = 'Cancel Editing';
            } else {
                form.style.display = 'none';
                this.textContent = 'Edit My Review';
            }
        });
    </script>
</body>
</html>


