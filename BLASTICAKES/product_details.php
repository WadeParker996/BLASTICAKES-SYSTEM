<?php
// Start session
session_start();

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? $_SESSION['username'] : '';
$is_admin = $logged_in && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// If user is admin, redirect to admin.php
if ($is_admin) {
    header("Location: admin.php");
    exit();
}

// Include database connection
require_once 'includes/db.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = $_GET['id'];

// Fetch product details
$product = null;
$related_products = [];

// Prepare statement to prevent SQL injection
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $product = mysqli_fetch_assoc($result);
    
    // Fetch related products from the same category
    $category = $product['category'];
    $related_query = mysqli_prepare($conn, "SELECT * FROM products WHERE category = ? AND id != ? AND stock > 0 LIMIT 4");
    mysqli_stmt_bind_param($related_query, "si", $category, $product_id);
    mysqli_stmt_execute($related_query);
    $related_result = mysqli_stmt_get_result($related_query);
    
    if ($related_result && mysqli_num_rows($related_result) > 0) {
        while ($row = mysqli_fetch_assoc($related_result)) {
            $related_products[] = $row;
        }
    }
} else {
    // Product not found, redirect to products page
    header("Location: products.php");
    exit();
}

// Get cart count if user is logged in
$cart_count = 0;
if ($logged_in) {
    $user_id = $_SESSION['user_id'];
    $cart_query = mysqli_prepare($conn, "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    mysqli_stmt_bind_param($cart_query, "i", $user_id);
    mysqli_stmt_execute($cart_query);
    $cart_result = mysqli_stmt_get_result($cart_query);
    
    if ($cart_row = mysqli_fetch_assoc($cart_result)) {
        $cart_count = $cart_row['total'] ? $cart_row['total'] : 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --secondary: #4ecdc4;
            --dark: #333;
            --gray: #666;
            --light-gray: #f0f0f0;
            --white: #fff;
            --light: #f9f9f9;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--primary);
            color: var(--white);
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 20px;
        }
        
        nav ul li a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
            display: flex;
            align-items: center;
        }
        
        nav ul li a i {
            margin-right: 8px;
        }
        
        nav ul li a:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Cart Badge */
        .cart-link {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--secondary);
            color: var(--white);
            font-size: 0.7rem;
            font-weight: bold;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-btn {
            display: flex;
            align-items: center;
            color: var(--white);
            cursor: pointer;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .user-dropdown-btn:hover {
            background-color: rgba(255,255,255,0.2);
        }
        
        .user-dropdown-btn img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
        }
        
        .user-dropdown-content {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            min-width: 200px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
            z-index: 100;
        }
        
        .user-dropdown:hover .user-dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .user-dropdown-content a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .user-dropdown-content a:hover {
            background-color: var(--light-gray);
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            margin: 20px 0;
            font-size: 0.9rem;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .breadcrumb > *:not(:last-child)::after {
            content: '>';
            margin: 0 8px;
            color: var(--gray);
        }
        
        /* Product Details */
        .product-details-container {
            display: flex;
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            margin: 30px 0;
        }
        
        .product-image-container {
            flex: 1;
            padding: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--light);
        }
        
        .product-image-container img {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: var(--border-radius);
            transition: transform 0.5s ease;
        }
        
        .product-image-container:hover img {
            transform: scale(1.05);
        }
        
        .product-info {
            flex: 1;
            padding: 30px;
            border-left: 1px solid var(--light-gray);
        }
        
        .product-category {
            display: inline-block;
            background-color: var(--light-gray);
            color: var(--gray);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
            text-transform: capitalize;
        }
        
        .product-title {
            font-size: 2rem;
            margin: 0 0 15px;
            color: var(--dark);
            line-height: 1.3;
        }
        
        .product-price {
            font-size: 1.8rem;
            color: var(--primary);
            font-weight: bold;
            margin: 15px 0;
            display: flex;
            align-items: center;
        }
        
        .product-description {
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.8;
        }
        
        .product-meta {
            margin-bottom: 25px;
            padding: 15px;
            background-color: var(--light);
            border-radius: var(--border-radius);
        }
        
        .product-meta p {
            margin: 8px 0;
            color: var(--gray);
        }
        
        .product-meta strong {
            color: var(--dark);
        }
        
        .lead-time {
            color: #17a2b8;
            font-weight: bold;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .quantity-selector label {
            margin-right: 15px;
            font-weight: bold;
            color: var(--dark);
        }
        
        .quantity-selector input {
            width: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            text-align: center;
            font-size: 1rem;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: var(--white);
            padding: 12px 25px;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 1rem;
            text-align: center;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        /* Stock Status */
        .stock {
            color: #28a745;
            font-weight: bold;
        }
        
        .low-stock {
            color: #ffc107;
            font-weight: bold;
        }
        
        .out-of-stock {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Related Products */
        .section-title {
            text-align: center;
            margin: 40px 0 30px;
            color: var(--dark);
            position: relative;
            font-size: 1.8rem;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 3px;
            background-color: var(--primary);
            margin: 15px auto 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

          .product-card .product-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .product-card .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-card .product-details {
            padding: 20px;
        }
        
        .product-card .product-title {
            font-size: 1.2rem;
            margin: 0 0 10px;
            color: var(--dark);
            height: 2.4em;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-card .product-category {
            display: inline-block;
            background-color: var(--light-gray);
            color: var(--gray);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            text-transform: capitalize;
        }
        
        .product-card .product-price {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: bold;
            margin: 10px 0;
        }
        
        .product-card .product-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }
        
        .product-card .view-btn {
            padding: 8px 15px;
            background-color: var(--light-gray);
            color: var(--dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            text-align: center;
        }
        
        .product-card .view-btn:hover {
            background-color: #e0e0e0;
        }
        
        .product-card .add-to-cart-btn {
            padding: 8px 15px;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-align: center;
        }
        
        .product-card .add-to-cart-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background-color: #4CAF50;
            color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            animation: fadeInOut 3s ease-in-out;
        }
        
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 60px 0 20px;
            margin-top: 60px;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-column {
            flex: 1;
            min-width: 200px;
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
            width: 40px;
            height: 2px;
            background-color: var(--primary);
            bottom: 0;
            left: 0;
        }
        
        .footer-column p {
            color: #adb5bd;
            margin-bottom: 15px;
            font-size: 0.9rem;
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
            color: #adb5bd;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 8px;
            color: var(--primary);
        }
        
        .footer-links a:hover {
            color: var(--white);
            padding-left: 5px;
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
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
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
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #adb5bd;
            font-size: 0.9rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .product-details-container {
                flex-direction: column;
            }
            
            .product-image-container, .product-info {
                padding: 20px;
            }
            
            .product-info {
                border-left: none;
                border-top: 1px solid var(--light-gray);
            }
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            nav ul {
                position: fixed;
                top: 70px;
                left: -100%;
                background-color: var(--white);
                width: 80%;
                height: calc(100vh - 70px);
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                transition: var(--transition);
                z-index: 1000;
            }
            
            nav ul.active {
                left: 0;
            }
            
            nav ul li {
                margin: 15px 0;
                width: 100%;
            }
            
            nav ul li a {
                display: block;
                width: 100%;
                padding: 10px;
            }
            
            .user-dropdown-content {
                position: static;
                box-shadow: none;
                display: none;
                margin-top: 10px;
                opacity: 1;
                visibility: visible;
                transform: none;
            }
            
            .user-dropdown:hover .user-dropdown-content {
                display: none;
            }
            
            .user-dropdown.active .user-dropdown-content {
                display: block;
            }
            
            .product-title {
                font-size: 1.5rem;
            }
            
            .product-price {
                font-size: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .btn-secondary {
                margin-left: 0;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-card .product-image {
                height: 180px;
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
            
            <button class="mobile-menu-btn" id="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav>
                <ul id="nav-menu">
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php"><i class="fas fa-shopping-basket"></i> Products</a></li>
                    <?php if ($logged_in): ?>
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
                                $user_id = $_SESSION['user_id'];
                                
                                $profile_query = "SELECT profile_picture FROM users WHERE id = ?";
                                
                                $stmt = mysqli_prepare($conn, $profile_query);
                                mysqli_stmt_bind_param($stmt, "i", $user_id);
                                mysqli_stmt_execute($stmt);
                                $profile_result = mysqli_stmt_get_result($stmt);
                                $profile_pic = 'default_profile.png'; // Default image
                                
                                if ($profile_row = mysqli_fetch_assoc($profile_result)) {
                                    if (!empty($profile_row['profile_picture'])) {
                                        $profile_pic = $profile_row['profile_picture'];
                                    }
                                }
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
                    <?php else: ?>
                        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <li><a href="signup.php"><i class="fas fa-user-plus"></i> Sign Up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <!-- Breadcrumb navigation -->
        <div class="breadcrumb" data-aos="fade-right">
            <a href="index.php">Home</a>
            <a href="products.php">Products</a>
            <a href="products.php?category=<?php echo urlencode($product['category']); ?>"><?php echo ucfirst(htmlspecialchars($product['category'])); ?></a>
            <span><?php echo htmlspecialchars($product['name']); ?></span>
        </div>

        <!-- Product Details Section -->
        <div class="product-details-container">
            <div class="product-image-container" data-aos="fade-right">
                <img src="images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
            <div class="product-info" data-aos="fade-left">
                <span class="product-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                
                <div class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                
                <div class="product-meta">
                    <!-- Stock Quantity Display -->
                    <?php if (isset($product['stock'])): ?>
                        <div>
                            Stock Quantity: 
                            <?php if ($product['stock'] > 10): ?>
                                <span class="stock"><?php echo $product['stock']; ?> available</span>
                            <?php elseif ($product['stock'] > 0): ?>
                                <span class="low-stock">Only <?php echo $product['stock']; ?> left!</span>
                            <?php else: ?>
                                <span class="out-of-stock">Out of stock</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($product['lead_time']) && !empty($product['lead_time'])): ?>
                        <p><span class="lead-time"><strong>Order at least <?php echo $product['lead_time']; ?> days before desired date</strong></span></p>
                    <?php endif; ?>
                </div>
                
                <?php if($product['stock'] > 0): ?>
                    <!-- Form to add product to cart -->
                    
<form id="addToCartForm" action="add_to_cart.php" method="post">
    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
    <input type="hidden" name="product_name" value="<?php echo htmlspecialchars($product['name']); ?>">
    <input type="hidden" name="product_price" value="<?php echo $product['price']; ?>">
    <input type="hidden" name="product_image" value="<?php echo htmlspecialchars($product['image']); ?>">
    <input type="hidden" name="return_url" value="product_details.php?id=<?php echo $product['id']; ?>">
    <div class="quantity-selector">
        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>">
    </div>
    
    <?php if ($logged_in): ?>
        <button type="submit" id="addToCartBtn" class="btn" data-aos="fade-up">
            <i class="fas fa-shopping-cart"></i> Add to Cart
        </button>
        <a href="products.php" class="btn btn-secondary" data-aos="fade-up" data-aos-delay="100">
            Continue Shopping
        </a>
    <?php else: ?>
        <a href="login.php" class="btn" data-aos="fade-up">
            <i class="fas fa-sign-in-alt"></i> Login to Purchase
        </a>
        <a href="products.php" class="btn btn-secondary" data-aos="fade-up" data-aos-delay="100">
            Continue Shopping
        </a>
    <?php endif; ?>
</form>

                <?php else: ?>
                    <p style="color: #dc3545; font-weight: bold; margin-bottom: 20px;" data-aos="fade-up">
                        This product is currently out of stock.
                    </p>
                    <a href="products.php" class="btn btn-secondary" data-aos="fade-up">
                        Continue Shopping
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products Section -->
        <?php if (!empty($related_products)): ?>
            <h2 class="section-title" data-aos="fade-up">Related Products</h2>
            <div class="products-grid">
                <?php foreach ($related_products as $index => $related): ?>
                    <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="product-image">
                            <img src="images/<?php echo htmlspecialchars($related['image']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        </div>
                        <div class="product-details">
                            <span class="product-category"><?php echo htmlspecialchars(ucfirst($related['category'])); ?></span>
                            <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                            <p class="product-price">₱<?php echo number_format($related['price'], 2); ?></p>
                            
                            <!-- Stock Quantity Display for related products -->
                            <?php if (isset($related['stock'])): ?>
                                <div style="margin-bottom: 10px; font-size: 0.9em;">
                                    <?php if ($related['stock'] > 10): ?>
                                        <span class="stock"><?php echo $related['stock']; ?> available</span>
                                    <?php elseif ($related['stock'] > 0): ?>
                                        <span class="low-stock">Only <?php echo $related['stock']; ?> left!</span>
                                    <?php else: ?>
                                        <span class="out-of-stock">Out of stock</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-actions">
                                <a href="product_details.php?id=<?php echo $related['id']; ?>" class="view-btn">View Details</a>
                                <?php if ($logged_in && $related['stock'] > 0): ?>
                                    <!-- Form for related products to add to cart -->
                                    <form action="add_to_cart.php" method="post" style="display:inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $related['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="return_url" value="cart.php">
                                        <button type="submit" class="add-to-cart-btn">Add to Cart</button>
                                    </form>
                                <?php elseif (!$logged_in): ?>
                                    <a href="login.php" class="add-to-cart-btn">Login to Buy</a>
                                <?php else: ?>
                                    <span class="add-to-cart-btn" style="background-color: #ccc; cursor: not-allowed;">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Notification element -->
    <div id="notification" class="notification"></div>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>BLASTICAKES & CRAFTS</h3>
                    <p>Specializing in custom cakes, cupcakes, and crafts for all occasions. We bring your sweet dreams to life!</p>
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
                        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="products.php"><i class="fas fa-birthday-cake"></i> Products</a></li>
                        <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                        <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Customer Service</h3>
                    <ul class="footer-links">
                        <li><a href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a></li>
                        <li><a href="shipping.php"><i class="fas fa-truck"></i> Shipping & Delivery</a></li>
                        <li><a href="privacy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
                        <li><a href="terms.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                
                <div class="footer-column">
                    <h3>Contact Information</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-map-marker-alt"></i> 123 Cake Street, Bacolod City</a></li>
                        <li><a href="tel:+639123456789"><i class="fas fa-phone"></i> +63 912 345 6789</a></li>
                        <li><a href="mailto:info@blasticakes.com"><i class="fas fa-envelope"></i> info@blasticakes.com</a></li>
                        <li><a href="#"><i class="fas fa-clock"></i> Mon-Sat: 8:00 AM - 8:00 PM</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> BLASTICAKES & CRAFTS. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Add jQuery for AJAX functionality -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize AOS
            AOS.init({
                duration: 800,
                easing: 'ease-in-out',
                once: true,
                mirror: false
            });
            
            // Check for added parameter in URL
            <?php if (isset($_GET['added']) && $_GET['added'] == 1): ?>
                showNotification("Product added to cart successfully!");
            <?php endif; ?>
            
            // Function to show notification
            function showNotification(message, type = "success") {
                var notification = $("#notification");
                notification.text(message);
                
                if (type === "error") {
                    notification.css("background-color", "#ff6b6b");
                } else {
                    notification.css("background-color", "#4CAF50");
                }
                
                notification.css("display", "block");
                
                // Hide notification after 3 seconds
                setTimeout(function() {
                    notification.css("display", "none");
                }, 3000);
            }
            
            // Mobile menu toggle
            $("#mobile-menu-btn").click(function() {
                $("#nav-menu").toggleClass("active");
                $(this).find("i").toggleClass("fa-bars fa-times");
            });
            
            // User dropdown toggle for mobile
            $(".user-dropdown").click(function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    $(this).toggleClass("active");
                }
            });
            
            // Quantity input validation
            $("#quantity").on("input", function() {
                var max = parseInt($(this).attr("max"));
                var value = parseInt($(this).val());
                
                if (value > max) {
                    $(this).val(max);
                    showNotification("Sorry, only " + max + " items available in stock.", "error");
                }
                
                if (value < 1 || isNaN(value)) {
                    $(this).val(1);
                }
            });
            
            // Add to cart form submission
            $("#addToCartForm").submit(function(e) {
                // You can add additional validation here if needed
                
                // For example, check if user is logged in
                <?php if (!$logged_in): ?>
                    e.preventDefault();
                    window.location.href = "login.php";
                    return false;
                <?php endif; ?>
                
                // Check if quantity is valid
                var quantity = parseInt($("#quantity").val());
                var max = parseInt($("#quantity").attr("max"));
                
                if (quantity > max) {
                    e.preventDefault();
                    showNotification("Sorry, only " + max + " items available in stock.", "error");
                    return false;
                }
                
                if (quantity < 1 || isNaN(quantity)) {
                    e.preventDefault();
                    showNotification("Please enter a valid quantity.", "error");
                    return false;
                }
                
                // If all validations pass, form will submit normally
            });
        });
    </script>
</body>
</html>