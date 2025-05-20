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

// Get user data if logged in
$user = [];
if ($logged_in) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    if ($user_row = mysqli_fetch_assoc($user_result)) {
        $user = $user_row;
    }
}

// Get cart count
$cart_count = 0;
if ($logged_in) {
    $cart_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $cart_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $cart_result = mysqli_stmt_get_result($stmt);
    if ($cart_row = mysqli_fetch_assoc($cart_result)) {
        $cart_count = $cart_row['count'];
    }
}

// Get category filter from URL
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Get search query if provided
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare query based on category filter and search
$params = [];
$types = "";

if (!empty($search) && !empty($category)) {
    $sql = "SELECT * FROM products WHERE category = ? AND (name LIKE ? OR description LIKE ?)";
    $search_param = "%$search%";
    $params = [$category, $search_param, $search_param];
    $types = "sss";
} elseif (!empty($search)) {
    $sql = "SELECT * FROM products WHERE name LIKE ? OR description LIKE ?";
    $search_param = "%$search%";
    $params = [$search_param, $search_param];
    $types = "ss";
} elseif (!empty($category)) {
    $sql = "SELECT * FROM products WHERE category = ?";
    $params = [$category];
    $types = "s";
} else {
    $sql = "SELECT * FROM products";
}

$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Get all categories for filter
$categories_query = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_result = mysqli_query($conn, $categories_query);
$categories = [];
while ($cat_row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat_row['category'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - BLASTICAKES & CRAFTS</title>
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
            --light: #f9f9f9;
            --gray: #666;
            --light-gray: #f1f1f1;
            --white: #fff;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: var(--light);
            color: var(--dark);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
            font-weight: bold;
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
            min-width: 200px;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            padding: 10px 0;
            z-index: 1000;
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
            padding: 10px 20px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .user-dropdown-content a:hover {
            background-color: var(--light-gray);
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
        }
        
        /* Page Title */
        .page-title-container {
            background-color: var(--primary);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .page-title-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('images/pattern.png');
            opacity: 0.1;
        }
        
        .page-title {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
        }
        
        .search-form {
            display: flex;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
            border: none;
            font-size: 1rem;
        }
        
        .search-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0 20px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-btn:hover {
            background-color: var(--primary-dark);
        }
        
        /* Filters */
        .filters {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .filter-btn {
            padding: 8px 20px;
            background-color: var(--light-gray);
            color: var(--gray);
            border: none;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            text-transform: capitalize;
        }
        
        .filter-btn:hover {
            background-color: #e0e0e0;
        }
        
        .filter-btn.active {
            background-color: var(--primary);
            color: var(--white);
        }
        
        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-details {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-category {
            display: inline-block;
            background-color: var(--primary-dark);
            color: var(--white);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 10px;
            text-transform: capitalize;
        }
        
        .product-title {
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
        
        .product-description {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 15px;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .product-price {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: bold;
            margin: 10px 0;
        }
        
        .product-meta {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 15px;
        }
        
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
        
        .lead-time {
            color: #17a2b8;
            font-weight: bold;
        }
        
        .delivery-date {
            margin-top: 10px;
            font-size: 0.85rem;
            color: var(--gray);
            padding-top: 10px;
            border-top: 1px solid var(--light-gray);
        }
        
        .product-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-align: center;
            font-size: 0.9rem;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: var(--light-gray);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--light-gray);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--gray);
            max-width: 500px;
            margin: 0 auto 20px;
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
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
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
            
            .page-title {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .product-image {
                height: 180px;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-btn {
                width: 100%;
                text-align: center;
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
                                $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.png';
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

    <div class="page-title-container">
        <div class="container">
            <h1 class="page-title" data-aos="fade-up">Our Products</h1>
            <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">
                Discover our handcrafted cakes and unique crafts for all your special occasions
            </p>
        </div>
    </div>

    <div class="container">
        <!-- Search Bar -->
        <div class="search-container" data-aos="fade-up">
            <form action="products.php" method="GET" class="search-form">
                <?php if (!empty($category)): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
                <input type="text" name="search" class="search-input" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>

        <!-- Category Filters -->
        <div class="filters" data-aos="fade-up">
            <a href="products.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo empty($category) ? 'active' : ''; ?>">
                All Products
            </a>
            
            <?php foreach ($categories as $cat): ?>
                <a href="products.php?category=<?php echo urlencode($cat); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                   class="filter-btn <?php echo $category === $cat ? 'active' : ''; ?>">
                    <?php echo ucfirst(htmlspecialchars($cat)); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Products Grid -->
        <div class="products-grid">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php $delay = 0; ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                        <div class="product-image">
                            <img src="images/<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                        </div>
                        <div class="product-details">
                            <span class="product-category"><?php echo ucfirst(htmlspecialchars($row['category'])); ?></span>
                            <h3 class="product-title"><?php echo htmlspecialchars($row['name']); ?></h3>
                            <p class="product-description">
                                <?php echo htmlspecialchars(strlen($row['description']) > 100 ? substr($row['description'], 0, 100) . "..." : $row['description']); ?>
                            </p>
                            <p class="product-price">₱<?php echo number_format($row['price'], 2); ?></p>
                            
                            <div class="product-meta">
                                <!-- Stock Quantity Display -->
                                <?php if (isset($row['stock'])): ?>
                                    <div>
                                        Stock Quantity: 
                                        <?php if ($row['stock'] > 10): ?>
                                            <span class="stock"><?php echo $row['stock']; ?> available</span>
                                        <?php elseif ($row['stock'] > 0): ?>
                                            <span class="low-stock">Only <?php echo $row['stock']; ?> left!</span>
                                        <?php else: ?>
                                            <span class="out-of-stock">Out of stock</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Lead Time Display -->
                                <?php if (isset($row['lead_time']) && !empty($row['lead_time'])): ?>
                                    <div>
                                        <span class="lead-time">Order at least <?php echo $row['lead_time']; ?> days before desired date</span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Delivery Date -->
                                <?php if (isset($row['delivery_datetime'])): ?>
                                    <div class="delivery-date">
                                        Order now, get it by: <?php echo date('F j, Y', strtotime($row['delivery_datetime'])); ?>
                                         </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="product-actions">
                                <a href="product_details.php?id=<?php echo $row['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if ($logged_in && isset($row['stock']) && $row['stock'] > 0): ?>
                                    
                                <?php elseif (!$logged_in): ?>
                                    <a href="login.php" class="btn btn-block">
                                        <i class="fas fa-sign-in-alt"></i> Login to Buy
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-block" style="background-color: #ccc; cursor: not-allowed;">
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php $delay += 50; ?>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" data-aos="fade-up">
                    <i class="fas fa-search"></i>
                    <h3>No products found</h3>
                    <?php if (!empty($search) || !empty($category)): ?>
                        <p>We couldn't find any products matching your search criteria. Try different keywords or browse all products.</p>
                        <a href="products.php" class="btn">View All Products</a>
                    <?php else: ?>
                        <p>We're currently updating our inventory. Please check back later for new products.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
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
        });
    </script>
</body>
</html>