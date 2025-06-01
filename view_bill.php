<?php
require_once 'config/config.php';
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Get bill ID
$billId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$billId) {
    header('Location: dashboard.php');
    exit;
}

// Get database connection
$conn = getDbConnection();

// Get bill details
$billStmt = $conn->prepare("SELECT * FROM bills WHERE bill_id = ?");
$billStmt->bind_param("i", $billId);
$billStmt->execute();
$billResult = $billStmt->get_result();

if ($billResult->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}

$bill = $billResult->fetch_assoc();

// Get items
$itemsStmt = $conn->prepare("SELECT * FROM bill_items WHERE bill_id = ? ORDER BY item_name");
$itemsStmt->bind_param("i", $billId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get participants
$participantsStmt = $conn->prepare("SELECT * FROM participants WHERE bill_id = ? ORDER BY name");
$participantsStmt->bind_param("i", $billId);
$participantsStmt->execute();
$participants = $participantsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get assignments with participant names
$assignmentsStmt = $conn->prepare("
    SELECT ia.*, p.name as participant_name, bi.item_name, bi.price
    FROM item_assignments ia
    JOIN participants p ON ia.participant_id = p.participant_id
    JOIN bill_items bi ON ia.item_id = bi.item_id
    WHERE bi.bill_id = ?
    ORDER BY bi.item_name, p.name
");
$assignmentsStmt->bind_param("i", $billId);
$assignmentsStmt->execute();
$assignments = $assignmentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate amounts due
$participantAmounts = [];
foreach ($participants as $participant) {
    $participantAmounts[$participant['participant_id']] = [
        'name' => $participant['name'],
        'amount' => 0
    ];
}

foreach ($assignments as $assignment) {
    $amount = ($assignment['price'] * $assignment['share_percentage']) / 100;
    $participantAmounts[$assignment['participant_id']]['amount'] += $amount;
}

closeDbConnection($conn);
?>

<?php include 'includes/header.php'; ?>

<style>
    .share-card {
        border: 2px solid #007bff;
        border-radius: 15px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
    .receipt-image {
        max-height: 400px;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .amount-badge {
        font-size: 1.1em;
        padding: 0.5rem 1rem;
    }
    
    .summary-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
    }
    
    .bill-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        border-radius: 15px 15px 0 0;
        padding: 2rem;
    }
    
    .participant-row {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    
    .participant-row:hover {
        background-color: #f8f9fa;
    }
    
    .participant-row:last-child {
        border-bottom: none;
    }
    
    .total-row {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        font-weight: bold;
        border-radius: 0 0 15px 15px;
        padding: 1.5rem;
    }
    
    @media print {
        .no-print { display: none !important; }
        .container { max-width: none !important; }
    }
    
    .share-buttons {
        gap: 10px;
    }
    
    .qr-code {
        max-width: 150px;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
        background: white;
    }
</style>

<div class="container mt-4">
    <!-- Botones de acción superiores -->
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active text-muted">Bill Summary</li>
                    </ol>
                </nav>
                
                <div class="share-buttons d-flex">
                    <button class="btn btn-success" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button class="btn btn-primary" onclick="copyBillLink()">
                        <i class="bi bi-share"></i> Share Link
                    </button>
                    <a href="split_bill.php?id=<?php echo $billId; ?>" class="btn btn-warning">
                        <i class="bi bi-pencil"></i> Edit Split
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Columna principal con el resumen -->
        <div class="col-lg-8">
            <div class="summary-card mb-4">
                <!-- Encabezado de la factura -->
                <div class="bill-header text-center">
                    <h1 class="mb-2">
                        <i class="bi bi-receipt"></i>
                        <?php echo htmlspecialchars($bill['restaurant_name'] ?? 'Restaurant Bill'); ?>
                    </h1>
                    <p class="mb-2 fs-5">
                        <i class="bi bi-calendar3"></i>
                        <?php echo date('F j, Y', strtotime($bill['bill_date'] ?? $bill['created_at'])); ?>
                    </p>
                    <div class="badge bg-light text-primary fs-4">
                        Total: <?php echo formatCurrency($bill['total_amount']); ?>
                    </div>
                </div>

                <!-- Resumen de pagos por persona -->
                <div class="card-body p-0">
                    <div class="text-center py-3">
                        <h3 class="text-primary mb-3">
                            <i class="bi bi-people-fill"></i> Amount Due Per Person
                        </h3>
                    </div>
                    
                    <?php if (empty($participants)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-person-plus fs-1 d-block mb-3"></i>
                            <h4>No participants added yet</h4>
                            <p>Add participants to split this bill</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $totalAssigned = array_sum(array_column($participantAmounts, 'amount'));
                        foreach ($participantAmounts as $participantId => $data): 
                        ?>
                            <div class="participant-row">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" 
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person fs-4"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0 text-muted"><?php echo htmlspecialchars($data['name']); ?></h5>
                                            <small class="text-muted">Participant</small>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="badge bg-success amount-badge">
                                            <?php echo formatCurrency($data['amount']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Total row -->
                        <div class="total-row">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="bg-white text-success rounded-circle d-flex align-items-center justify-content-center me-3" 
                                         style="width: 50px; height: 50px;">
                                        <i class="bi bi-check-circle fs-4"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-0">Total Assigned</h4>
                                        <small class="opacity-75">
                                            <?php echo count($participants); ?> participant(s)
                                        </small>
                                    </div>
                                </div>
                                <div class="badge bg-white text-success fs-3 px-3 py-2">
                                    <?php echo formatCurrency($totalAssigned); ?>
                                </div>
                            </div>
                            
                            <?php if ($totalAssigned < $bill['total_amount']): ?>
                                <div class="mt-3 p-3 bg-warning text-dark rounded">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Remaining to assign: <?php echo formatCurrency($bill['total_amount'] - $totalAssigned); ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Detalle de items (collapsible) -->
            <div class="card mb-4">
                <div class="card-header">
                    <button class="btn btn-link text-decoration-none p-0 w-100 text-start" 
                            type="button" data-bs-toggle="collapse" data-bs-target="#itemsDetail">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">
                                <i class="bi bi-list-check"></i> Bill Items Detail
                            </h4>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                    </button>
                </div>
                <div class="collapse" id="itemsDetail">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Price</th>
                                        <th>Assigned To</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo formatCurrency($item['price']); ?></td>
                                            <td>
                                                <?php
                                                $assignedNames = [];
                                                foreach ($assignments as $assignment) {
                                                    if ($assignment['item_id'] == $item['item_id']) {
                                                        $shareText = $assignment['share_percentage'] == 100 ? '' : 
                                                                    " (" . $assignment['share_percentage'] . "%)";
                                                        $assignedNames[] = '<span class="badge bg-info text-dark me-1">' . 
                                                                          htmlspecialchars($assignment['participant_name']) . 
                                                                          $shareText . '</span>';
                                                    }
                                                }
                                                
                                                if (!empty($assignedNames)) {
                                                    echo implode('', $assignedNames);
                                                } else {
                                                    echo '<span class="badge bg-light text-muted">Unassigned</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar con imagen del ticket y herramientas para compartir -->
        <div class="col-lg-4">
            <!-- Imagen del recibo -->
            <?php if (!empty($bill['receipt_image'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-image"></i> Receipt Image
                    </h5>
                </div>
                <div class="card-body text-center">
                    <img src="<?php echo htmlspecialchars($bill['receipt_image']); ?>" 
                         alt="Receipt" 
                         class="img-fluid receipt-image"
                         data-bs-toggle="modal" 
                         data-bs-target="#receiptModal"
                         style="cursor: pointer;">
                    <p class="text-muted mt-2 small">Click to enlarge</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Herramientas para compartir -->
            <div class="share-card card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="bi bi-share"></i> Share This Bill
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Share Link:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="bill-url" 
                                   value="<?php echo BASE_URL; ?>/view_bill.php?id=<?php echo $billId; ?>" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyBillLink()">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                    
                    <!-- QR Code placeholder -->
                    <div class="text-center">
                        <p class="small fw-bold mb-2">QR Code:</p>
                        <div class="qr-code mx-auto">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode(BASE_URL . '/view_bill.php?id=' . $billId); ?>" 
                                 alt="QR Code" class="img-fluid">
                        </div>
                        <p class="text-muted small mt-2">Scan to view bill</p>
                    </div>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle"></i> Bill Info
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Bill ID:</strong> #<?php echo $billId; ?></p>
                    <p><strong>Items:</strong> <?php echo count($items); ?></p>
                    <p><strong>Participants:</strong> <?php echo count($participants); ?></p>
                    <?php if (!empty($bill['notes'])): ?>
                    <p><strong>Notes:</strong><br>
                       <small class="text-muted"><?php echo htmlspecialchars($bill['notes']); ?></small>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para imagen del recibo -->
<?php if (!empty($bill['receipt_image'])): ?>
<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Receipt Image - <?php echo htmlspecialchars($bill['restaurant_name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?php echo htmlspecialchars($bill['receipt_image']); ?>" 
                     alt="Receipt" 
                     class="img-fluid">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function copyBillLink() {
    const urlInput = document.getElementById('bill-url');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999); // Para móviles
    
    try {
        document.execCommand('copy');
        
        // Mostrar feedback visual
        const button = event.target.closest('button');
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-primary');
        
        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
        
    } catch (err) {
        alert('Could not copy link. Please copy manually.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar comportamiento de collapse manualmente
    const itemsCollapseElement = document.getElementById('itemsDetail');
    const collapseToggle = document.querySelector('[data-bs-target="#itemsDetail"]');
    const chevronIcon = collapseToggle.querySelector('.bi-chevron-down');
    
    // Crear instancia de collapse con Bootstrap
    const itemsCollapse = new bootstrap.Collapse(itemsCollapseElement, {
        toggle: false
    });
    
    // Mostrar elementos si no hay participantes
    const participantCount = <?php echo count($participants); ?>;
    if (participantCount === 0) {
        itemsCollapse.show();
    }
    
    // Añadir listener personalizado para actualizar el icono
    collapseToggle.addEventListener('click', function() {
        // Verificar si el elemento está colapsado o no
        const isCollapsed = itemsCollapseElement.classList.contains('show');
        
        if (isCollapsed) {
            itemsCollapse.hide();
            chevronIcon.classList.remove('bi-chevron-up');
            chevronIcon.classList.add('bi-chevron-down');
        } else {
            itemsCollapse.show();
            chevronIcon.classList.remove('bi-chevron-down');
            chevronIcon.classList.add('bi-chevron-up');
        }
    });
    
    // Actualizar icono según el estado inicial
    if (itemsCollapseElement.classList.contains('show')) {
        chevronIcon.classList.remove('bi-chevron-down');
        chevronIcon.classList.add('bi-chevron-up');
    }
});
</script>

<?php include 'includes/footer.php'; ?>