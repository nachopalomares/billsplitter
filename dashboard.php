<?php
// Start output buffering to handle header issues
ob_start();

require_once 'config/config.php';
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_bill') {
    ob_end_clean();
    header('Content-Type: application/json');
    $billId = filter_var($_POST['bill_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($billId === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid bill.']);
        exit;
    }
    $conn = getDbConnection();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('SELECT image_path FROM bills WHERE bill_id = ? AND user_id = ? FOR UPDATE');
        $stmt->bind_param('ii', $billId, $_SESSION['user_id']);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bill) {
            throw new InvalidArgumentException('Bill not found or access denied.');
        }
        $stmt = $conn->prepare('DELETE FROM bills WHERE bill_id = ? AND user_id = ?');
        $stmt->bind_param('ii', $billId, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $image = basename($bill['image_path'] ?? '');
        if ($image !== '' && is_file(UPLOAD_DIR . $image) && !unlink(UPLOAD_DIR . $image)) {
            error_log('Could not remove the deleted bill receipt.');
        }
        echo json_encode(['success' => true]);
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('Bill deletion failed: ' . $error->getMessage());
        echo json_encode(['success' => false, 'error' => 'Could not delete this bill.']);
    }
    closeDbConnection($conn);
    exit;
}

// For normal page view, we need to discard any output buffering
// so it doesn't interfere with the HTML page
ob_end_clean();

// Start a fresh buffer for the page content
ob_start();

// Get user's bills
$conn = getDbConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT b.*, COUNT(DISTINCT bi.item_id) as item_count, COUNT(DISTINCT p.participant_id) as participant_count
    FROM bills b
    LEFT JOIN bill_items bi ON b.bill_id = bi.bill_id
    LEFT JOIN participants p ON b.bill_id = p.bill_id
    WHERE b.user_id = ?
    GROUP BY b.bill_id
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$bills = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
closeDbConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Bills</h1>
        <a href="upload_receipt.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> New Bill
        </a>
    </div>
    
    <?php if (empty($bills)): ?>
        <div class="alert alert-info">
            <p>You haven't created any bills yet. <a href="upload_receipt.php">Upload a receipt</a> to get started!</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($bills as $bill): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <?php if (!empty($bill['image_path'])): ?>
                        <div class="card-img-top-container" style="height: 200px; overflow: hidden;">
                            <img src="<?php echo htmlspecialchars(receiptUrl($bill)); ?>"
                                 alt="Receipt" 
                                 class="card-img-top" 
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($bill['restaurant_name'] ?? 'Unnamed Restaurant'); ?>
                            </h5>
                            <h6 class="card-subtitle mb-2 text-muted">
                                <?php echo date('M d, Y', strtotime($bill['bill_date'] ?? $bill['created_at'])); ?>
                            </h6>
                            <p class="card-text">
                                <strong>Total:</strong> <?php echo formatCurrency($bill['total_amount']); ?><br>
                                <strong>Items:</strong> <?php echo $bill['item_count']; ?><br>
                                <strong>People:</strong> <?php echo $bill['participant_count']; ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="view_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                    <a href="split_bill.php?id=<?php echo $bill['bill_id']; ?>" class="btn btn-sm btn-outline-primary">Split Bill</a>
                                </div>
                                <button type="button" 
                                        class="btn btn-sm btn-outline-danger delete-bill-btn" 
                                        data-bill-id="<?php echo $bill['bill_id']; ?>"
                                        data-bill-name="<?php echo htmlspecialchars($bill['restaurant_name'] ?? 'this bill'); ?>"
                                        title="Delete Bill">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteBillModal" tabindex="-1" aria-labelledby="deleteBillModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-info" id="deleteBillModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-info">
                <p>Are you sure you want to delete <strong id="billNameToDelete"></strong>?</p>
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action cannot be undone. All associated data including items, participants, and assignments will be permanently deleted.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete Bill</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let billIdToDelete = null;
    
    // Handle delete button clicks
    document.querySelectorAll('.delete-bill-btn').forEach(button => {
        button.addEventListener('click', function() {
            billIdToDelete = this.dataset.billId;
            const billName = this.dataset.billName;
            
            document.getElementById('billNameToDelete').textContent = billName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteBillModal'));
            modal.show();
        });
    });
    
    // Handle confirm delete
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (!billIdToDelete) return;
        
        const confirmBtn = this;
        const originalText = confirmBtn.textContent;
        
        // Show loading state
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
        
        const formData = new FormData();
        formData.append('action', 'delete_bill');
        formData.append('bill_id', billIdToDelete);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        fetch('dashboard.php', {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(response => {
            // Check if the response is valid
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get the response text for debugging
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text); // For debugging
            
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    // Hide modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteBillModal'));
                    modal.hide();
                    
                    // Remove the card from the page
                    const cardToRemove = document.querySelector(`[data-bill-id="${billIdToDelete}"]`).closest('.col-md-6');
                    if (cardToRemove) {
                        cardToRemove.remove();
                    }
                    
                    // Check if there are no bills left
                    const remainingBills = document.querySelectorAll('.delete-bill-btn').length;
                    if (remainingBills === 0) {
                        // Show the "no bills" message
                        const container = document.querySelector('.row');
                        if (container) {
                            container.innerHTML = `
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <p>You haven't created any bills yet. <a href="upload_receipt.php">Upload a receipt</a> to get started!</p>
                                    </div>
                                </div>
                            `;
                        }
                    }
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        Bill deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    `;
                    const container = document.querySelector('.container');
                    const firstChild = container.querySelector('.d-flex');
                    if (firstChild) {
                        container.insertBefore(alertDiv, firstChild);
                    }
                    
                    // Auto-dismiss after 3 seconds
                    setTimeout(() => {
                        if (alertDiv.parentNode) {
                            alertDiv.remove();
                        }
                    }, 3000);
                    
                } else {
                    console.error('Server returned error:', data.error);
                    alert('Failed to delete bill: ' + (data.error || 'Unknown error'));
                }
            } catch (jsonError) {
                console.error('JSON parse error:', jsonError);
                console.error('Response was:', text);
                alert('Invalid response from server. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Network error occurred while deleting the bill');
        })
        .finally(() => {
            // Reset button state
            confirmBtn.disabled = false;
            confirmBtn.textContent = originalText;
            billIdToDelete = null;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
