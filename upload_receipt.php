<?php
require_once 'config/config.php';
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

$errors = [];
$uploadSuccess = false;
$uploadedImage = null;
$billId = null;

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt_image'])) {
    if ($_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        $fileType = $_FILES['receipt_image']['type'];
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Invalid file type. Only JPG and PNG are allowed.";
        } else {
            // Create uploads directory if it doesn't exist
            if (!file_exists(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
            // Generate unique filename
            $fileName = uniqid('receipt_') . '_' . time() . '.' . pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION);
            $uploadPath = UPLOAD_DIR . $fileName;
            
            if (move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadPath)) {
                // Create bill entry with just the image
                $conn = getDbConnection();
                $userId = $_SESSION['user_id'];
                
                // Insert bill with placeholder data
                $stmt = $conn->prepare("
                    INSERT INTO bills (user_id, restaurant_name, total_amount, image_path, bill_date) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $restaurantName = "New Receipt"; // Placeholder
                $totalAmount = 0; // Will be calculated when items are added
                $stmt->bind_param("isds", $userId, $restaurantName, $totalAmount, $fileName);
                
                if ($stmt->execute()) {
                    $billId = $conn->insert_id;
                    $uploadSuccess = true;
                    $uploadedImage = $fileName;
                } else {
                    $errors[] = "Failed to save receipt: " . $conn->error;
                }
                
                $stmt->close();
                closeDbConnection($conn);
            } else {
                $errors[] = "Failed to upload file";
            }
        }
    } else {
        $errors[] = "File upload error: " . $_FILES['receipt_image']['error'];
    }
}

