<?php
session_start();
require_once 'includes/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        $error = "Username and password are required";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (!$conn) {
            $error = "Database connection failed: " . mysqli_connect_error();
        } else {
            // First, check what fields are available in the users table
            $check_fields = mysqli_query($conn, "DESCRIBE users");
            $fields = [];
            while ($field = mysqli_fetch_assoc($check_fields)) {
                $fields[] = $field['Field'];
            }
            
            $sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                if ($result && mysqli_num_rows($result) === 1) {
                    $user = mysqli_fetch_assoc($result);
                    
                    // Debug - you can remove this in production
                    error_log("User data: " . print_r($user, true));
                    error_log("Input password: " . $password);
                    error_log("Stored PASSWORD: " . $user['PASSWORD']);
                    
                    // Plain text password check
                    if ($password === $user['PASSWORD']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['is_admin'] = $user['is_admin'];
                        
                        header("Location: " . ($user['is_admin'] == 1 ? "admin.php" : "index.php"));
                        exit();
                    } else {
                        $error = "Invalid username or password.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "SQL Error: " . mysqli_error($conn);
            }
        }
    }
}
require_once 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Cake Shop</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Custom CSS with modern design principles */
        :root {
            --primary-color: #ff6b6b;
            --primary-dark: #ff5252;
            --primary-light: #ffb8b8;
            --secondary-color: #4ecdc4;
            --text-color: #333333;
            --light-gray: #f8f9fa;
            --medium-gray: #e9ecef;
            --dark-gray: #6c757d;
            --danger: #dc3545;
            --success: #28a745;
            --white: #ffffff;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
            min-height: 100vh;
            overflow-x: hidden;
            margin: 0;
            padding: 0;
        }
        
        /* Split layout container */
        .split-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }
        
        /* Left side - Image section */
        .image-section {
            flex: 1;
            background-color: var(--primary-light);
            position: relative;
            overflow: hidden;
            display: none; /* Hidden on mobile by default */
        }
        
        .cake-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            opacity: 0;
            animation: fadeIn 1.2s forwards;
        }
        
        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.7) 0%, rgba(255, 107, 107, 0.4) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: var(--white);
            padding: 2rem;
            text-align: center;
        }
        
        .image-overlay h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.8s 0.5s forwards;
        }
        
        .image-overlay p {
            font-size: 1.1rem;
            max-width: 80%;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transform: translateY(20px);
            animation: slideUp 0.8s 0.8s forwards;
        }
        
        /* Right side - Form section */
        .form-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: var(--white);
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
            opacity: 0;
            animation: fadeInUp 0.8s 0.3s forwards;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            background-color: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }
        
        .login-logo i {
            font-size: 2.5rem;
            color: var(--white);
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
        
        .form-floating {
            margin-bottom: 1.25rem;
        }
        
        .form-floating label {
            color: var(--dark-gray);
        }
        
        .form-control {
            border: 2px solid var(--medium-gray);
            border-radius: 10px;
            padding: 12px 20px;
            height: 58px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
        }
        
        .password-field {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--dark-gray);
            z-index: 10;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: var(--primary-color);
        }
        
        .btn-login {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            margin-top: 0.5rem;
            transition: var(--transition);
            height: 54px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.2);
        }
        
        .btn-login:hover, .btn-login:focus {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid var(--danger);
            font-size: 0.9rem;
            animation: shake 0.5s;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--medium-gray);
            color: var(--dark-gray);
            font-size: 0.95rem;
        }
        
        .signup-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .signup-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        /* Responsive adjustments */
        @media (min-width: 992px) {
            .image-section {
                display: block; /* Show on larger screens */
            }
            
            .form-section {
                padding: 3rem;
            }
        }
        
        @media (max-width: 991.98px) {
            .split-container {
                flex-direction: column;
            }
            
            .image-section {
                display: block;
                min-height: 300px;
            }
            
            .form-section {
                padding: 2rem 1.5rem;
            }
            
            .login-container {
                padding: 1.5rem 1rem;
            }
            
            .login-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <!-- Left side - Cake Image -->
        <div class="image-section">
            <!-- You can replace this URL with your actual cake image -->
            <img src="https://images.unsplash.com/photo-1578985545062-69928b1d9587?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1089&q=80"
                  alt="Delicious Cake" class="cake-image">
            <div class="image-overlay">
                <h2>BLASTICAKES & CRAFTS</h2>
                <p>Indulge in our handcrafted cakes and unique crafts for all your special occasions</p>
            </div>
        </div>
        
        <!-- Right side - Login Form -->
        <div class="form-section">
            <div class="login-container">
                <!-- Logo and Header -->
                <div class="login-header">
                    <div class="login-logo">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <h1>Welcome Back</h1>
                    <p>Please login to your account</p>
                </div>
                
                <!-- Error Message -->
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="loginForm">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username"
                             value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                    </div>
                    
                    <div class="form-floating password-field">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                                <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <span>Login</span>
                        <i class="fas fa-sign-in-alt ms-2"></i>
                    </button>
                </form>
                
                <div class="signup-link">
                    Don't have an account? <a href="signup.php">Sign up here</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePasswordVisibility() {
            const pwField = document.getElementById("password");
            const icon = document.querySelector(".toggle-password i");
            
            if (pwField.type === "password") {
                pwField.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                pwField.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }

        // Form animations and validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const inputs = form.querySelectorAll('input');
            
            // Add focus animations to inputs
            inputs.forEach(input => {
                // Add animation when input receives focus
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'translateY(-5px)';
                    this.parentElement.style.transition = 'transform 0.3s ease';
                });
                
                // Remove animation when input loses focus
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'translateY(0)';
                });
            });
            
            // Button animation on form submission
            form.addEventListener('submit', function(e) {
                const button = document.querySelector('.btn-login');
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Logging in...';
                button.disabled = true;
                
                // We don't prevent default here as we want the form to actually submit
                // This is just for visual feedback
            });
            
            // Preload the cake image for smoother animation
            const cakeImage = new Image();
            cakeImage.src = document.querySelector('.cake-image').src;
        });
    </script>
</body>
</html>
