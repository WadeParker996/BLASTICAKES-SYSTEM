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

// Get cart count if user is logged in
$cart_count = 0;
if ($logged_in) {
    $user_id = $_SESSION['user_id'];
    $cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $cart_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $cart_result = mysqli_stmt_get_result($stmt);
    if ($cart_row = mysqli_fetch_assoc($cart_result)) {
        $cart_count = $cart_row['total'] ? $cart_row['total'] : 0;
    }
}

// Fetch products from database
$products = [];
$featured_products = [];

// Check if products table exists
$table_exists = mysqli_query($conn, "SHOW TABLES LIKE 'products'");
if (mysqli_num_rows($table_exists) > 0) {
    // Get all products
    $sql = "SELECT * FROM products WHERE stock > 0 ORDER BY id DESC";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
        
        // Get featured products (latest 4 products)
        $featured_products = array_slice($products, 0, 4);
    }
}

// Get product categories
$categories = [];
if (!empty($products)) {
    foreach ($products as $product) {
        if (!in_array($product['category'], $categories)) {
            $categories[] = $product['category'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --secondary: #4ecdc4;
            --dark: #333;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --light: #f8f9fa;
            --white: #ffffff;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
            --info: #17a2b8;
            --transition: all 0.3s ease;
            --shadow: 0 5px 15px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
            --border-radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
            overflow-x: hidden;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 1.8rem;
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
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
        }
        
        /* User Dropdown */
        .user-dropdown {
            position: relative;
        }
        
        .user-dropdown-btn {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .user-dropdown-btn img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }
        
        .user-dropdown-content {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--white);
            box-shadow: var(--shadow);
            border-radius: var(--border-radius);
            width: 200px;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: var(--transition);
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
            background-color: var(--light);
            color: var(--primary);
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        /* Cart Badge */
        .cart-link {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -10px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: 600;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(rgba(255, 107, 107, 0.9), rgba(255, 107, 107, 0.7)), url('images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: var(--white);
            padding: 100px 0;
            text-align: center;
            margin-bottom: 60px;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 3rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            font-weight: 300;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: var(--white);
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-align: center;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .btn-white {
            background-color: var(--white);
            color: var(--primary);
        }
        
        .btn-white:hover {
            background-color: var(--light);
            color: var(--primary-dark);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--white);
        }
        
        .btn-outline:hover {
            background-color: var(--white);
            color: var(--primary);
        }
        
        /* Section Styles */
        .section {
            padding: 60px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark);
            position: relative;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background-color: var(--primary);
        }
        
        /* Product Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .product-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .product-image {
            height: 220px;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-category {
            position: absolute;
            top: 15px;
            left: 15px;
            background-color: var(--primary);
            color: var(--white);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .product-details {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .product-actions {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .view-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: var(--light);
            color: var(--dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .view-btn:hover {
            background-color: var(--light-gray);
        }
        
        .add-to-cart-btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .add-to-cart-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .view-all {
            text-align: center;
            margin-top: 30px;
        }
        
        .view-all a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
        }
        
        .view-all a i {
            margin-left: 5px;
            transition: var(--transition);
        }
        
        .view-all a:hover {
            color: var(--primary-dark);
        }
        
        .view-all a:hover i {
            transform: translateX(5px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin: 30px 0;
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Features Section */
        .features-section {
            background-color: var(--white);
            padding: 60px 0;
            margin: 60px 0;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .feature-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .feature-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .feature-description {
            color: var(--gray);
            font-size: 0.95rem;
        }
        
        /* About Section */
        .about-section {
            padding: 60px 0;
            background-color: var(--light);
        }
        
        .about-container {
            display: flex;
            align-items: center;
            gap: 50px;
        }
        
        .about-image {
            flex: 1;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .about-content {
            flex: 1;
        }
        
        .about-title {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }
        
        .about-description {
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.8;
        }
        
        /* Testimonials Section */
        .testimonials-section {
            background-color: var(--white);
            padding: 60px 0;
        }
        
        .testimonials-container {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }
        
        .testimonial {
            padding: 30px;
            border-radius: var(--border-radius);
            background-color: var(--light);
            margin-bottom: 30px;
            position: relative;
        }
        
        .testimonial:last-child {
            margin-bottom: 0;
        }
        
        .testimonial-text {
            font-style: italic;
            color: var(--dark);
            margin-bottom: 20px;
            line-height: 1.8;
            position: relative;
            padding: 0 20px;
        }
        
        .testimonial-text:before,
        .testimonial-text:after {
            content: '"';
            font-size: 2rem;
            color: var(--primary);
            position: absolute;
            font-family: serif;
        }
        
        .testimonial-text:before {
            left: 0;
            top: -10px;
        }
        
        .testimonial-text:after {
            right: 0;
            bottom: -10px;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .author-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            border: 3px solid var(--primary);
        }
        
        .author-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .author-info h4 {
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .author-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Call to Action */
        .cta-section {
            background: linear-gradient(rgba(255, 107, 107, 0.9), rgba(255, 107, 107, 0.7)), url('images/cta-bg.jpg');
            background-size: cover;
            background-position: center;
            color: var(--white);
            padding: 80px 0;
            text-align: center;
            margin: 60px 0;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-description {
            font-size: 1.1rem;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: var(--white);
            padding: 60px 0 20px;
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
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .about-container {
                flex-direction: column;
            }
            
            .about-image, .about-content {
                flex: none;
                width: 100%;
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
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }
            
            .cta-title {
                font-size: 1.8rem;
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
                                // Get user profile picture
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
    
    <!-- Hero Section -->
    <section class="hero-section" data-aos="fade-up">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Welcome to BLASTICAKES & CRAFTS</h1>
                <p class="hero-subtitle">Discover our delicious cakes and beautiful crafts for all your special occasions.</p>
                <div>
                    <a href="products.php" class="btn btn-white">Shop Now</a>
                    <?php if (!$logged_in): ?>
                        <a href="signup.php" class="btn btn-outline">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Featured Products Section -->
    <section class="section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Featured Products</h2>
            
            <?php if (empty($featured_products)): ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-birthday-cake"></i>
                    <h3>No Products Available Yet</h3>
                    <p>Check back soon for our delicious cakes and beautiful crafts!</p>
                    <?php if ($logged_in): ?>
                        <a href="products.php" class="btn">Browse All Products</a>
                    <?php else: ?>
                        <a href="login.php" class="btn">Login to Shop</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($featured_products as $index => $product): ?>
                        <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                            <div class="product-image">
                                <img src="images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <span class="product-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
                            </div>
                            <div class="product-details">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                                <div class="product-actions">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="view-btn">View Details</a>
                                    <?php if ($logged_in): ?>
                                        <a href="add_to_cart.php?product_id=<?php echo $product['id']; ?>" class="add-to-cart-btn">Add to Cart</a>
                                    <?php else: ?>
                                        <a href="login.php" class="add-to-cart-btn">Login to Buy</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="view-all" data-aos="fade-up">
                    <a href="products.php">View All Products <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section" data-aos="fade-up">
        <div class="container">
            <h2 class="section-title">Why Choose Us</h2>
            <div class="features-grid">
                <div class="feature-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-icon">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <h3 class="feature-title">Handcrafted with Love</h3>
                    <p class="feature-description">Every cake and craft is made with passion and attention to detail, ensuring a unique and special creation for your occasion.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3 class="feature-title">Fast Delivery</h3>
                    <p class="feature-description">We ensure your orders are delivered on time and in perfect condition, so you can enjoy your special moments without worry.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3 class="feature-title">Premium Quality</h3>
                    <p class="feature-description">We use only the finest ingredients and materials to create products that not only look amazing but taste delicious too.</p>
                </div>
                
                <div class="feature-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="feature-icon">
                        <i class="fas fa-paint-brush"></i>
                    </div>
                    <h3 class="feature-title">Custom Designs</h3>
                    <p class="feature-description">We can create custom designs tailored to your specific needs and preferences, making your celebration truly special.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Category Sections -->
    <?php foreach ($categories as $index => $category): ?>
        <section class="section">
            <div class="container">
                <h2 class="section-title" data-aos="fade-up"><?php echo ucfirst(htmlspecialchars($category)); ?> Collection</h2>
                <div class="products-grid">
                    <?php
                    $category_products = array_filter($products, function($p) use ($category) {
                        return $p['category'] === $category;
                    });
                    
                    // Get first 4 products of this category
                    $category_products = array_slice($category_products, 0, 4);
                    
                    foreach ($category_products as $prod_index => $product):
                    ?>
                        <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo $prod_index * 100; ?>">
                            <div class="product-image">
                                <img src="images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <span class="product-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
                            </div>
                            <div class="product-details">
                                <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                                <div class="product-actions">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>" class="view-btn">View Details</a>
                                    <?php if ($logged_in): ?>
                                        <a href="add_to_cart.php?product_id=<?php echo $product['id']; ?>" class="add-to-cart-btn">Add to Cart</a>
                                    <?php else: ?>
                                        <a href="login.php" class="add-to-cart-btn">Login to Buy</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="view-all" data-aos="fade-up">
                    <a href="products.php?category=<?php echo urlencode($category); ?>">View All <?php echo ucfirst(htmlspecialchars($category)); ?> <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
    
    <!-- About Section -->
    <section class="about-section" data-aos="fade-up">
        <div class="container">
            <div class="about-container">
                <div class="about-image" data-aos="fade-right">
                    <img src="profile_images\profile_2_1747495303.jpg" alt="About BLASTICAKES & CRAFTS">
                </div>
                <div class="about-content" data-aos="fade-left">
                    <h2 class="about-title">About BLASTICAKES & CRAFTS</h2>
                    <p class="about-description">
                        At BLASTICAKES & CRAFTS, we specialize in creating delicious cakes and beautiful crafts for all your special occasions. From birthdays to weddings, our handcrafted products will make your celebration unforgettable.
                    </p>
                    <p class="about-description">
                        Our team of skilled bakers and craftspeople put their heart and soul into every creation, ensuring that each product is not only visually stunning but also delicious and memorable.
                    </p>
                    <?php if (!$logged_in): ?>
                        <p class="about-description">
                            Join our community today to start shopping and make your next celebration truly special!
                        </p>
                        <a href="signup.php" class="btn">Sign Up Now</a>
                    <?php else: ?>
                        <a href="products.php" class="btn">Explore Our Products</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="testimonials-section" data-aos="fade-up">
        <div class="container">
            <h2 class="section-title">What Our Customers Say</h2>
            <div class="testimonials-container">
                <div class="testimonial" data-aos="fade-up" data-aos-delay="100">
                    <p class="testimonial-text">
                        The birthday cake I ordered for my daughter was absolutely stunning! Not only did it look beautiful, but it tasted amazing too. Everyone at the party was impressed!
                    </p>
                    <div class="testimonial-author">
                        <div class="author-image">
                            <img src="images/testimonial-1.jpg" alt="Maria Santos">
                        </div>
                        <div class="author-info">
                            <h4>Maria Santos</h4>
                            <p>Happy Customer</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonial" data-aos="fade-up" data-aos-delay="200">
                    <p class="testimonial-text">
                        I ordered a custom craft for my wedding and it exceeded all my expectations. The attention to detail was incredible, and it made our special day even more memorable.
                    </p>
                    <div class="testimonial-author">
                        <div class="author-image">
                            <img src="images/testimonial-2.jpg" alt="John Reyes">
                        </div>
                        <div class="author-info">
                            <h4>John Reyes</h4>
                            <p>Satisfied Client</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="cta-section" data-aos="fade-up">
        <div class="container">
            <h2 class="cta-title">Ready to Make Your Celebration Special?</h2>
            <p class="cta-description">
                Browse our collection of cakes and crafts to find the perfect addition to your next special occasion.
            </p>
            <a href="products.php" class="btn btn-white">Shop Now</a>
        </div>
    </section>
    
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
    
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Mobile menu toggle
        document.getElementById('mobile-menu-btn').addEventListener('click', function() {
            document.getElementById('nav-menu').classList.toggle('active');
            this.classList.toggle('active');
        });
        
        // User dropdown toggle for mobile
        const userDropdown = document.querySelector('.user-dropdown');
        if (userDropdown) {
            userDropdown.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    this.classList.toggle('active');
                }
            });
        }
    </script>
</body>
</html>
