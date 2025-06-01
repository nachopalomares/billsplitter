<?php
// Start output buffering to handle header issues
ob_start();

require_once 'config/config.php';
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

// Initialize variables
$userId = $_SESSION['user_id'];
$success = false;
$errors = [];
$passwordChanged = false;
$user = [];

// Get database connection
$conn = getDbConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'update_profile') {
        // Update profile info
        $email = trim($_POST['email']);
        
        // Validate email
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email already exists for another user
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Email is already in use by another account";
            }
            $stmt->close();
        }
        
        // Update user information
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
            $stmt->bind_param("si", $email, $userId);
            
            if ($stmt->execute()) {
                $success = true;
            } else {
                $errors[] = "Failed to update profile: " . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'change_password') {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        
        // Validate password
        if (!password_verify($current_password, $user_data['password_hash'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // Update password
        if (empty($errors)) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $password_hash, $userId);
            
            if ($stmt->execute()) {
                $passwordChanged = true;
            } else {
                $errors[] = "Failed to update password: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Get user information
$stmt = $conn->prepare("SELECT username, email, created_at, last_login FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
}

$stmt->close();
closeDbConnection($conn);

// For normal page view, end the current buffer
ob_end_clean();
// Start a fresh buffer for the page content
ob_start();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-5">
    <div class="row">
        <div class="col-lg-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Menu</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#profile-info" class="list-group-item list-group-item-action active" data-bs-toggle="list">Profile Information</a>
                    <a href="#change-password" class="list-group-item list-group-item-action" data-bs-toggle="list">Change Password</a>
                    <a href="#account-activity" class="list-group-item list-group-item-action" data-bs-toggle="list">Account Activity</a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <h3>My Profile</h3>
                </div>
                <div class="card-body">
                    <!-- Success Messages -->
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong>Success!</strong> Your profile has been updated.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($passwordChanged): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <strong>Success!</strong> Your password has been updated.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Error Messages -->
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-content">
                        <!-- Profile Information Tab -->
                        <div class="tab-pane fade show active" id="profile-info">
                            <form method="POST" action="profile.php">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                                    <small class="text-muted">Username cannot be changed</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Account Created</label>
                                    <p class="form-control-plaintext"><?php echo isset($user['created_at']) ? date('F j, Y', strtotime($user['created_at'])) : 'N/A'; ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Last Login</label>
                                    <p class="form-control-plaintext"><?php echo isset($user['last_login']) ? date('F j, Y g:i a', strtotime($user['last_login'])) : 'N/A'; ?></p>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                        
                        <!-- Change Password Tab -->
                        <div class="tab-pane fade" id="change-password">
                            <form method="POST" action="profile.php" id="password-form">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required
                                           pattern=".{8,}" title="Password must be at least 8 characters">
                                    <small class="text-muted">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                        </div>
                        
                        <!-- Account Activity Tab -->
                        <div class="tab-pane fade" id="account-activity">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Bills Summary</h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $conn = getDbConnection();
                                    $stmt = $conn->prepare("
                                        SELECT 
                                            COUNT(*) as total_bills,
                                            SUM(total_amount) as total_spent,
                                            MAX(created_at) as last_bill_date
                                        FROM bills 
                                        WHERE user_id = ?
                                    ");
                                    $stmt->bind_param("i", $userId);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    $stats = $result->fetch_assoc();
                                    $stmt->close();
                                    closeDbConnection($conn);
                                    ?>
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h2><?php echo (int)($stats['total_bills'] ?? 0); ?></h2>
                                                <p class="text-muted">Total Bills</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h2><?php echo formatCurrency($stats['total_spent'] ?? 0); ?></h2>
                                                <p class="text-muted">Total Amount</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="text-center">
                                                <h2><?php echo isset($stats['last_bill_date']) && $stats['last_bill_date'] ? date('M j', strtotime($stats['last_bill_date'])) : 'N/A'; ?></h2>
                                                <p class="text-muted">Last Bill</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 text-center">
                                        <a href="dashboard.php" class="btn btn-outline-primary">View All Bills</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a hash in the URL
    const hash = window.location.hash;
    if (hash) {
        // Find the tab with this hash and activate it
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            const tabTrigger = new bootstrap.Tab(tab);
            tabTrigger.show();
        }
    }
    
    // Password validation
    const passwordForm = document.getElementById('password-form');
    if (passwordForm) {
        passwordForm.addEventListener('submit', function(event) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            
            if (newPassword.length < 8) {
                event.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>