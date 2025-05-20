<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);
$username = $logged_in ? $_SESSION['username'] : '';
$is_admin = $logged_in && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Get user data if logged in
$user = [];
if ($logged_in) {
    // Include database connection if not already included
    if (!isset($conn)) {
        require_once 'includes/db.php';
    }
    
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user_result = mysqli_stmt_get_result($stmt);
    
    if ($user_row = mysqli_fetch_assoc($user_result)) {
        $user = $user_row;
    }
    
    // Check for unread notifications
    $notification_count = 0;
    if (!$is_admin) {
        $notification_sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
        $stmt = mysqli_prepare($conn, $notification_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $notification_result = mysqli_stmt_get_result($stmt);
        
        if ($notification_row = mysqli_fetch_assoc($notification_result)) {
            $notification_count = $notification_row['count'];
        }
    }
    
    // Get cart count
    $cart_count = 0;
    if (!$is_admin) {
        $cart_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $cart_query);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $cart_result = mysqli_stmt_get_result($stmt);
        
        if ($cart_row = mysqli_fetch_assoc($cart_result)) {
            $cart_count = $cart_row['count'];
        }
    }
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>BLASTICAKES & CRAFTS</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #ff6b6b;
            --primary-dark: #ff5252;
            --secondary: #4ecdc4;
            --dark: #2d3436;
            --light: #f9f9f9;
            --gray: #a0a0a0;
            --white: #ffffff;
            --danger: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
            --info: #3498db;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 1.8rem;
        }
        
        nav ul {
            display: flex;
            list-style: none;
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
            transition: var(--transition);
            display: flex;
            align-items: center;
            position: relative;
        }
        
        nav ul li a i {
            margin-right: 6px;
            font-size: 1.1rem;
        }
        
        nav ul li a:hover {
            color: var(--primary);
        }
        
        nav ul li a.active {
            color: var(--primary);
            font-weight: 600;
        }
        
        nav ul li a.active::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary);
            border-radius: 2px;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: var(--white);
            padding: 10px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            text-align: center;
        }
        
        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 107, 107, 0.3);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85rem;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.5rem;
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
            transition: var(--transition);
        }
        
        .user-dropdown-btn:hover {
            color: var(--primary);
        }
        
        .user-dropdown-btn img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--white);
            min-width: 220px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1000;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
            transition: var(--transition);
        }
        
        .user-dropdown-content a {
            color: var(--dark);
            padding: 15px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: var(--transition);
            font-weight: 400;
        }
        
        .user-dropdown-content a i {
            margin-right: 10px;
            color: var(--primary);
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .user-dropdown-content a:hover {
            background-color: var(--light);
            color: var(--primary);
        }
        
        .user-dropdown:hover .user-dropdown-content {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Cart Badge */
        .cart-link {
            position: relative;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--primary);
            color: white;
            font-size: 0.7rem;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            nav ul {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 100%;
                height: calc(100vh - 80px);
                background-color: var(--white);
                flex-direction: column;
                align-items: center;
                justify-content: flex-start;
                padding-top: 40px;
                transition: var(--transition);
                z-index: 999;
            }
            
            nav ul.active {
                left: 0;
            }
            
            nav ul li {
                margin: 15px 0;
            }
            
            nav ul li a.active::after {
                display: none;
            }
        }
    </style>
    
    <?php if (isset($extra_css)): ?>
    <style>
        <?php echo $extra_css; ?>
    </style>
    <?php endif; ?>
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
                    <li>
                        <a href="index.php" class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li>
                        <a href="products.php" class="<?php echo $current_page == 'products.php' ? 'active' : ''; ?>">
                            <i class="fas fa-store"></i> Shop
                        </a>
                    </li>
                    
                    <?php if ($logged_in && !$is_admin): ?>
                        <li>
                            <a href="my_orders.php" class="<?php echo $current_page == 'my_orders.php' ? 'active' : ''; ?>">
                                <i class="fas fa-box"></i> My Orders
                                <?php if ($notification_count > 0): ?>
                                    <span class="notification-badge"><?php echo $notification_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li>
                            <a href="cart.php" class="cart-link <?php echo $current_page == 'cart.php' ? 'active' : ''; ?>">
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
                                <a href="account.php" class="<?php echo $current_page == 'account.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-user"></i> My Account
                                </a>
                                <a href="my_orders.php" class="<?php echo $current_page == 'my_orders.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-shopping-bag"></i> My Orders
                                    <?php if ($notification_count > 0): ?>
                                        <span class="notification-badge"><?php echo $notification_count; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="change_password.php" class="<?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>">
                                    <i class="fas fa-key"></i> Change Password
                                </a>
                                <a href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </li>
                    <?php elseif ($logged_in && $is_admin): ?>
                        <li>
                            <a href="admin/dashboard.php" class="<?php echo strpos($current_page, 'admin/') !== false ? 'active' : ''; ?>">
                                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                            </a>
                        </li>
                        <li class="user-dropdown">
                            <div class="user-dropdown-btn">
                                <?php
                                // Get admin profile picture
                                $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_admin.png';
                                ?>
                                <img src="profile_images/<?php echo $profile_pic; ?>" alt="Admin Profile">
                            </div>
                            <div class="user-dropdown-content">
                                <a href="admin/profile.php">
                                    <i class="fas fa-user-shield"></i> Admin Profile
                                </a>
                                <a href="admin/settings.php">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                                <a href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="login.php" class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li>
                            <a href="signup.php" class="btn btn-sm <?php echo $current_page == 'signup.php' ? 'active' : ''; ?>">
                                Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobile-menu-btn');
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    document.getElementById('nav-menu').classList.toggle('active');
                    
                    // Toggle icon between bars and times
                    const icon = this.querySelector('i');
                    if (icon.classList.contains('fa-bars')) {
                        icon.classList.replace('fa-bars', 'fa-times');
                    } else {
                        icon.classList.replace('fa-times', 'fa-bars');
                    }
                });
            }
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                const navMenu = document.getElementById('nav-menu');
                const mobileMenuBtn = document.getElementById('mobile-menu-btn');
                
                if (navMenu && mobileMenuBtn && navMenu.classList.contains('active') && 
                    !navMenu.contains(event.target) && 
                    !mobileMenuBtn.contains(event.target)) {
                    navMenu.classList.remove('active');
                    mobileMenuBtn.querySelector('i').classList.replace('fa-times', 'fa-bars');
                }
            });
        });
    </script>
