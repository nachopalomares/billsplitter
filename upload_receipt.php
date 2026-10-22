<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
requireLogin();

$errors = [];
$uploadSuccess = false;
$uploadedImage = null;
$billId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt_image'])) {
    $file = $_FILES['receipt_image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The upload failed. Choose a JPG or PNG file up to 5 MB.';
    } elseif ($file['size'] > MAX_RECEIPT_SIZE) {
        $errors[] = 'The receipt must be no larger than 5 MB.';
    } else {
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$mime]) || !getimagesize($file['tmp_name'])) {
            $errors[] = 'Invalid image. Only JPG and PNG are allowed.';
        } else {
            $uploadPath = null;
            try {
                if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0700, true) && !is_dir(UPLOAD_DIR)) {
                    throw new RuntimeException('Could not create the receipt directory.');
                }
                $fileName = bin2hex(random_bytes(16)) . '.' . $types[$mime];
                $uploadPath = UPLOAD_DIR . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    throw new RuntimeException('Could not move the uploaded image.');
                }
                $conn = getDbConnection();
                $stmt = $conn->prepare("INSERT INTO bills (user_id, restaurant_name, total_amount, image_path, bill_date) VALUES (?, 'New Receipt', 0, ?, NOW())");
                $stmt->bind_param('is', $_SESSION['user_id'], $fileName);
                $stmt->execute();
                $billId = $conn->insert_id;
                $stmt->close();
                closeDbConnection($conn);
                $uploadSuccess = true;
                $uploadedImage = $fileName;
            } catch (Throwable $error) {
                if ($uploadPath && is_file($uploadPath)) {
                    unlink($uploadPath);
                }
                error_log('Receipt upload failed: ' . $error->getMessage());
                $errors[] = 'Could not save the receipt. Please try again.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_items'], $_POST['bill_id'])) {
    $billId = (int)$_POST['bill_id'];
    $restaurantName = trim(postString('restaurant_name'));
    $items = $_POST['items'] ?? [];
    $conn = getDbConnection();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT * FROM bills WHERE bill_id = ? AND user_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $billId, $_SESSION['user_id']);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bill) {
            throw new InvalidArgumentException('Bill not found or access denied.');
        }
        $uploadedImage = $bill['image_path'];
        $uploadSuccess = true;
        if ($restaurantName === '' || strlen($restaurantName) > 100) {
            throw new InvalidArgumentException('The restaurant name is missing or too long.');
        }
        if (!is_array($items) || count($items) < 1 || count($items) > 100) {
            throw new InvalidArgumentException('Enter between 1 and 100 item rows.');
        }
        $validated = [];
        $totalCents = 0;
        $unitCount = 0;
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['name'] ?? null)) {
                throw new InvalidArgumentException('Each item needs a name, quantity and price.');
            }
            $name = trim($item['name']);
            $quantity = filter_var($item['quantity'] ?? 1, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 100]]);
            $cents = moneyToCents($item['price'] ?? '');
            if ($name === '' || strlen($name) > 100 || $quantity === false || $cents === 0) {
                throw new InvalidArgumentException('Check the item name, positive price and quantity (1 to 100).');
            }
            $unitCount += $quantity;
            $totalCents += $cents * $quantity;
            if ($unitCount > 1000 || $totalCents > 9999999999) {
                throw new InvalidArgumentException('The bill exceeds the item or amount limit.');
            }
            $validated[] = ['name' => $name, 'quantity' => $quantity, 'price' => $cents / 100];
        }
        $totalAmount = $totalCents / 100;
        $stmt = $conn->prepare('UPDATE bills SET restaurant_name = ?, total_amount = ? WHERE bill_id = ? AND user_id = ?');
        $stmt->bind_param('sdii', $restaurantName, $totalAmount, $billId, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('DELETE FROM bill_items WHERE bill_id = ?');
        $stmt->bind_param('i', $billId);
        $stmt->execute();
        $stmt->close();
        $stmt = $conn->prepare('INSERT INTO bill_items (bill_id, item_name, price) VALUES (?, ?, ?)');
        foreach ($validated as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $stmt->bind_param('isd', $billId, $item['name'], $item['price']);
                $stmt->execute();
            }
        }
        $stmt->close();
        $conn->commit();
        closeDbConnection($conn);
        header('Location: split_bill.php?id=' . $billId);
        exit;
    } catch (InvalidArgumentException $error) {
        $conn->rollback();
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Saving bill items failed: ' . $error->getMessage());
        $errors[] = 'Could not save the items. Existing bill data has been kept.';
    }
    closeDbConnection($conn);
}
$formItems = [['name' => '', 'quantity' => '1', 'price' => '']];
if ($uploadSuccess && is_array($_POST['items'] ?? null)) {
    $formItems = array_values(array_filter(array_slice($_POST['items'], 0, 100), 'is_array')) ?: $formItems;
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
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label for="receipt_image" class="form-label">Receipt Image</label>
                            <input type="file" class="form-control" id="receipt_image" name="receipt_image" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                            <div class="form-text">JPG or PNG, up to 5 MB. You'll enter the items manually afterwards.</div>
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
                            $webPath = BASE_URL . '/receipt.php?id=' . (int)$billId;
                            
                            
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
                                <?= csrfField() ?>
                                <?php foreach ($errors as $error): ?>
                                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                                <input type="hidden" name="save_items" value="1">
                                <input type="hidden" name="bill_id" value="<?php echo $billId; ?>">
                                
                                <div class="mb-3">
                                    <label for="restaurant_name" class="form-label">Restaurant/Store Name</label>
                                    <input type="text" class="form-control" id="restaurant_name" name="restaurant_name" 
                                           placeholder="Enter restaurant or store name" value="<?= htmlspecialchars(postString('restaurant_name')) ?>" maxlength="100" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Items</label>
                                    <div id="items-container">
                                        <?php foreach ($formItems as $index => $formItem): ?>
                                        <div class="row mb-2 item-row">
                                            <div class="col-4">
                                                <input type="text" class="form-control" name="items[<?= $index ?>][name]"
                                                       value="<?= htmlspecialchars(is_string($formItem['name'] ?? null) ? $formItem['name'] : '') ?>" placeholder="Item name" maxlength="100" required>
                                            </div>
                                            <div class="col-2">
                                                <input type="number" class="form-control" name="items[<?= $index ?>][quantity]"
                                                       placeholder="Qty" min="1" max="100" value="<?= htmlspecialchars(is_scalar($formItem['quantity'] ?? null) ? (string)$formItem['quantity'] : '1') ?>">
                                            </div>
                                            <div class="col-3">
                                                <input type="number" class="form-control price-input" name="items[<?= $index ?>][price]"
                                                       value="<?= htmlspecialchars(is_scalar($formItem['price'] ?? null) ? (string)$formItem['price'] : '') ?>" placeholder="Price" step="0.01" min="0.01" required>
                                            </div>
                                            <div class="col-2">
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-item w-100">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
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
    if (!document.getElementById('itemsForm')) return;
    let itemCount = <?= count($formItems) ?>;
    
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