// Handle manual item entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_items']) && isset($_POST['bill_id'])) {
    $billId = (int)$_POST['bill_id'];
    $restaurantName = sanitizeInput($_POST['restaurant_name']);
    $items = $_POST['items'] ?? [];
    
    if (empty($restaurantName)) {
        $errors[] = "Please enter a restaurant name.";
    }
    
    if (empty($items)) {
        $errors[] = "Please add at least one item.";
    }
    
    if (empty($errors)) {
        $conn = getDbConnection();
        
        // Update restaurant name and calculate total
        $totalAmount = 0;
        foreach ($items as $item) {
            if (!empty($item['name']) && !empty($item['price']) && $item['price'] > 0) {
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $price = (float)$item['price'];
                $totalAmount += $price * $quantity;
            }
        }
        
        // Update bill
        $stmt = $conn->prepare("UPDATE bills SET restaurant_name = ?, total_amount = ? WHERE bill_id = ? AND user_id = ?");
        $stmt->bind_param("sdii", $restaurantName, $totalAmount, $billId, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        
        // Clear existing items and insert new ones
        $stmt = $conn->prepare("DELETE FROM bill_items WHERE bill_id = ?");
        $stmt->bind_param("i", $billId);
        $stmt->execute();
        $stmt->close();
        
        // Insert new items
        $itemStmt = $conn->prepare("INSERT INTO bill_items (bill_id, item_name, price) VALUES (?, ?, ?)");
        
        foreach ($items as $item) {
            if (!empty($item['name']) && !empty($item['price']) && $item['price'] > 0) {
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $price = (float)$item['price'];
                $itemName = sanitizeInput($item['name']);
                
                // Insert item for each quantity (to maintain current DB structure)
                for ($i = 0; $i < $quantity; $i++) {
                    $itemStmt->bind_param("isd", $billId, $itemName, $price);
                    $itemStmt->execute();
                }
            }
        }
        
        $itemStmt->close();
        closeDbConnection($conn);
        
        // Redirect to split bill page
        $_SESSION['upload_success'] = true;
        header("Location: split_bill.php?id=" . $billId);
        exit;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            
            <?php if (!$uploadSuccess): ?>
            <!-- Step 1: Upload Image -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2><i class="bi bi-cloud-upload"></i> Upload Receipt</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="receipt_image" class="form-label">Receipt Image</label>
                            <input type="file" class="form-control" id="receipt_image" name="receipt_image" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                            <div class="form-text">Upload a clear photo of your receipt. You'll manually enter the items afterwards.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload"></i> Upload Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Step 2: Manual Item Entry -->
            <div class="row">
                <div class="col-md-6">
                    <!-- Display uploaded image with sticky behavior -->
                    <div class="card mb-4 receipt-image-container" id="receipt-image-card">
                        <div class="card-header bg-primary text-white">
                            <h3><i class="bi bi-image"></i> Receipt Image</h3>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            
                            $imagePath = UPLOAD_DIR . $uploadedImage;
                            $webPath = 'uploads/' . $uploadedImage;
                            
                            echo "<!-- Debug Info:\n";
                            echo "UPLOAD_DIR: " . UPLOAD_DIR . "\n";
                            echo "uploadedImage: " . $uploadedImage . "\n";
                            echo "imagePath: " . $imagePath . "\n";
                            echo "webPath: " . $webPath . "\n";
                            echo "File exists: " . (file_exists($imagePath) ? 'YES' : 'NO') . "\n";
                            echo "-->";
                            
                            if (file_exists($imagePath)): 
                            ?>
                                <img src="<?php echo htmlspecialchars($webPath); ?>" 
                                     alt="Uploaded Receipt" 
                                     class="img-fluid receipt-image" 
                                     id="receipt-image"
                                     style="max-height: 600px; border: 1px solid #ddd; border-radius: 5px;"
                                     onerror="this.style.display='none'; document.getElementById('image-error').style.display='block';">
                                
                                <div id="image-error" style="display: none;" class="alert alert-warning mt-2">
                                    <i class="bi bi-exclamation-triangle"></i> Image could not be loaded
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Image file not found
                                </div>
                                <p class="text-muted">Expected path: <?php echo htmlspecialchars($imagePath); ?></p>
                            <?php endif; ?>
                            <p class="text-muted mt-2">Use this image as reference to enter the items manually</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Manual item entry form -->
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h3><i class="bi bi-pencil-square"></i> Enter Items Manually</h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="itemsForm">
                                <input type="hidden" name="save_items" value="1">
                                <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                                
                                <div class="mb-3">
                                    <label for="restaurant_name" class="form-label">Restaurant/Store Name</label>
                                    <input type="text" class="form-control" id="restaurant_name" name="restaurant_name" 
                                           placeholder="Enter restaurant or store name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Items</label>
                                    <div id="items-container">
                                        <!-- Initial item row -->
                                        <div class="row mb-2 item-row">
                                            <div class="col-4">
                                                <input type="text" class="form-control" name="items[0][name]" 
                                                       placeholder="Item name" required>
                                            </div>
                                            <div class="col-2">
                                                <input type="number" class="form-control" name="items[0][quantity]" 
                                                       placeholder="Qty" min="1" value="1">
                                            </div>
                                            <div class="col-3">
                                                <input type="number" class="form-control price-input" name="items[0][price]" 
                                                       placeholder="Price" step="0.01" min="0.01" required>
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item w-100">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-item">
                                        <i class="bi bi-plus"></i> Add Item
                                    </button>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="alert alert-info">
                                        <strong>Total: <span id="total-amount">$0.00</span></strong>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-check-circle"></i> Save Items & Continue
                                    </button>
                                    <a href="upload_receipt.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Upload Different Receipt
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let itemCount = 1;
    
    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.price-input').forEach(function(input) {
            const price = parseFloat(input.value) || 0;
            const quantityInput = input.closest('.item-row').querySelector('input[name*="[quantity]"]');
            const quantity = parseInt(quantityInput.value) || 1;
            total += price * quantity;
        });
        document.getElementById('total-amount').textContent = '$' + total.toFixed(2);
    }
    
    function updateRemoveButtons() {
        const removeButtons = document.querySelectorAll('.remove-item');
        removeButtons.forEach(function(btn, index) {
            btn.disabled = removeButtons.length <= 1;
        });
    }
    
    // Add item button
    document.getElementById('add-item').addEventListener('click', function() {
        const container = document.getElementById('items-container');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2 item-row';
        newRow.innerHTML = `
            <div class="col-4">
                <input type="text" class="form-control" name="items[${itemCount}][name]" 
                       placeholder="Item name" required>
            </div>
            <div class="col-2">
                <input type="number" class="form-control" name="items[${itemCount}][quantity]" 
                       placeholder="Qty" min="1" value="1">
            </div>
            <div class="col-3">
                <input type="number" class="form-control price-input" name="items[${itemCount}][price]" 
                       placeholder="Price" step="0.01" min="0.01" required>
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-outline-danger btn-sm remove-item w-100">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
        itemCount++;
        updateRemoveButtons();
        
        // Add event listeners to new inputs
        const newPriceInput = newRow.querySelector('.price-input');
        const newQuantityInput = newRow.querySelector('input[name*="[quantity]"]');
        
        newPriceInput.addEventListener('input', updateTotal);
        newQuantityInput.addEventListener('input', updateTotal);
        
        // Add remove functionality
        newRow.querySelector('.remove-item').addEventListener('click', function() {
            newRow.remove();
            updateTotal();
            updateRemoveButtons();
        });
    });
    
    // Remove item functionality for initial row
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            e.target.closest('.item-row').remove();
            updateTotal();
            updateRemoveButtons();
        }
    });
    
    // Update total when price or quantity changes
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('price-input') || e.target.name.includes('[quantity]')) {
            updateTotal();
        }
    });
    
    // Initialize
    updateRemoveButtons();
    updateTotal();
});
</script>

<style>
/* Simple and stable solution for sticky receipt */
@media (min-width: 768px) {
    .receipt-image-container {
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 40px);
        overflow-y: auto;
    }
    
    .receipt-image-container .card-body {
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }
    
    .receipt-image {
        max-height: calc(100vh - 160px);
        width: auto;
        object-fit: contain;
    }
}

/* On small screens, normal behavior */
@media (max-width: 767px) {
    .receipt-image-container {
        position: static;
        max-height: none;
    }
    
    .receipt-image {
        max-height: 400px;
    }
}

/* Improve spacing in the form */
.item-row {
    align-items: center;
}

.item-row .btn {
    height: 38px; /* Same height as inputs */
}

/* Improve text contrast */
.card-header {
    border-bottom: 1px solid rgba(0,0,0,0.125);
}

.card-header h2, 
.card-header h3 {
    margin-bottom: 0;
}

.bg-primary {
    background-color: #0d6efd !important;
}

.text-white {
    color: #fff !important;
}

.form-label {
    font-weight: 500;
    color: #212529;
}
</style>

<?php include 'includes/footer.php'; ?>