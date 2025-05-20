<?php
require_once 'includes/db.php';

// Check if full_name column exists
$check_full_name = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'full_name'");
if (mysqli_num_rows($check_full_name) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN full_name VARCHAR(255) DEFAULT NULL");
    echo "Added full_name column<br>";
}

// Check if address column exists
$check_address = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'address'");
if (mysqli_num_rows($check_address) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN address TEXT DEFAULT NULL");
    echo "Added address column<br>";
}

// Check if phone column exists
$check_phone = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'phone'");
if (mysqli_num_rows($check_phone) == 0) {
    mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT NULL");
    echo "Added phone column<br>";
}

echo "All required columns have been checked and added if needed.";

mysqli_close($conn);
?>
