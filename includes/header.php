<?php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Split Bills Effortlessly</title>
    
    <!-- Base URL for JavaScript -->
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    

    <!-- Fonts - Using Inter for better readability -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    
    <!-- Meta tags -->
    <meta name="description" content="Split bills fairly with friends using our smart bill splitter app. Upload receipts, tag participants, and calculate everyone's share instantly.">
    <meta name="keywords" content="bill splitter, expense sharing, receipt splitting, group expenses">
    <meta name="author" content="<?php echo APP_NAME; ?>">
    
    <!-- Open Graph meta tags -->
    <meta property="og:title" content="<?php echo APP_NAME; ?> - Split Bills Effortlessly">
    <meta property="og:description" content="The smartest way to divide expenses. Upload receipts, invite friends, and let our calculator handle the math.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo BASE_URL; ?>">
</head>
<body>
    <!-- Enhanced Navigation -->
    <header>
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>">
                    <i class="bi bi-receipt-cutoff me-2 fs-4"></i>
                    <span class="fw-bold"><?php echo APP_NAME; ?></span>
                </a>
                
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto ms-4">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>">
                                <i class="bi bi-house-door me-1"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>/index.php#how-it-works">
                                <i class="bi bi-info-circle me-1"></i> How It Works
                            </a>
                        </li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/dashboard.php">
                                    <i class="bi bi-speedometer2 me-1"></i> Dashboard
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo BASE_URL; ?>/upload_receipt.php">
                                    <i class="bi bi-upload me-1"></i> Upload Receipt
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="nav-actions">
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="dropdown custom-dropdown">
                                <!-- Changed to button instead of anchor for dropdown toggle -->
                                <button class="nav-link dropdown-toggle d-flex align-items-center pe-0 bg-transparent border-0" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="avatar-circle me-2">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <span class="d-none d-sm-block"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                </button>
                                
                                <!-- Direct regular links instead of dropdown items -->
                                <div class="profile-menu-wrapper shadow-lg border-0" id="profileMenu">
                                    <a class="custom-menu-item d-flex align-items-center" href="<?php echo BASE_URL; ?>/profile.php">
                                        <i class="bi bi-person me-2"></i> Profile
                                    </a>
                                    <hr class="my-2">
                                    <form method="POST" action="<?php echo BASE_URL; ?>/logout.php">
                                        <?= csrfField() ?>
                                        <button type="submit" class="custom-menu-item d-flex align-items-center text-danger bg-transparent border-0 w-100">
                                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="d-flex gap-2">
                                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-box-arrow-in-right me-1"></i> 
                                    <span class="d-none d-sm-inline">Log In</span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-light btn-sm">
                                    <i class="bi bi-person-plus me-1"></i> 
                                    <span class="d-none d-sm-inline">Sign Up</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>


    <!-- Additional CSS for avatar and enhanced elements -->
    <style>
        .avatar-circle {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(59, 130, 246, 0.5);
            color: white;
        }
        
        /* New custom dropdown styles */
        .custom-dropdown {
            position: relative;
        }
        
        .profile-menu-wrapper {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: linear-gradient(145deg, #1e40af 0%, #2563eb 100%);
            border-radius: 12px;
            padding: 8px;
            min-width: 200px;
            z-index: 1000;
        }
        
        .custom-menu-item {
            display: block;
            padding: 8px 16px;
            border-radius: 8px;
            color: white !important;
            text-decoration: none;
            transition: all 0.2s ease;
            margin-bottom: 4px;
        }
        
        .custom-menu-item:hover {
            background: rgba(59, 130, 246, 0.3) !important;
            transform: translateX(4px);
            color: white !important;
        }
        
        .navbar {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%) !important;
        }
        
        .navbar.scrolled {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 50%, #1e40af 100%) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.3);
        }
        
        .navbar-brand {
            color: white !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .navbar-brand:hover {
            color: #60a5fa !important;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
        }
        
        .nav-link:hover {
            color: #60a5fa !important;
            background: rgba(96, 165, 250, 0.2);
        }
        
        .btn-outline-light {
            border-color: rgba(96, 165, 250, 0.5);
            color: #60a5fa;
        }
        
        .btn-outline-light:hover {
            background: #60a5fa;
            border-color: #60a5fa;
            color: white;
        }
        
        .btn-light {
            background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
            border: none;
            color: white;
        }
        
        .btn-light:hover {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            transform: translateY(-2px);
        }
    </style>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced navbar script with custom dropdown behavior -->
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Custom dropdown toggle for profile menu
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggle = document.getElementById('userDropdown');
            const profileMenu = document.getElementById('profileMenu');
            
            if (dropdownToggle && profileMenu) {
                // Show dropdown when clicking the toggle button
                dropdownToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    profileMenu.style.display = profileMenu.style.display === 'block' ? 'none' : 'block';
                });
                
                // Handle clicks outside to close dropdown
                document.addEventListener('click', function(e) {
                    if (!dropdownToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                        profileMenu.style.display = 'none';
                    }
                });
                
                // Make sure menu items work
                document.querySelectorAll('.custom-menu-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        window.location.href = this.getAttribute('href');
                    });
                });
            }
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/fix-header.js"></script>
</body>
</html>
