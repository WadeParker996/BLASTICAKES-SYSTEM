<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? '';

// Initialize variables
$name = '';
$description = '';
$price = '';
$stock = '';
$category = '';
$delivery_date = '';
$errors = [];
$success_message = '';
$categories = ['cake', 'craft'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? filter_var($_POST['price'], FILTER_VALIDATE_FLOAT) : false;
    $stock = isset($_POST['stock']) ? filter_var($_POST['stock'], FILTER_VALIDATE_INT) : false;
    $category = trim($_POST['category'] ?? '');
    $delivery_date = trim($_POST['delivery_date'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Description is required";
    }
    
    if ($price === false || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    if ($stock === false || $stock < 0) {
        $errors[] = "Valid stock quantity is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    if (empty($delivery_date)) {
        $errors[] = "Delivery date is required";
    }
    
    // Handle file upload
    $file_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_name = time() . '_' . basename($_FILES['image']['name']);
        $upload_dir = 'images/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $upload_path = $upload_dir . $file_name;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $errors[] = "Failed to upload image";
        }
    } else {
        $errors[] = "Product image is required";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        $delivery_datetime = $delivery_date . ' 00:00:00'; // Add time component
        
        $sql = "INSERT INTO products (name, description, price, stock, category, delivery_datetime, image) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssisss", $name, $description, $price, $stock, $category, $delivery_datetime, $file_name);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Product added successfully!";
                // Reset form fields after successful submission
                $name = $description = $price = $stock = $category = $delivery_date = '';
            } else {
                $errors[] = "Error: " . mysqli_stmt_error($stmt);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #ffa5a5;
            --accent-color: #ffd3d3;
            --dark-color: #333333;
            --light-color: #f9f9f9;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            background-color: #f5f5f5;
            color: var(--dark-color);
        }
        
        header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .container {
            width: 90%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            font-size: 2rem;
        }
        
        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
        }
        
        nav li {
            margin-left: 20px;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        nav a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .page-title {
            margin: 30px 0;
            text-align: center;
            color: var(--primary-color);
            font-size: 2.2rem;
            font-weight: 700;
            position: relative;
        }
        
        .page-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--primary-color);
            margin: 10px auto;
            border-radius: 2px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
            margin-bottom: 30px;
        }
        
        .card-header {
            padding: 20px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            color: var(--dark-color);
            transition: var(--transition);
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--accent-color);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        input[type="file"] {
            padding: 10px 0;
        }
        
        .delivery-note {
            margin-top: 8px;
            font-size: 0.85rem;
            color: #666;
            font-style: italic;
        }
        
        .preview-container {
            margin-top: 15px;
            text-align: center;
        }
        
        #imagePreview {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            display: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1rem;
            text-align: center;
        }
        
        .btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--danger-color);
        }
        
        .error-message ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--success-color);
        }
        
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .dropdown-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            border-radius: var(--border-radius);
            z-index: 1000;
            animation: fadeIn 0.2s;
        }
        
        .dropdown-content a {
            color: var(--dark-color) !important;
            padding: 12px 16px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .dropdown-content a i {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .dropdown-content a:hover {
            background-color: #f5f5f5;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            nav ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            nav li {
                margin: 0;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="admin.php" class="logo">
                    <i class="fas fa-birthday-cake"></i> BLASTICAKES & CRAFTS
                </a>
                <nav>
                    <ul>
                        <li><a href="admin.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <li><a href="add_product.php" class="active"><i class="fas fa-box"></i> Products</a></li>
                        <li><a href="admin_orders.php"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                        <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
                        <li><a href="scan_order.php"><i class="fas fa-qrcode"></i> Scan</a></li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-btn">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($username); ?> <i class="fas fa-caret-down"></i>
                            </a>
                            <div class="dropdown-content">
                                <a href="admin_change_password.php"><i class="fas fa-key"></i> Change Password</a>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                            </div>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <div class="form-container">
    <?php if (!empty($errors)): ?>
        <div class="error-message">
            <strong><i class="fas fa-exclamation-circle"></i> Please correct the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <form action="add_product.php" method="post" enctype="multipart/form-data" class="product-form">
        <div class="form-layout">
            <div class="form-column">
                <div class="form-section">
                    <h4 class="section-title">Basic Information</h4>
                    
                    <div class="form-group">
                        <label for="name">Product Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="Enter product name" required>
                        <div class="field-hint">Choose a descriptive name for your product</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category</label>
                        <div class="select-wrapper">
                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php if ($category === $cat) echo 'selected'; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="price">Price (₱)</label>
                            <div class="input-with-icon">
                                <i class="fas fa-peso-sign"></i>
                                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>" placeholder="0.00" required>
                            </div>
                        </div>
                        
                        <div class="form-group half">
                            <label for="stock">Stock Quantity</label>
                            <div class="input-with-icon">
                                <i class="fas fa-cubes"></i>
                                <input type="number" id="stock" name="stock" min="0" value="<?php echo htmlspecialchars($stock); ?>" placeholder="Enter quantity" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="delivery_date">Delivery Date</label>
                        <div class="input-with-icon">
                            <i class="fas fa-calendar-alt"></i>
                            <input type="date" id="delivery_date" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date); ?>" required>
                        </div>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i> Customers must order at least 
                            <input type="number" id="min_days" name="min_days" min="1" value="5" class="mini-input"> 
                            days before this date
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-column">
                <div class="form-section">
                    <h4 class="section-title">Product Details</h4>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Enter product description" rows="5" required><?php echo htmlspecialchars($description); ?></textarea>
                        <div class="field-hint">Provide detailed information about your product</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Product Image</label>
                        <div class="file-upload-container">
                            <div class="file-upload-button">
                                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.gif" required>
                                <label for="image" class="file-label">
                                    <i class="fas fa-cloud-upload-alt"></i> Choose Image
                                </label>
                            </div>
                            <div class="file-name" id="fileName">No file chosen</div>
                        </div>
                        <div class="preview-container">
                            <div class="preview-placeholder" id="previewPlaceholder">
                                <i class="fas fa-image"></i>
                                <p>Image Preview</p>
                            </div>
                            <img id="imagePreview" alt="Image Preview" />
                        </div>
                        <div class="field-hint">
                            <i class="fas fa-info-circle"></i> Recommended image size: 800x600 pixels. Maximum file size: 2MB.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Product
            </button>
            <a href="admin.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<style>
    .product-form {
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
    }
    
    .form-layout {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        margin-bottom: 30px;
    }
    
    .form-column {
        flex: 1;
        min-width: 300px;
    }
    
    .form-section {
        margin-bottom: 30px;
        padding: 25px;
        background-color: #fff;
        border-radius: 8px;
        border: 1px solid #eaeaea;
    }
    
    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--primary-color);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 60px;
        height: 2px;
        background-color: var(--primary-color);
    }
    
    .form-group {
        margin-bottom: 22px;
    }
    
    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 0;
    }
    
    .half {
        flex: 1;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #444;
        font-size: 0.95rem;
    }
    
    input[type="text"],
    input[type="number"],
    input[type="date"],
    textarea,
    select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
        color: #333;
        transition: all 0.2s ease;
        background-color: #f9f9f9;
    }
    
    input[type="text"]:focus,
    input[type="number"]:focus,
    input[type="date"]:focus,
    textarea:focus,
    select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
        background-color: #fff;
    }
    
    .input-with-icon {
        position: relative;
    }
    
    .input-with-icon i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #888;
    }
    
    .input-with-icon input {
        padding-left: 40px;
    }
    
    .select-wrapper {
        position: relative;
    }
    
    .select-wrapper::after {
        content: '\f107';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #888;
        pointer-events: none;
    }
    
    select {
        appearance: none;
        padding-right: 40px;
        cursor: pointer;
    }
    
    .field-hint {
        margin-top: 6px;
        font-size: 0.8rem;
        color: #777;
    }
    
    .mini-input {
        width: 50px;
        padding: 4px 8px;
        text-align: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: inline-block;
        margin: 0 5px;
    }
    
    .file-upload-container {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .file-upload-button {
        position: relative;
    }
    
    .file-upload-button input[type="file"] {
        position: absolute;
        left: 0;
        top: 0;
        opacity: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
    }
    
    .file-label {
        display: inline-block;
        padding: 10px 20px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .file-label:hover {
        background-color: var(--secondary-color);
    }
    
    .file-name {
        color: #666;
        font-size: 0.9rem;
    }
    
    .preview-container {
        margin-top: 15px;
        border: 2px dashed #ddd;
        border-radius: 8px;
        height: 200px;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow: hidden;
        background-color: #f9f9f9;
    }
    
    .preview-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        color: #aaa;
    }
    
    .preview-placeholder i {
        font-size: 3rem;
        margin-bottom: 10px;
    }
    
    #imagePreview {
        max-width: 100%;
        max-height: 100%;
        display: none;
        object-fit: contain;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        padding: 0 25px 25px;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 25px;
        border-radius: 6px;
        font-weight: 500;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1rem;
        min-width: 150px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: #ff5252;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }
    
    .btn-secondary:hover {
        background-color: #5a6268;
        transform: translateY(-2px);
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
        .form-layout {
            flex-direction: column;
        }
        
        .form-row {
            flex-direction: column;
            gap: 15px;
        }
        
        .form-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
    }
</style>

<script>
    // Image preview functionality
    document.getElementById('image').addEventListener('change', function(e) {
        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('previewPlaceholder');
        const fileName = document.getElementById('fileName');
        const file = e.target.files[0];
        
        if (file) {
            // Update file name display
            fileName.textContent = file.name;
            
            // Show image preview
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                placeholder.style.display = 'none';
            }
            reader.readAsDataURL(file);
        } else {
            fileName.textContent = 'No file chosen';
            preview.style.display = 'none';
            placeholder.style.display = 'flex';
        }
    });
    
    // Auto-hide success message after 5 seconds
    setTimeout(function() {
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            successMessage.style.opacity = '0';
            successMessage.style.transition = 'opacity 0.5s';
            setTimeout(function() {
                successMessage.style.display = 'none';
            }, 500);
        }
    }, 5000);
</script>