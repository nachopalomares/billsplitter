<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get basic stats
$stats = getHeroStats();
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
?>
<?php include 'includes/header.php'; ?>


<main>
    <!-- Enhanced Hero Section -->
    <section class="hero-section bg-primary py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="hero-content">
                        <h1 class="display-4 fw-bold text-primary mb-3 text-muted">BillSplitter App</h1>
                        <p class="lead text-muted mb-4">
                            Split your bills with friends easily! No more calculator math or awkward conversations about who owes what.
                        </p>
                        
                        <!-- Enhanced stats display -->
                        <div class="stats-container mb-4">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="stat-card text-center p-3 bg-light rounded">
                                        <h3 class="text-primary mb-1"><?= $stats['bills'] === null ? 'Unavailable' : number_format($stats['bills']) ?></h3>
                                        <p class="small text-muted mb-0">Saved bills</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-card text-center p-3 bg-light rounded">
                                        <h3 class="text-success mb-1"><?= $stats['users'] === null ? 'Unavailable' : number_format($stats['users']) ?></h3>
                                        <p class="small text-muted mb-0">Registered accounts</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Updated action buttons with your preferred style -->
                        <div class="hero-actions mt-4">
                            <?php if ($isLoggedIn): ?>
                                <a href="dashboard.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-speedometer2 me-2"></i> My Dashboard
                                </a>
                                <a href="upload_receipt.php" class="btn btn-outline-light btn-lg">
                                    <i class="bi bi-upload me-2"></i> Upload Receipt
                                </a>
                            <?php else: ?>
                                <a href="register.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-person-plus me-2"></i> Get Started Free
                                </a>
                                <a href="#how-it-works" class="btn btn-outline-light btn-lg">
                                    <i class="bi bi-play-circle me-2"></i> See How It Works
                                </a>
                                <a href="anonymous.php" class="btn btn-outline-secondary btn-lg">
                                    <i class="bi bi-lightning me-2"></i> Try without Account
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Enhanced receipt mockup -->
                    <div class="receipt-mockup shadow-lg">
                        <div class="receipt-header bg-white p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h4 class="mb-0 text-dark">Example receipt</h4>
                                <span class="badge bg-success">Split Ready</span>
                            </div>
                            <p class="text-muted small mb-0">Date: <?php echo date('d/m/Y'); ?></p>
                        </div>
                        
                        <div class="receipt-body bg-white p-3">
                            <table class="table table-sm mb-3">
                                <tbody>
                                    <tr>
                                        <td>Pasta Carbonara</td>
                                        <td class="text-end">$16.99</td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill">John</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Caesar Salad</td>
                                        <td class="text-end">$9.50</td>
                                        <td class="text-center">
                                            <span class="badge bg-info rounded-pill">Sarah</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Sparkling Water (2x)</td>
                                        <td class="text-end">$4.00</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill">Shared</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Chocolate Cake</td>
                                        <td class="text-end">$7.99</td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary rounded-pill">Shared</span>
                                        </td>
                                    </tr>
                                    <tr class="border-top">
                                        <td><strong>Total:</strong></td>
                                        <td class="text-end"><strong>$38.48</strong></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div class="split-results">
                                <div class="alert alert-success mb-2">
                                    <strong>John owes:</strong> $22.99
                                </div>
                                <div class="alert alert-info mb-0">
                                    <strong>Sarah owes:</strong> $15.49
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced How It Works section -->
    <section class="how-it-works py-5 bg-light" id="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold text-muted">How It Works</h2>
                <p class="lead text-muted">Three simple steps to split any bill</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center">
                        <div class="card-body p-4">
                            <div class="step-icon mb-3">
                                <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-camera text-white fs-4"></i>
                                </div>
                            </div>
                            <h4 class="card-title">1. Upload Receipt</h4>
                            <p class="card-text text-muted">Take a picture of your receipt or manually enter the bill details</p>
                            <?php if (!$isLoggedIn): ?>
                                <a href="register.php" class="btn btn-outline-primary btn-sm">Get Started</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center">
                        <div class="card-body p-4">
                            <div class="step-icon mb-3">
                                <div class="bg-success rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-people text-white fs-4"></i>
                                </div>
                            </div>
                            <h4 class="card-title">2. Assign Items</h4>
                            <p class="card-text text-muted">Mark who had what item and handle shared expenses automatically</p>
                            <?php if ($isLoggedIn): ?>
                                <a href="upload_receipt.php" class="btn btn-outline-success btn-sm">Try Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm text-center">
                        <div class="card-body p-4">
                            <div class="step-icon mb-3">
                                <div class="bg-info rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                    <i class="bi bi-share text-white fs-4"></i>
                                </div>
                            </div>
                            <h4 class="card-title">3. Share Results</h4>
                            <p class="card-text text-muted">Copy a read-only link so the group can see the bill and each person's share</p>
                            <?php if ($isLoggedIn): ?>
                                <a href="dashboard.php" class="btn btn-outline-info btn-sm">View Dashboard</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Enhanced CTA section -->
    <section class="cta-section bg-primary text-white py-5">
        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-3">Ready to start splitting?</h2>
                    <p class="lead mb-4">Join over <?= number_format($stats['users']) ?> users who have split <?= number_format($stats['bills']) ?> bills successfully!</p>
                    
                    <?php if (!$isLoggedIn): ?>
                        <div class="d-flex flex-wrap justify-content-center" style="gap: 1rem;">
                            <a href="login.php" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                            </a>
                            <a href="anonymous.php" class="btn btn-light btn-lg">
                                <i class="bi bi-lightning me-2"></i>Try without Account
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap justify-content-center" style="gap: 1rem;">
                            <a href="upload_receipt.php" class="btn btn-light btn-lg">
                                <i class="bi bi-upload me-2"></i>Upload Your First Receipt
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>



<!-- Add smooth scrolling JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
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
    
    // Add loading states to buttons
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            if (this.href && !this.href.includes('#')) {
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Loading...';
                this.disabled = true;
                
                // Re-enable after a short delay if navigation doesn't occur
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            }
        });
    });
});
</script>

<style>
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<?php include 'includes/footer.php'; ?>
