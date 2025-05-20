<?php
session_start();
require_once 'includes/db.php';
require_once 'header.php';
$error = "";
$success = "";
$form_submitted = false;

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms_accepted = isset($_POST['terms']) ? 1 : 0;

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $error = "Password must be at least 8 characters, include an uppercase letter, a number, and a special character";
    } elseif (!$terms_accepted) {
        $error = "You must accept the Terms and Conditions to register";
    } else {
        $sql = "SELECT id FROM users WHERE username = '$username'";
        $result = mysqli_query($conn, $sql);
        if (mysqli_num_rows($result) > 0) {
            $error = "Username already exists";
        } else {
            $sql = "SELECT id FROM users WHERE email = '$email'";
            $result = mysqli_query($conn, $sql);
            if (mysqli_num_rows($result) > 0) {
                $error = "Email already exists";
            } else {
                $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$password')";
                if (mysqli_query($conn, $sql)) {
                    $success = "Registration successful! You can now login.";
                    $form_submitted = true;
                    $username = "";
                    $email = "";
                    $password = "";
                    $confirm_password = "";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - BLASTICAKES & CRAFTS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
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
            overflow-y: auto;
        }

        .signup-container {
            width: 100%;
            max-width: 550px;
            opacity: 0;
            animation: fadeInUp 0.8s 0.3s forwards;
        }

        .signup-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .signup-header h1 {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .signup-header p {
            color: var(--dark-gray);
            font-size: 0.95rem;
        }

        .signup-logo {
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

        .signup-logo i {
            font-size: 2.5rem;
            color: var(--white);
        }

        .form-floating {
            margin-bottom: 1.25rem;
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

        .btn-signup {
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

        .btn-signup:hover, .btn-signup:focus {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }

        .btn-signup:active {
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

        .success-message {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 4px solid var(--success);
            font-size: 0.9rem;
            animation: fadeIn 0.5s;
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--medium-gray);
            color: var(--dark-gray);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
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
        
        /* Password strength indicator */
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .password-requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
            color: var(--dark-gray);
            transition: var(--transition);
        }

        .password-requirement i {
            margin-right: 0.5rem;
            font-size: 0.75rem;
        }

        .valid {
            color: var(--success);
        }

        .invalid {
            color: var(--danger);
        }

        .progress {
            height: 5px;
            margin-top: 0.5rem;
            border-radius: 2.5px;
        }

        .progress-bar {
            transition: width 0.5s ease;
        }

        /* Terms and conditions */
        .terms-container {
            max-height: 150px;
            overflow-y: auto;
            padding: 1rem;
            background-color: var(--light-gray);
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid var(--medium-gray);
            font-size: 0.9rem;
        }

        .terms-container h5 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .terms-container ol {
            padding-left: 1.25rem;
            margin-bottom: 0;
        }

        .terms-container li {
            margin-bottom: 0.5rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(255, 107, 107, 0.25);
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
                padding: 2rem;
                max-height: 100vh;
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
            
            .signup-container {
                padding: 0;
            }
            
            .signup-header h1 {
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
                        <img src="https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1087&q=80" 
                 alt="Delicious Cake" class="cake-image">
            <div class="image-overlay">
                <h2>Join Our Sweet Community</h2>
                <p>Create an account to order custom cakes, save your favorites, and get special offers</p>
            </div>
        </div>

        <!-- Right side - Signup Form -->
        <div class="form-section">
            <div class="signup-container">
                <!-- Logo and Header -->
                <div class="signup-header">
                    <div class="signup-logo">
                        <i class="fas fa-birthday-cake"></i>
                    </div>
                    <h1>Create Your Account</h1>
                    <p>Join BLASTICAKES & CRAFTS today</p>
                </div>

                <!-- Error/Success Messages -->
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Signup Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="signup-form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="username" name="username" 
                                    placeholder="Username" value="<?php echo $form_submitted ? '' : (isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''); ?>" required>
                                <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" 
                                    placeholder="Email" value="<?php echo $form_submitted ? '' : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>" required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating mb-3 password-field">
                                <input type="password" class="form-control" id="password" name="password" 
                                    placeholder="Password" onkeyup="checkPassword(this.value)" onfocus="showPasswordRequirements()" 
                                    value="<?php echo $form_submitted ? '' : (isset($_POST['password']) ? htmlspecialchars($_POST['password']) : ''); ?>" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                <span class="toggle-password" onclick="togglePasswordVisibility('password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div id="password-requirements" class="password-strength" style="display: none;">
                                <div id="length" class="password-requirement">
                                    <i class="fas fa-circle"></i> At least 8 characters
                                </div>
                                <div id="uppercase" class="password-requirement">
                                    <i class="fas fa-circle"></i> At least one uppercase letter
                                </div>
                                <div id="lowercase" class="password-requirement">
                                    <i class="fas fa-circle"></i> At least one lowercase letter
                                </div>
                                <div id="number" class="password-requirement">
                                    <i class="fas fa-circle"></i> At least one number
                                </div>
                                <div id="special" class="password-requirement">
                                    <i class="fas fa-circle"></i> At least one special character
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating mb-3 password-field">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                    placeholder="Confirm Password" required>
                                <label for="confirm_password"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                    <i class="fas fa-eye"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="terms-container mb-3">
                        <h5>Terms and Conditions</h5>
                        <ol>
                            <li>All personal information provided is accurate and will be kept confidential.</li>
                            <li>You are responsible for maintaining the security of your account credentials.</li>
                            <li>BLASTICAKES & CRAFTS reserves the right to modify or cancel orders in certain circumstances.</li>
                            <li>Delivery times are estimates and may vary based on location and availability.</li>
                            <li>Payment information will be processed securely through our payment partners.</li>
                        </ol>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms" 
                            <?php echo $form_submitted ? '' : (isset($_POST['terms']) ? 'checked' : ''); ?> required>
                        <label class="form-check-label" for="terms">I agree to the Terms and Conditions</label>
                    </div>
                    
                    <button type="submit" class="btn btn-signup">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = passwordField.parentNode.querySelector('.toggle-password i');
            
            if (passwordField.type === "password") {
                passwordField.type = "text";
                icon.classList.replace("fa-eye", "fa-eye-slash");
            } else {
                passwordField.type = "password";
                icon.classList.replace("fa-eye-slash", "fa-eye");
            }
        }
        
        function showPasswordRequirements() {
            document.getElementById('password-requirements').style.display = 'block';
        }
        
        function hidePasswordRequirements() {
            if (document.activeElement !== document.getElementById('password')) {
                document.getElementById('password-requirements').style.display = 'none';
            }
        }
        
        function checkPassword(password) {
            let strength = 0;
            
            // Check length
            const lengthRequirement = document.getElementById('length');
            if (password.length >= 8) {
                lengthRequirement.classList.add('valid');
                lengthRequirement.classList.remove('invalid');
                lengthRequirement.querySelector('i').classList.replace('fa-circle', 'fa-check');
                strength += 20;
            } else {
                lengthRequirement.classList.add('invalid');
                lengthRequirement.classList.remove('valid');
                lengthRequirement.querySelector('i').classList.replace('fa-check', 'fa-circle');
            }
            
            // Check uppercase
            const uppercaseRequirement = document.getElementById('uppercase');
            if (/[A-Z]/.test(password)) {
                uppercaseRequirement.classList.add('valid');
                uppercaseRequirement.classList.remove('invalid');
                uppercaseRequirement.querySelector('i').classList.replace('fa-circle', 'fa-check');
                strength += 20;
            } else {
                uppercaseRequirement.classList.add('invalid');
                uppercaseRequirement.classList.remove('valid');
                uppercaseRequirement.querySelector('i').classList.replace('fa-check', 'fa-circle');
            }
            
            // Check lowercase
            const lowercaseRequirement = document.getElementById('lowercase');
            if (/[a-z]/.test(password)) {
                lowercaseRequirement.classList.add('valid');
                lowercaseRequirement.classList.remove('invalid');
                lowercaseRequirement.querySelector('i').classList.replace('fa-circle', 'fa-check');
                strength += 20;
            } else {
                lowercaseRequirement.classList.add('invalid');
                lowercaseRequirement.classList.remove('valid');
                lowercaseRequirement.querySelector('i').classList.replace('fa-check', 'fa-circle');
            }
            
            // Check number
            const numberRequirement = document.getElementById('number');
            if (/[0-9]/.test(password)) {
                numberRequirement.classList.add('valid');
                numberRequirement.classList.remove('invalid');
                numberRequirement.querySelector('i').classList.replace('fa-circle', 'fa-check');
                strength += 20;
            } else {
                numberRequirement.classList.add('invalid');
                numberRequirement.classList.remove('valid');
                numberRequirement.querySelector('i').classList.replace('fa-check', 'fa-circle');
            }
            
            // Check special character
            const specialRequirement = document.getElementById('special');
            if (/[^A-Za-z0-9]/.test(password)) {
                specialRequirement.classList.add('valid');
                specialRequirement.classList.remove('invalid');
                specialRequirement.querySelector('i').classList.replace('fa-circle', 'fa-check');
                strength += 20;
            } else {
                specialRequirement.classList.add('invalid');
                specialRequirement.classList.remove('valid');
                specialRequirement.querySelector('i').classList.replace('fa-check', 'fa-circle');
            }
            
            // Update progress bar
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.width = strength + '%';
            
            // Update progress bar color
            if (strength < 40) {
                progressBar.classList.remove('bg-warning', 'bg-success');
                progressBar.classList.add('bg-danger');
            } else if (strength < 80) {
                progressBar.classList.remove('bg-danger', 'bg-success');
                progressBar.classList.add('bg-warning');
            } else {
                progressBar.classList.remove('bg-danger', 'bg-warning');
                progressBar.classList.add('bg-success');
            }
        }
        
        // Hide password requirements when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.id !== 'password' && !event.target.closest('#password-requirements')) {
                hidePasswordRequirements();
            }
        });
        
        // Check password match
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password === confirmPassword) {
                this.style.borderColor = 'var(--success)';
            } else {
                this.style.borderColor = 'var(--danger)';
            }
        });
        
        // Clear form if registration was successful
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('signup-form').reset();
            
            // Redirect to login page after 3 seconds
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 3000);
        });
        <?php endif; ?>
        
        // Preload the cake image for smoother animation
        document.addEventListener('DOMContentLoaded', function() {
            const cakeImage = new Image();
            cakeImage.src = document.querySelector('.cake-image').src;
        });
    </script>
</body>
</html>

