<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';

// Get user info
$username = $_SESSION['username'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: " . ($is_admin ? "admin.php" : "products.php"));
    exit;
}

$product_id = $_GET['id'];

// Get product details
$sql = "SELECT * FROM products WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    // Product not found
    header("Location: " . ($is_admin ? "admin.php" : "products.php"));
    exit;
}

$product = mysqli_fetch_assoc($result);

// Get related products
$category = $product['category'];
$related_sql = "SELECT * FROM products WHERE category = ? AND id != ? LIMIT 4";
$related_stmt = mysqli_prepare($conn, $related_sql);
mysqli_stmt_bind_param($related_stmt, "si", $category, $product_id);
mysqli_stmt_execute($related_stmt);
$related_result = mysqli_stmt_get_result($related_stmt);
$related_products = [];

while ($related = mysqli_fetch_assoc($related_result)) {
    $related_products[] = $related;
}

// Set page title for header
$page_title = htmlspecialchars($product['name']);
include 'header.php';
?>

<div class="container">
    <!-- Breadcrumb navigation -->
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="separator"><i class="fas fa-chevron-right"></i></span>
        <a href="products.php">Products</a>
        <span class="separator"><i class="fas fa-chevron-right"></i></span>
        <a href="products.php?category=<?php echo urlencode($product['category']); ?>"><?php echo ucfirst(htmlspecialchars($product['category'])); ?></a>
        <span class="separator"><i class="fas fa-chevron-right"></i></span>
        <span><?php echo htmlspecialchars($product['name']); ?></span>
    </div>

    <!-- Product Details Section -->
    <div class="product-details-container" data-aos="fade-up">
        <div class="product-image-container">
            <span class="product-category-badge"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
            <img src="images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
            
            <?php if ($is_admin): ?>
                <div class="admin-actions">
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="admin-action-btn">
                        <i class="fas fa-edit"></i> Edit Product
                    </a>
                    <button type="button" class="admin-action-btn delete-btn" data-id="<?php echo $product['id']; ?>">
                        <i class="fas fa-trash-alt"></i> Delete Product
                    </button>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="product-info">
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="product-price">
                <span class="currency">₱</span><?php echo number_format($product['price'], 2); ?>
            </div>
            
            <div class="product-meta">
                <div class="product-meta-item">
                    <i class="fas fa-box"></i>
                    <strong>Availability:</strong>
                    <?php if($product['stock'] > 10): ?>
                        <span class="stock-status in-stock">In Stock (<?php echo $product['stock']; ?> available)</span>
                    <?php elseif($product['stock'] > 0): ?>
                        <span class="stock-status low-stock">Low Stock (Only <?php echo $product['stock']; ?> left)</span>
                    <?php else: ?>
                        <span class="stock-status out-of-stock">Out of Stock</span>
                    <?php endif; ?>
                </div>
                
                <?php if(isset($product['lead_time']) && !empty($product['lead_time'])): ?>
                    <div class="product-meta-item">
                        <i class="far fa-clock"></i>
                        <strong>Lead Time:</strong>
                        <span><?php echo $product['lead_time']; ?> days</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="product-description">
                <h3>Description</h3>
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>
            
            <div class="product-actions">
                <?php if (!$is_admin): ?>
                    <?php if ($product['stock'] > 0): ?>
                        <form action="add_to_cart.php" method="post" class="add-to-cart-form">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            
                            <div class="quantity-selector">
                                <label for="quantity">Quantity:</label>
                                <div class="quantity-input">
                                    <button type="button" class="quantity-btn" id="decrease-qty">-</button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" readonly>
                                    <button type="button" class="quantity-btn" id="increase-qty">+</button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-disabled" disabled>
                            <i class="fas fa-shopping-cart"></i> Out of Stock
                        </button>
                    <?php endif; ?>
                <?php endif; ?>
                
                <a href="<?php echo $is_admin ? 'admin.php' : 'products.php'; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to <?php echo $is_admin ? 'Admin Panel' : 'Products'; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Related Products Section -->
    <?php if (!empty($related_products) && !$is_admin): ?>
        <div class="related-products-section" data-aos="fade-up">
            <h2 class="section-title">You May Also Like</h2>
            <div class="products-grid">
                <?php foreach ($related_products as $index => $related): ?>
                    <div class="product-card" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="product-image">
                            <span class="product-category"><?php echo htmlspecialchars(ucfirst($related['category'])); ?></span>
                            <img src="images/<?php echo htmlspecialchars($related['image']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                        </div>
                        <div class="product-details">
                            <h3 class="product-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                            <p class="product-price">₱<?php echo number_format($related['price'], 2); ?></p>
                            <div class="product-actions">
                                <a href="view_product.php?id=<?php echo $related['id']; ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($related['stock'] > 0): ?>
                                    <form action="add_to_cart.php" method="post" style="display:inline;">
                                        <input type="hidden" name="product_id" value="<?php echo $related['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="add-to-cart-btn">
                                            <i class="fas fa-cart-plus"></i> Add
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="add-to-cart-btn disabled" disabled>
                                        <i class="fas fa-times"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal for Admin -->
