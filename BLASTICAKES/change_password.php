<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'includes/db.php';

// Get user information
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user data
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// Initialize variables
$current_password = $new_password = $confirm_password = "";
$error = $success = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Get user's current password from database
        $user_id = $_SESSION['user_id'];
        
        // Debug: Check what fields are available in the users table
        $check_fields = mysqli_query($conn, "DESCRIBE users");
        $fields = [];
        while ($field = mysqli_fetch_assoc($check_fields)) {
            $fields[] = $field['Field'];
        }
        
        // Use the correct field name based on the database structure
        $password_field = in_array('PASSWORD', $fields) ? 'PASSWORD' : 'password';
        
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            // Compare with the correct password field
            if ($current_password === $user['PASSWORD']) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password in database - update both fields to be safe
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, PASSWORD = ? WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "ssi", $hashed_password, $new_password, $user_id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Password changed successfully!";
                    // Clear form fields after successful submission
                    $current_password = $new_password = $confirm_password = "";
                } else {
                    $error = "Error updating password: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($update_stmt);
            } else {
                $error = "Current password is incorrect";
            }
        } else {
            $error = "User not found";
        }
        
        mysqli_stmt_close($stmt);
    }
}

// Get cart count if user is logged in
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - BLASTICAKES & CRAFTS</title>
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
            font-weight: bold;
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

        /* Page Title */
        .page-title-section {
            background-color: var(--primary);
            color: var(--white);
            padding: 40px 0;
            margin-bottom: 40px;
            text-align: center;
        }

        .page-title {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .page-subtitle {
            font-size: 1rem;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Account Container */
        .account-container {
            display: flex;
            gap: 30px;
            margin-bottom: 60px;
        }

        .account-sidebar {
            flex: 0 0 280px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
            height: fit-content;
        }

        .account-content {
            flex: 1;
            background-color: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
        }

        .profile-picture-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-picture {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .user-email {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 25px;
        }

        .account-menu {
            list-style: none;
        }

        .account-menu li {
            margin-bottom: 10px;
        }

        .account-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }

        .account-menu a:hover {
            background-color: var(--light);
            color: var(--primary);
        }

        .account-menu a.active {
            background-color: var(--light);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        .account-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: var(--primary);
        }

        /* Form Styles */
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--dark);
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
            outline: none;
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.85rem;
            color: var(--gray);
        }

        .btn {
                display: inline-block;
                background-color: var(--primary);
                color: var(--white);
                padding: 12px 25px;
                border: none;
                border-radius: var(--border-radius);
                font-family: 'Poppins', sans-serif;
                font-size: 0.95rem;
                font-weight: 500;
                cursor: pointer;
                transition: var(--transition);
                text-decoration: none;
            }
            
            .btn:hover {
                background-color: var(--primary-dark);
                transform: translateY(-2px);
                box-shadow: var(--shadow);
            }
            
            .btn-secondary {
                background-color: var(--gray);
            }
            
            .btn-secondary:hover {
                background-color: #5a6268;
            }
            
            /* Alert Messages */
            .alert {
                padding: 15px;
                margin-bottom: 20px;
                border-radius: var(--border-radius);
                display: flex;
                align-items: center;
            }
            
            .alert i {
                margin-right: 10px;
                font-size: 1.2rem;
            }
            
            .alert-success {
                background-color: rgba(40, 167, 69, 0.1);
                color: var(--success);
                border: 1px solid rgba(40, 167, 69, 0.2);
            }
            
            .alert-danger {
                background-color: rgba(220, 53, 69, 0.1);
                color: var(--danger);
                border: 1px solid rgba(220, 53, 69, 0.2);
            }
            
            /* Password Tips */
            .password-tips {
                background-color: var(--light);
                border-radius: var(--border-radius);
                padding: 20px;
                margin-top: 30px;
            }
            
            .password-tips h3 {
                font-size: 1.1rem;
                margin-bottom: 15px;
                color: var(--dark);
            }
            
            .password-tips ul {
                padding-left: 20px;
            }
            
            .password-tips li {
                margin-bottom: 8px;
                color: var(--gray);
                font-size: 0.9rem;
            }
            
            /* Password Strength Meter */
            .password-strength-meter {
                height: 5px;
                background-color: var(--light-gray);
                margin-top: 10px;
                border-radius: 5px;
                overflow: hidden;
            }
            
            .password-strength-meter div {
                height: 100%;
                width: 0;
                transition: var(--transition);
            }
            
            .strength-weak {
                background-color: var(--danger);
                width: 25% !important;
            }
            
            .strength-medium {
                background-color: var(--warning);
                width: 50% !important;
            }
            
            .strength-good {
                background-color: var(--info);
                width: 75% !important;
            }
            
            .strength-strong {
                background-color: var(--success);
                width: 100% !important;
            }
            
            .strength-text {
                font-size: 0.85rem;
                margin-top: 5px;
                color: var(--gray);
            }
            
            /* Footer */
            footer {
                background-color: var(--dark);
                color: var(--white);
                padding: 60px 0 20px;
                margin-top: 60px;
            }
            
            .footer-container {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 30px;
                margin-bottom: 40px;
            }
            
            .footer-col {
                flex: 1;
                min-width: 200px;
            }
            
            .footer-col h3 {
                font-size: 1.2rem;
                margin-bottom: 20px;
                position: relative;
                padding-bottom: 10px;
            }
            
            .footer-col h3:after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 30px;
                height: 2px;
                background-color: var(--primary);
            }
            
            .footer-col ul {
                list-style: none;
            }
            
            .footer-col ul li {
                margin-bottom: 10px;
            }
            
            .footer-col ul li a {
                color: #aaa;
                text-decoration: none;
                transition: var(--transition);
            }
            
            .footer-col ul li a:hover {
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
            
            .copyright {
                text-align: center;
                padding-top: 20px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                color: #aaa;
                font-size: 0.9rem;
            }
            
            /* Responsive Design */
            @media (max-width: 992px) {
                .account-container {
                    flex-direction: column;
                }
                
                .account-sidebar {
                    flex: 0 0 auto;
                    margin-bottom: 30px;
                }
            }
            
            @media (max-width: 768px) {
                .header-container {
                    flex-wrap: wrap;
                }
                
                nav {
                    display: none;
                    width: 100%;
                    margin-top: 15px;
                }
                
                nav.active {
                    display: block;
                }
                
                nav ul {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                nav ul li {
                    margin-left: 0;
                    margin-bottom: 10px;
                    width: 100%;
                }
                
                nav ul li a {
                    display: block;
                    padding: 10px 0;
                }
                
                .mobile-menu-btn {
                    display: block;
                }
                
                .footer-container {
                    flex-direction: column;
                    gap: 40px;
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
            
            <nav id="main-nav">
                <ul>
                    <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="products.php"><i class="fas fa-shopping-basket"></i> Products</a></li>
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
                            <a href="account.php"><i class="fas fa-user"></i> My Account</a>
                            <a href="my_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
                            <a href="change_password.php"><i class="fas fa-key"></i> Change Password</a>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="page-title-section">
        <div class="container">
            <h1 class="page-title">Change Password</h1>
            <p class="page-subtitle">Update your password to keep your account secure</p>
        </div>
    </div>
    
    <div class="container">
        <div class="account-container">
            <div class="account-sidebar">
                <div class="profile-picture-container">
                    <?php
                    // Display profile picture
                    $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.png';
                    ?>
                    <img src="profile_images/<?php echo $profile_pic; ?>" alt="Profile Picture" class="profile-picture">
                    <h2 class="user-name"><?php echo htmlspecialchars($user['username']); ?></h2>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <ul class="account-menu">
                    <li><a href="account.php"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="my_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                    <li><a href="change_password.php" class="active"><i class="fas fa-key"></i> Change Password</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="account-content">
                <h2 class="section-title">Change Your Password</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="current_password">Current Password*</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" value="<?php echo htmlspecialchars($current_password); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password*</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" value="<?php echo htmlspecialchars($new_password); ?>">
                        <div class="password-strength-meter">
                            <div id="strength-meter"></div>
                        </div>
                        <span class="strength-text" id="strength-text"></span>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password*</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" value="<?php echo htmlspecialchars($confirm_password); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Change Password</button>
                    </div>
                    
                    <div class="form-group">
                        <p class="form-text">* Required fields</p>
                    </div>
                </form>
                
                <div class="password-tips">
                    <h3>Password Security Tips</h3>
                    <ul>
                        <li>Use a minimum of 8 characters</li>
                        <li>Include both uppercase and lowercase letters</li>
                        <li>Include at least one number</li>
                        <li>Include at least one special character (e.g., !@#$%^&*)</li>
                        <li>Avoid using easily guessable information like birthdays or names</li>
                        <li>Don't reuse passwords across multiple sites</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-col">
                    <h3>BLASTICAKES & CRAFTS</h3>
                    <p>We create delicious memories with our custom cakes and crafts for all your special occasions.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
                
                <div class="footer-col">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="products.php">Products</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Customer Area</h3>
                    <ul>
                        <li><a href="account.php">My Account</a></li>
                        <li><a href="my_orders.php">My Orders</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="faq.php">FAQ</a></li>
                    </ul>
                </div>
                
                <div class="footer-col">
                    <h3>Contact Us</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Cake Street, Bakery Town</li>
                        <li><i class="fas fa-phone"></i> +1 234 567 8900</li>
                        <li><i class="fas fa-envelope"></i> info@blasticakes.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Sat: 9:00 AM - 6:00 PM</li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> BLASTICAKES & CRAFTS. All rights reserved.</p>
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
            document.getElementById('main-nav').classList.toggle('active');
            
            // Change icon based on menu state
            const icon = this.querySelector('i');
            if (icon.classList.contains('fa-bars')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
        
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthMeter = document.getElementById('strength-meter');
            const strengthText = document.getElementById('strength-text');
            
            // Remove all classes
            strengthMeter.className = '';
            
            // Check password strength
            let strength = 0;
            
            // Check length
            if (password.length >= 8) strength += 1;
            
            // Check for lowercase and uppercase
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
            
            // Check for numbers
            if (password.match(/\d/)) strength += 1;
            
            // Check for special characters
            if (password.match(/[^a-zA-Z\d]/)) strength += 1;
            
            // Update UI based on strength
            if (password.length === 0) {
                strengthMeter.style.width = '0';
                strengthText.textContent = '';
            } else if (strength === 1) {
                strengthMeter.classList.add('strength-weak');
                strengthText.textContent = 'Weak';
                strengthText.style.color = 'var(--danger)';
            } else if (strength === 2) {
                strengthMeter.classList.add('strength-medium');
                strengthText.textContent = 'Medium';
                strengthText.style.color = 'var(--warning)';
            } else if (strength === 3) {
                strengthMeter.classList.add('strength-good');
                strengthText.textContent = 'Good';
                strengthText.style.color = 'var(--info)';
            } else if (strength === 4) {
                strengthMeter.classList.add('strength-strong');
                strengthText.textContent = 'Strong';
                strengthText.style.color = 'var(--success)';
            }
        });
        
        // Confirm password match check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword === confirmPassword) {
                this.setCustomValidity('');
                this.style.borderColor = 'var(--success)';
            } else {
                this.setCustomValidity('Passwords do not match');
                this.style.borderColor = 'var(--danger)';
            }
        });
    </script>
</body>
</html>