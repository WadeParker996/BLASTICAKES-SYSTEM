<?php
session_start();
require_once 'includes/db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$product_id = mysqli_real_escape_string($conn, $_GET['id']);

// Get product details
$product_sql = "SELECT * FROM products WHERE id = ?";
$stmt = mysqli_prepare($conn, $product_sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    // Product not found
    header("Location: admin.php");
    exit();
}

$product = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $stock = mysqli_real_escape_string($conn, $_POST['stock']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($description)) {
        $errors[] = "Product description is required";
    }
    
    if (empty($price) || !is_numeric($price) || $price <= 0) {
        $errors[] = "Valid price is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    if (!is_numeric($stock) || $stock < 0) {
        $errors[] = "Valid stock quantity is required";
    }
    
    // If no errors, proceed with update
    if (empty($errors)) {
        // Check if a new image was uploaded
        $image_name = $product['image']; // Default to existing image
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $file_type = $_FILES['image']['type'];
            
            if (in_array($file_type, $allowed_types)) {
                $file_name = time() . '_' . $_FILES['image']['name'];
                $upload_dir = 'images/';
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name)) {
                    $image_name = $file_name;
                } else {
                    $errors[] = "Failed to upload image";
                }
            } else {
                $errors[] = "Invalid image format. Only JPG, PNG and GIF are allowed";
            }
        }
        
        if (empty($errors)) {
            // Update product in database
            $update_sql = "UPDATE products SET 
                          name = ?, 
                          description = ?, 
                          price = ?, 
                          category = ?, 
                          stock = ?, 
                          image = ? 
                          WHERE id = ?";
            
            $update_stmt = mysqli_prepare($conn, $update_sql);
            mysqli_stmt_bind_param(
                $update_stmt, 
                "ssdsssi", 
                $name, 
                $description, 
                $price, 
                $category, 
                $stock, 
                $image_name, 
                $product_id
            );
            
            if (mysqli_stmt_execute($update_stmt)) {
                $success_message = "Product updated successfully!";
                
                // Refresh product data
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($result);
            } else {
                $errors[] = "Error updating product: " . mysqli_error($conn);
            }
        }
    }
}

// Get all categories for dropdown
$categories_sql = "SELECT DISTINCT category FROM products ORDER BY category";
$categories_result = mysqli_query($conn, $categories_sql);
$categories = [];

if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row['category'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - BLASTICAKES & CRAFTS</title>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f6f9;
        }
        header {
            background-color: #343a40;
            color: white;
            padding: 1rem;
        }
        .container {
            width: 90%;
            max-width: 1200px;
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
            background-color: #007bff;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #0069d9;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        textarea {
            height: 150px;
            resize: vertical;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .product-image {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .image-preview {
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>BLASTICAKES & CRAFTS Admin</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="admin.php">Dashboard</a></li>
                    <li><a href="add_product.php">Products</a></li>
                    <li><a href="admin_orders.php">Orders</a></li>
                    <li><a href="admin_users.php">Users</a></li>
                    <li><a href="logout.php">Logout (<?php echo $username; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <h2>Edit Product</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <form action="edit_product.php?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Product Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (₱)</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo ($product['category'] == $category) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="other">Other (New Category)</option>
                    </select>
                </div>
                
                <div id="newCategoryGroup" class="form-group" style="display: none;">
                    <label for="new_category">New Category Name</label>
                    <input type="text" id="new_category" name="new_category">
                </div>
                
                <div class="form-group">
                    <label for="stock">Stock Quantity</label>
                    <input type="number" id="stock" name="stock" min="0" value="<?php echo $product['stock']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="image">Product Image</label>
                    <div class="image-preview">
                        <img src="images/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <p>Current image: <?php echo $product['image']; ?></p>
                    </div>
                    <input type="file" id="image" name="image" accept="image/*">
                    <p><small>Leave empty to keep the current image. Upload a new image to replace it.</small></p>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn">Update Product</button>
                    <a href="admin.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show/hide new category input based on selection
        document.getElementById('category').addEventListener('change', function() {
            const newCategoryGroup = document.getElementById('newCategoryGroup');
            if (this.value === 'other') {
                newCategoryGroup.style.display = 'block';
                document.getElementById('new_category').setAttribute('required', 'required');
            } else {
                newCategoryGroup.style.display = 'none';
                document.getElementById('new_category').removeAttribute('required');
            }
        });
        
        // Preview image before upload
        document.getElementById('image').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.querySelector('.product-image');
                    img.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
