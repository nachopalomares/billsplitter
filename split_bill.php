<?php
require_once 'config/config.php';
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

// Check if bill ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$billId = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

// Get database connection
$conn = getDbConnection();

// Verify that the bill belongs to the user
$stmt = $conn->prepare("SELECT * FROM bills WHERE bill_id = ? AND user_id = ?");
$stmt->bind_param("ii", $billId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    closeDbConnection($conn);
    header("Location: dashboard.php");
    exit;
}

$bill = $result->fetch_assoc();
$stmt->close();

// Get bill items
$stmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY item_id");
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Create an item lookup map for efficient access
$itemsMap = [];
foreach ($items as $item) {
    $itemsMap[$item['item_id']] = $item;
}

// Get participants
$stmt = $conn->prepare("SELECT * FROM participants WHERE bill_id = ? ORDER BY participant_id");
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();
$participants = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get item assignments
$stmt = $conn->prepare("
    SELECT ia.*, p.name as participant_name, p.participant_id
    FROM item_assignments ia 
    JOIN participants p ON ia.participant_id = p.participant_id
    JOIN bill_items bi ON ia.item_id = bi.item_id AND bi.bill_id = p.bill_id
    WHERE p.bill_id = ?
");
$stmt->bind_param("i", $billId);
$stmt->execute();
$result = $stmt->get_result();
$assignments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Process assignment data for easy access
$itemAssignments = [];
foreach ($assignments as $assignment) {
    if (!isset($itemAssignments[$assignment['item_id']])) {
        $itemAssignments[$assignment['item_id']] = [];
    }
    $itemAssignments[$assignment['item_id']][] = [
        'participant_id' => $assignment['participant_id'],
        'participant_name' => $assignment['participant_name'],
        'share_percentage' => $assignment['share_percentage']
    ];
}


$participantAmounts = calculateParticipantAmounts($itemsMap, $participants, $assignments);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false];
    
    switch ($_POST['action']) {
        case 'add_participant':
            $name = trim(postString('name'));
            $email = trim(postString('email'));
            if ($name !== '' && strlen($name) <= 50 && strlen($email) <= 100
                && ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL))) {
                
                $stmt = $conn->prepare("INSERT INTO participants (bill_id, name, email) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $billId, $name, $email);
                
                if ($stmt->execute()) {
                    $response = [
                        'success' => true,
                        'participant' => [
                            'participant_id' => $conn->insert_id,
                            'name' => $name,
                            'email' => $email
                        ]
                    ];
                }
                $stmt->close();
            }
            break;
            
        case 'remove_participant':
            if (isset($_POST['participant_id']) && is_numeric($_POST['participant_id'])) {
                $participantId = (int)$_POST['participant_id'];
                
                $stmt = $conn->prepare("DELETE FROM participants WHERE participant_id = ? AND bill_id = ?");
                $stmt->bind_param("ii", $participantId, $billId);
                
                if ($stmt->execute()) {
                    $response = ['success' => true];
                }
                $stmt->close();
            }
            break;
            
        case 'assign_item':
            if (isset($_POST['item_id']) && is_numeric($_POST['item_id']) && 
                is_array($_POST['participant_ids'] ?? [])) {
                
                $itemId = (int)$_POST['item_id'];
                $participantIds = [];
                foreach ($_POST['participant_ids'] ?? [] as $id) {
                    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false) {
                        $response['error'] = 'Invalid participant.';
                        break 2;
                    }
                    $participantIds[] = $id;
                }
                $participantIds = array_values(array_unique($participantIds));
                if (!isset($itemsMap[$itemId]) || array_diff($participantIds, array_column($participants, 'participant_id'))) {
                    $response['error'] = 'Items and participants must belong to this bill.';
                    break;
                }
                
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Remove existing assignments
                    $stmt = $conn->prepare("
                        DELETE ia FROM item_assignments ia
                        JOIN participants p ON ia.participant_id = p.participant_id
                        WHERE ia.item_id = ? AND p.bill_id = ?
                    ");
                    $stmt->bind_param("ii", $itemId, $billId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Add new assignments
                    if (!empty($participantIds)) {
                        $sharePercentage = 100 / count($participantIds);
                        $stmt = $conn->prepare("INSERT INTO item_assignments (item_id, participant_id, share_percentage) VALUES (?, ?, ?)");
                        
                        foreach ($participantIds as $participantId) {
                            $stmt->bind_param("iid", $itemId, $participantId, $sharePercentage);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }
                    
                    $conn->commit();
                    $response = ['success' => true];
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log('Assignment failed: ' . $e->getMessage());
                    $response = ['success' => false, 'error' => 'Could not save the assignment.'];
                }
            }
            break;
            
        case 'split_evenly':
            if (!empty($participants)) {
                $conn->begin_transaction();
                
                try {
                    // Clear all existing assignments
                    $stmt = $conn->prepare("
                        DELETE ia FROM item_assignments ia
                        JOIN participants p ON ia.participant_id = p.participant_id
                        WHERE p.bill_id = ?
                    ");
                    $stmt->bind_param("i", $billId);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Create new assignments
                    $sharePercentage = 100 / count($participants);
                    $stmt = $conn->prepare("INSERT INTO item_assignments (item_id, participant_id, share_percentage) VALUES (?, ?, ?)");
                    
                    foreach ($items as $item) {
                        foreach ($participants as $participant) {
                            $stmt->bind_param("iid", $item['item_id'], $participant['participant_id'], $sharePercentage);
                            $stmt->execute();
                        }
                    }
                    
                    $stmt->close();
                    $conn->commit();
                    $response = ['success' => true];
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log('Equal split failed: ' . $e->getMessage());
                    $response = ['success' => false, 'error' => 'Could not split the bill.'];
                }
            }
            break;
    }
    
    closeDbConnection($conn);
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

closeDbConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<!-- Optimized styles - removed unused classes -->
<style>
    .amount-summary-card {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border: 2px solid #28a745;
    }
    
    .floating-total {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1030;
        min-width: 200px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    @media (min-width: 992px) {
        .floating-total {
            display: none;
        }
    }
    
    .sticky-sidebar {
        position: -webkit-sticky;
        position: sticky;
        top: 20px;
        z-index: 1020;
    }
</style>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item active text-primary">Bill Details</li>
        </ol>
    </nav>

    <!-- Información de la factura -->
    <div class="row mb-4 ">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0"><?php echo htmlspecialchars($bill['restaurant_name'] ?? 'Restaurant'); ?></h2>
                        <div class="badge bg-light text-primary fs-5">
                            <?php echo formatCurrency($bill['total_amount']); ?>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong><i class="bi bi-calendar3"></i> Date:</strong> 
                               <?php echo date('M d, Y', strtotime($bill['bill_date'] ?? $bill['created_at'])); ?>
                            </p>
                            <?php if (!empty($bill['notes'])): ?>
                            <p><strong><i class="bi bi-sticky"></i> Notes:</strong> 
                               <?php echo htmlspecialchars($bill['notes']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-0"><strong>Bill #:</strong> <?php echo $billId; ?></p>
                            <p><strong>Items:</strong> <?php echo count($items); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h4 class="card-title"><i class="bi bi-scissors"></i> Bill Splitting</h4>
                    <p class="card-text">Assign items to participants to calculate how much each person owes.</p>
                    <div class="d-grid">
                        <button id="splitEvenlyBtn" class="btn btn-success">
                            <i class="bi bi-people-fill"></i> Split All Items Evenly
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Imagen del ticket -->
            <?php if (!empty($bill['image_path'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Receipt Image</h4>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo htmlspecialchars(receiptUrl($bill)); ?>"
                         alt="Receipt" 
                         class="img-fluid" 
                         style="max-height: 500px; cursor: pointer;"
                         data-bs-toggle="modal" 
                         data-bs-target="#receiptModal">
                    <p class="text-muted mt-2">Click to enlarge</p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Items de la factura -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h3 class="mb-0 text-primary"><i class="bi bi-list-check"></i> Bill Items</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Price</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo formatCurrency($item['price']); ?></td>
                                    <td class="assigned-to" data-item-id="<?php echo $item['item_id']; ?>">
                                        <?php 
                                        if (isset($itemAssignments[$item['item_id']])) {
                                            $names = array_map(function($a) { 
                                                return htmlspecialchars($a['participant_name']); 
                                            }, $itemAssignments[$item['item_id']]);
                                            
                                            foreach ($names as &$name) {
                                                $name = '<span class="badge bg-primary text-white">' . $name . '</span>';
                                            }
                                            
                                            echo implode(' ', $names);
                                        } else {
                                            echo '<span class="badge bg-light text-muted">Unassigned</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary assign-item" 
                                                data-item-id="<?php echo $item['item_id']; ?>"
                                                data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                data-bs-toggle="modal" data-bs-target="#assignItemModal">
                                            <i class="bi bi-person-plus"></i> Assign
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Panel lateral con participantes y resumen de cantidades -->
        <div class="col-lg-4">
            <div class="sticky-sidebar">
                <!-- Sección de Participantes -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-people"></i> Participants (<span id="participantCount"><?php echo count($participants); ?></span>)
                            </h4>
                            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#addParticipantModal">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="participantsList">
                            <?php if (empty($participants)): ?>
                                <div class="text-center text-muted py-3" id="noParticipantsMsg">
                                    <i class="bi bi-person-plus fs-4 d-block mb-2"></i>
                                    No participants yet. 
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#addParticipantModal">Add the first one</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($participants as $participant): ?>
                                    <div class="participant-item mb-2 p-2 bg-light rounded" data-participant-id="<?php echo $participant['participant_id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($participant['name']); ?>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge bg-success">
                                                    <?php 
                                                    $amount = isset($participantAmounts[$participant['participant_id']]) ? 
                                                        $participantAmounts[$participant['participant_id']]['amount'] : 0;
                                                    echo formatCurrency($amount); 
                                                    ?>
                                                </span>
                                                <button class="btn btn-sm btn-outline-danger remove-participant" title="Remove <?php echo htmlspecialchars($participant['name']); ?>">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Resumen de cantidades -->
                <div class="card amount-summary-card border-success mb-5">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="bi bi-cash-coin"></i> Amount Due</h3>
                    </div>
                    <div class="card-body" id="amountDueContainer">
                        <?php if (empty($participants)): ?>
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-calculator fs-4 d-block mb-2"></i>
                                Add participants to see how much each person owes
                            </div>
                        <?php else: ?>
                            <?php 
                            $totalAssigned = array_sum(array_column($participantAmounts, 'amount_cents')) / 100;
                            foreach ($participantAmounts as $participantId => $data): 
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 <?php echo $data['amount'] > 0 ? 'bg-light rounded' : ''; ?>">
                                    <span>
                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($data['name']); ?>
                                    </span>
                                    <span class="badge bg-success fs-6"><?php echo formatCurrency($data['amount']); ?></span>
                                </div>
                            <?php endforeach; ?>
                            
                            <hr>
                            <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                <span class="fw-bold">Total Assigned:</span>
                                <span class="badge bg-primary fs-5"><?php echo formatCurrency($totalAssigned); ?></span>
                            </div>
                            
                            <?php if ($totalAssigned < $bill['total_amount']): ?>
                                <div class="alert alert-warning mt-3" role="alert">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <div>
                                            <strong>Remaining: <?php echo formatCurrency($bill['total_amount'] - $totalAssigned); ?></strong>
                                            <div class="small">Some items are not assigned yet.</div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success mt-3" role="alert">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle-fill me-2"></i>
                                        <div>All items have been assigned!</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Total flotante para móvil -->
<div class="card floating-total d-lg-none" id="floatingTotal">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center">
            <span class="fw-bold">Total Bill:</span>
            <span class="badge bg-primary"><?php echo formatCurrency($bill['total_amount']); ?></span>
        </div>
    </div>
</div>

<!-- Modal de imagen del recibo -->
<?php if (!empty($bill['image_path'])): ?>
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?php echo htmlspecialchars(receiptUrl($bill)); ?>"
                     alt="Receipt" 
                     class="img-fluid">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal para agregar participante -->
<div class="modal fade" id="addParticipantModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add Participant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addParticipantForm">
                    <div class="mb-3">
                        <label for="participantName" class="form-label fw-bold text-dark">Name</label>
                        <input type="text" class="form-control" id="participantName" required>
                    </div>
                    <div class="mb-3">
                        <label for="participantEmail" class="form-label fw-bold text-dark">Email (optional)</label>
                        <input type="email" class="form-control" id="participantEmail">
                        <div class="form-text text-dark">Optional contact detail. No emails are sent.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveParticipantBtn">Add</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para asignar item -->
<div class="modal fade" id="assignItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Assign Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold text-dark">Assign <strong id="assignItemName"></strong> to:</p>
                
                <div id="assignParticipantsList">
                    <?php if (empty($participants)): ?>
                        <div class="alert alert-info">
                            No participants added yet. Please add participants first.
                        </div>
                    <?php else: ?>
                        <?php foreach ($participants as $participant): ?>
                            <div class="form-check">
                                <input class="form-check-input participant-checkbox" type="checkbox" 
                                       id="participant<?php echo $participant['participant_id']; ?>" 
                                       value="<?php echo $participant['participant_id']; ?>">
                                <label class="form-check-label fw-medium text-dark" for="participant<?php echo $participant['participant_id']; ?>">
                                    <?php echo htmlspecialchars($participant['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveAssignmentBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const billId = <?php echo $billId; ?>;
    let currentItemId = null;
    
    // Function to make AJAX requests
    const makeRequest = async (action, data = {}) => {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
        
        Object.entries(data).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach(v => formData.append(`${key}[]`, v));
            } else {
                formData.append(key, value);
            }
        });
        
        try {
            const response = await fetch(`split_bill.php?id=${billId}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: formData
            });
            return await response.json();
        } catch (error) {
            console.error('Request failed:', error);
            return { success: false, error: error.message };
        }
    };
    
    // Add participant
    document.getElementById('addParticipantForm').addEventListener('submit', function(event) {
        event.preventDefault();
        document.getElementById('saveParticipantBtn').click();
    });
    document.getElementById('saveParticipantBtn').addEventListener('click', async function() {
        const name = document.getElementById('participantName').value.trim();
        const email = document.getElementById('participantEmail').value.trim();
        
        if (!name) {
            alert('Please enter a name');
            return;
        }
        
        const data = await makeRequest('add_participant', { name, email });
        
        if (data.success) {
            // Reload the page to refresh data
            location.reload();
        } else {
            alert(data.error || 'Check the name and email, then try again.');
        }
    });
    
    // Remove participant
    document.getElementById('participantsList').addEventListener('click', async function(e) {
        if (e.target.closest('.remove-participant')) {
            const participantChip = e.target.closest('.participant-item');
            const participantId = participantChip.dataset.participantId;
            
            if (confirm('Are you sure you want to remove this participant?')) {
                const data = await makeRequest('remove_participant', { participant_id: participantId });
                
                if (data.success) {
                    // Reload the page to refresh data
                    location.reload();
                } else {
                    alert('Failed to remove participant');
                }
            }
        }
    });
    
    // Update participant count
    const updateParticipantCount = () => {
        const count = document.querySelectorAll('.participant-item').length;
        document.getElementById('participantCount').textContent = count;
    };
    
    // Assign item
    document.querySelectorAll('.assign-item').forEach(button => {
        button.addEventListener('click', function() {
            currentItemId = this.dataset.itemId;
            document.getElementById('assignItemName').textContent = this.dataset.itemName;
            
            // Clear checkboxes
            document.querySelectorAll('.participant-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
        });
    });
    
    // Save assignment
    document.getElementById('saveAssignmentBtn').addEventListener('click', async function() {
        const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
        const participantIds = Array.from(checkboxes).map(cb => cb.value);
        
        const data = await makeRequest('assign_item', {
            item_id: currentItemId,
            participant_ids: participantIds
        });
        
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('assignItemModal')).hide();
            // Reload to update amounts
            location.reload();
        } else {
            alert(data.error || 'Failed to assign item');
        }
    });
    
    // Split evenly
    document.getElementById('splitEvenlyBtn').addEventListener('click', async function() {
        const participantCount = document.querySelectorAll('.participant-item').length;
        
        if (participantCount === 0) {
            alert('Please add participants first');
            return;
        }
        
        if (confirm('This will assign all items equally among all participants. Continue?')) {
            const data = await makeRequest('split_evenly');
            
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to split items');
            }
        }
    });
    
    // Auto-hide floating total when summary is visible (mobile)
    const floatingTotal = document.getElementById('floatingTotal');
    const summaryCard = document.querySelector('.amount-summary-card');
    
    if (floatingTotal && summaryCard) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                floatingTotal.style.display = entry.isIntersecting ? 'none' : 'block';
            });
        }, { threshold: 0.1 });
        
        observer.observe(summaryCard);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