<?php if ($is_admin): ?>
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Confirm Deletion</h2>
        <p>Are you sure you want to delete this product? This action cannot be undone.</p>
        <div class="modal-actions">
            <form id="deleteForm" action="delete_product.php" method="post">
                <input type="hidden" name="product_id" id="delete_product_id">
                <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
    /* Product Details Page Styles */
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .breadcrumb {
        display: flex;
        align-items: center;
        margin-bottom: 30px;
        font-size: 0.9rem;
        flex-wrap: wrap;
    }
    
    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .breadcrumb a:hover {
        color: var(--primary-dark);
        text-decoration: underline;
    }
    
    .breadcrumb .separator {
        margin: 0 10px;
        color: #ccc;
        font-size: 0.8rem;
    }
    
    .breadcrumb span:last-child {
        color: #777;
        font-weight: 500;
    }
    
    .product-details-container {
        display: flex;
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 40px;
    }
    
    .product-image-container {
        flex: 1;
        padding: 40px;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: #f9f9f9;
        min-height: 400px;
    }
    
    .product-image {
        max-width: 100%;
        max-height: 400px;
        object-fit: contain;
        transition: transform 0.3s ease;
    }
    
    .product-image:hover {
        transform: scale(1.05);
    }
    
    .product-category-badge {
        position: absolute;
        top: 20px;
        left: 20px;
        background-color: var(--primary);
        color: white;
        padding: 5px 15px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .admin-actions {
        position: absolute;
        bottom: 20px;
        left: 20px;
        display: flex;
        gap: 10px;
    }
    
    .admin-action-btn {
        background-color: white;
        color: #333;
        border: 1px solid #ddd;
        padding: 8px 15px;
        border-radius: var(--border-radius);
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
    
    .admin-action-btn i {
        margin-right: 5px;
    }
    
    .admin-action-btn:hover {
        background-color: #f5f5f5;
    }
    
    .admin-action-btn.delete-btn {
        color: var(--danger);
        border-color: var(--danger);
    }
    
    .admin-action-btn.delete-btn:hover {
        background-color: var(--danger);
        color: white;
    }
    
    .product-info {
        flex: 1;
        padding: 40px;
        border-left: 1px solid #eee;
    }
    
    .product-title {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #333;
        font-weight: 600;
    }
    
    .product-price {
        font-size: 1.8rem;
        color: var(--primary);
        margin-bottom: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
    }
    
    .currency {
        font-size: 1.2rem;
        margin-right: 5px;
        font-weight: 400;
    }
    
    .product-meta {
        margin-bottom: 25px;
        padding-bottom: 25px;
        border-bottom: 1px solid #eee;
    }
    
    .product-meta-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .product-meta-item i {
        margin-right: 10px;
        color: #777;
        width: 20px;
        text-align: center;
    }
    
    .product-meta-item strong {
        margin-right: 10px;
        font-weight: 500;
    }
    
    .stock-status {
                padding: 3px 10px;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .in-stock {
        background-color: #d4edda;
        color: #155724;
    }
    
    .low-stock {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .out-of-stock {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .product-description {
        margin-bottom: 30px;
        line-height: 1.8;
        color: #555;
    }
    
    .product-description h3 {
        font-size: 1.2rem;
        margin-bottom: 15px;
        color: #333;
        font-weight: 600;
    }
    
    .product-actions {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .add-to-cart-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-start;
        margin-bottom: 15px;
        width: 100%;
    }
    
    .quantity-selector {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .quantity-selector label {
        font-weight: 500;
        color: #555;
    }
    
    .quantity-input {
        display: flex;
        align-items: center;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        overflow: hidden;
    }
    
    .quantity-btn {
        background-color: #f5f5f5;
        border: none;
        color: #333;
        font-size: 1.2rem;
        width: 40px;
        height: 40px;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .quantity-btn:hover {
        background-color: #e5e5e5;
    }
    
    .quantity-input input {
        width: 60px;
        height: 40px;
        border: none;
        text-align: center;
        font-size: 1rem;
        font-weight: 500;
        appearance: textfield;
    }
    
    .quantity-input input::-webkit-outer-spin-button,
    .quantity-input input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 25px;
        border-radius: var(--border-radius);
        font-weight: 500;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s;
        border: none;
        text-decoration: none;
    }
    
    .btn i {
        margin-right: 8px;
    }
    
    .btn-primary {
        background-color: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background-color: var(--primary-dark);
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background-color: #f5f5f5;
        color: #333;
    }
    
    .btn-secondary:hover {
        background-color: #e5e5e5;
        transform: translateY(-2px);
    }
    
    .btn-danger {
        background-color: var(--danger);
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #c82333;
    }
    
    .btn-disabled {
        background-color: #f5f5f5;
        color: #999;
        cursor: not-allowed;
    }
    
    /* Related Products Section */
    .related-products-section {
        margin-top: 60px;
    }
    
    .section-title {
        font-size: 1.8rem;
        margin-bottom: 30px;
        color: #333;
        text-align: center;
        position: relative;
        padding-bottom: 15px;
    }
    
    .section-title:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 3px;
        background-color: var(--primary);
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 30px;
    }
    
    .product-card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    
    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }
    
    .product-card .product-image {
        position: relative;
        height: 200px;
        overflow: hidden;
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
    
    .product-card .product-category {
        position: absolute;
        top: 10px;
        left: 10px;
        background-color: var(--primary);
        color: white;
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 500;
        text-transform: uppercase;
    }
    
    .product-card .product-details {
        padding: 20px;
    }
    
    .product-card .product-title {
        font-size: 1.1rem;
        margin-bottom: 10px;
        font-weight: 600;
        color: #333;
        display: -webkit-box;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.8rem;
    }
    
    .product-card .product-price {
        font-size: 1.1rem;
        color: var(--primary);
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .product-card .product-actions {
        display: flex;
        justify-content: space-between;
        gap: 10px;
    }
    
    .view-btn, .add-to-cart-btn {
        flex: 1;
        padding: 8px 0;
        text-align: center;
        border-radius: var(--border-radius);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    
    .view-btn {
        background-color: #f5f5f5;
        color: #333;
        border: none;
    }
    
    .view-btn:hover {
        background-color: #e5e5e5;
    }
    
    .add-to-cart-btn {
        background-color: var(--primary);
        color: white;
        border: none;
    }
    
    .add-to-cart-btn:hover {
        background-color: var(--primary-dark);
    }
    
    .add-to-cart-btn.disabled {
        background-color: #f5f5f5;
        color: #999;
        cursor: not-allowed;
    }
    
    .add-to-cart-btn i, .view-btn i {
        margin-right: 5px;
        font-size: 0.9rem;
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        animation: fadeIn 0.3s;
    }
    
    .modal-content {
        background-color: white;
        margin: 10% auto;
        padding: 30px;
        border-radius: var(--border-radius);
        max-width: 500px;
        box-shadow: 0 5px 30px rgba(0,0,0,0.3);
        animation: slideIn 0.3s;
    }
    
    .modal-content h2 {
        margin-top: 0;
        color: #333;
        font-size: 1.5rem;
    }
    
    .modal-content p {
        color: #555;
        margin-bottom: 25px;
    }
    
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        margin-top: -15px;
    }
    
    .close:hover {
        color: #333;
    }
    
    @keyframes fadeIn {
        from {opacity: 0;}
        to {opacity: 1;}
    }
    
    @keyframes slideIn {
        from {transform: translateY(-50px); opacity: 0;}
        to {transform: translateY(0); opacity: 1;}
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .product-details-container {
            flex-direction: column;
        }
        
        .product-image-container, .product-info {
            padding: 30px;
        }
        
        .product-info {
            border-left: none;
            border-top: 1px solid #eee;
        }
    }
    
    @media (max-width: 768px) {
        .product-image-container, .product-info {
            padding: 20px;
        }
        
        .product-title {
            font-size: 1.8rem;
        }
        
        .product-price {
            font-size: 1.5rem;
        }
        
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .admin-actions {
            position: static;
            margin-top: 20px;
        }
    }
    
    @media (max-width: 576px) {
        .breadcrumb {
            font-size: 0.8rem;
        }
        
        .product-category-badge {
            font-size: 0.7rem;
        }
        
        .product-title {
            font-size: 1.5rem;
        }
        
        .product-actions {
            flex-direction: column;
        }
        
        .btn {
            width: 100%;
        }
        
        .products-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quantity selector functionality
        const quantityInput = document.getElementById('quantity');
        const decreaseBtn = document.getElementById('decrease-qty');
        const increaseBtn = document.getElementById('increase-qty');
        
        if (quantityInput && decreaseBtn && increaseBtn) {
            decreaseBtn.addEventListener('click', function() {
                let currentValue = parseInt(quantityInput.value);
                if (currentValue > 1) {
                    quantityInput.value = currentValue - 1;
                }
            });
            
            increaseBtn.addEventListener('click', function() {
                let currentValue = parseInt(quantityInput.value);
                let maxValue = parseInt(quantityInput.getAttribute('max'));
                if (currentValue < maxValue) {
                    quantityInput.value = currentValue + 1;
                }
            });
        }
        
        // Delete modal functionality for admin
        const deleteButtons = document.querySelectorAll('.delete-btn');
        const deleteModal = document.getElementById('deleteModal');
        const closeBtn = document.querySelector('.close');
        const cancelBtn = document.getElementById('cancelDelete');
        const deleteProductIdInput = document.getElementById('delete_product_id');
        
        if (deleteButtons.length > 0 && deleteModal) {
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const productId = this.getAttribute('data-id');
                    deleteProductIdInput.value = productId;
                    deleteModal.style.display = 'block';
                });
            });
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                });
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', function() {
                    deleteModal.style.display = 'none';
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
        }
    });
</script>

<?php include 'footer.php'; ?>

