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
$error = $success = "";

// Process form submission for profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Get form data
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Validate input
    if (empty($fullname) || empty($email)) {
        $error = "Name and email are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Handle profile picture upload
        $profile_picture = $user['profile_picture']; // Default to current picture
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            // Verify file extension
            if (in_array(strtolower($filetype), $allowed)) {
                // Create unique filename
                $new_filename = uniqid('profile_') . '.' . $filetype;
                $upload_dir = 'profile_images/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if it exists and is not the default
                    if (!empty($user['profile_picture']) && $user['profile_picture'] != 'default_profile.png' && file_exists($upload_dir . $user['profile_picture'])) {
                        unlink($upload_dir . $user['profile_picture']);
                    }
                    
                    $profile_picture = $new_filename;
                } else {
                    $error = "Failed to upload profile picture";
                }
            } else {
                $error = "Invalid file type. Only JPG, JPEG, PNG and GIF are allowed";
            }
        }
        
        if (empty($error)) {
            // Update user profile in database
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "sssssi", $fullname, $email, $phone, $address, $profile_picture, $user_id);
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);
            } else {
                $error = "Error updating profile: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($update_stmt);
        }
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
    <title>My Account - BLASTICAKES & CRAFTS</title>
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
        
        /* Profile Picture Upload */
        .profile-upload-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .profile-upload-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            display: block;
        }
        
        .profile-upload-btn {
            display: inline-block;
            background-color: var(--light);
            color: var(--dark);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .profile-upload-btn:hover {
            background-color: var(--light-gray);
        }
        
        .profile-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 0;
            height: 0;
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
            <h1 class="page-title">My Account</h1>
            <p class="page-subtitle">Manage your profile information and account settings</p>
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
                    <li><a href="account.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="my_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                    <li><a href="change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <div class="account-content">
                <h2 class="section-title">Profile Information</h2>
                
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
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                    <div class="profile-upload-container">
                        <?php
                        // Display profile picture
                        $profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : 'default_profile.png';
                        ?>
                        <img src="profile_images/<?php echo $profile_pic; ?>" alt="Profile Picture" id="profile-preview" class="profile-upload-preview">
                        <label for="profile_picture" class="profile-upload-btn">
                            <i class="fas fa-camera"></i> Change Profile Picture
                        </label>
                        <input type="file" name="profile_picture" id="profile_picture" class="profile-upload-input" accept="image/*">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        <span class="form-text">Username cannot be changed</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="fullname">Full Name*</label>
                        <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address*</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </div>
                    
                    <div class="form-group">
                        <p class="form-text">* Required fields</p>
                    </div>
                </form>
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
                        <li><a href="contact.php">Contact Us</a></li>
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
        
        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>