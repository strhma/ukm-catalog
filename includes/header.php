<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>UKM Catalog</title>
    <meta name="description" content="E-Katalog dan Pemesanan Sederhana untuk UKM/UMKM">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/images/favicon.ico">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="<?php echo BASE_URL; ?>" class="logo">UKM Catalog</a>
                <nav class="nav">
                    <a href="<?php echo BASE_URL; ?>">Home</a>
                    <a href="<?php echo BASE_URL; ?>products.php">Products</a>
                    <a href="<?php echo BASE_URL; ?>cart.php">
                        Cart
                        <span class="cart-count" style="display: none;">0</span>
                    </a>
                    
                    <?php if (isset($auth) && $auth->isLoggedIn()): ?>
                        <?php if ($auth->hasRole('admin')): ?>
                            <a href="<?php echo BASE_URL; ?>admin/dashboard.php">Admin</a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>profile.php">Profile</a>
                        <a href="<?php echo BASE_URL; ?>logout.php">Logout</a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php">Login</a>
                        <a href="<?php echo BASE_URL; ?>register.php">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>