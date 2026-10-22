<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

?>

<?php include 'includes/header.php'; ?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold">Anonymous Bill Splitter</h1>
                <p class="lead">Split your bill quickly without creating an account</p>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Note:</strong> Your data won't be saved permanently in anonymous mode.
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-calculator me-2"></i>Quick Bill Split</h4>
                </div>
                <div class="card-body p-4">
                    <form id="anonymousBillForm" novalidate>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="people_count" class="form-label">Number of People</label>
                                <input type="number" class="form-control" id="people_count" min="2" max="20" value="2" required>
                                <div class="invalid-feedback">
                                    Please enter between 2 and 20 people.
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <h5 class="text-primary fw-bold"><i class="bi bi-receipt me-2"></i>Bill Items</h5>
                                <button type="button" class="btn btn-success btn-sm" id="addItemBtn">
                                    <i class="bi bi-plus-circle me-1"></i>Add Item
                                </button>
                            </div>
                            
                            <div id="itemsList">
                                <div class="item-row mb-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-5">
                                            <input type="text" class="form-control item-name" placeholder="Item name" required>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control item-price" step="0.01" min="0.01" placeholder="0.00" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control item-quantity" min="1" value="1" placeholder="Qty" required>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <button type="button" class="btn btn-danger btn-sm remove-item-btn" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-calculator me-2"></i>Calculate Split
                        </button>
                    </form>

                    <div id="results" class="mt-4" style="display: none;">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title">Split Results</h5>
                                <div id="split-details"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <p class="mb-3">Want to save your splits and access more features?</p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="register.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Sign Up Free
                    </a>
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="assets/js/money.js"></script>
<script>
let itemCount = 1;

// Add item functionality
document.getElementById('addItemBtn').addEventListener('click', function() {
    const itemsList = document.getElementById('itemsList');
    const newItem = document.createElement('div');
    newItem.className = 'item-row mb-3';
    newItem.innerHTML = `
        <div class="row align-items-center">
            <div class="col-md-5">
                <input type="text" class="form-control item-name" placeholder="Item name" required>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control item-price" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control item-quantity" min="1" value="1" placeholder="Qty" required>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-danger btn-sm remove-item-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    
    itemsList.appendChild(newItem);
    itemCount++;
    updateRemoveButtons();
    
    // Add event listeners to new inputs
    addInputEventListeners(newItem);
});

// Remove item functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-item-btn')) {
        e.target.closest('.item-row').remove();
        itemCount--;
        updateRemoveButtons();
        updateTotal();
    }
});

// Update remove buttons state
function updateRemoveButtons() {
    const removeButtons = document.querySelectorAll('.remove-item-btn');
    const itemRows = document.querySelectorAll('.item-row');
    
    removeButtons.forEach(btn => {
        btn.disabled = itemRows.length <= 1;
    });
}

// Add event listeners to inputs
function addInputEventListeners(container = document) {
    container.querySelectorAll('.item-price, .item-quantity').forEach(input => {
        input.addEventListener('input', updateTotal);
    });
    
    container.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('is-invalid', 'is-valid');
        });
    });
}

// Update total display
function updateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const quantity = parseInt(row.querySelector('.item-quantity').value) || 0;
        total += price * quantity;
    });
    
    // You can add a total display here if needed
}

// Form submission
document.getElementById('anonymousBillForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Reset validation states
    const form = this;
    form.classList.remove('was-validated');
    
    const peopleCount = Number(document.getElementById('people_count').value);
    
    // Collect items
    const items = [];
    let totalCents = 0;
    let isValid = true;
    
    // Validate people count
    if (!Number.isInteger(peopleCount) || peopleCount < 2 || peopleCount > 20) {
        document.getElementById('people_count').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('people_count').classList.remove('is-invalid');
        document.getElementById('people_count').classList.add('is-valid');
    }
    
    // Validate items
    document.querySelectorAll('.item-row').forEach(row => {
        const name = row.querySelector('.item-name').value.trim();
        const priceCents = BillMoney.toCents(row.querySelector('.item-price').value);
        const price = priceCents === null ? NaN : priceCents / 100;
        const quantity = Number(row.querySelector('.item-quantity').value);
        
        // Validate item name
        if (!name) {
            row.querySelector('.item-name').classList.add('is-invalid');
            isValid = false;
        } else {
            row.querySelector('.item-name').classList.remove('is-invalid');
            row.querySelector('.item-name').classList.add('is-valid');
        }
        
        // Validate item price
        if (!price || price <= 0) {
            row.querySelector('.item-price').classList.add('is-invalid');
            isValid = false;
        } else {
            row.querySelector('.item-price').classList.remove('is-invalid');
            row.querySelector('.item-price').classList.add('is-valid');
        }
        
        // Validate quantity
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
            row.querySelector('.item-quantity').classList.add('is-invalid');
            isValid = false;
        } else {
            row.querySelector('.item-quantity').classList.remove('is-invalid');
            row.querySelector('.item-quantity').classList.add('is-valid');
        }
        
        if (name && price > 0 && quantity > 0) {
            const itemTotal = price * quantity;
            items.push({ name, price, quantity, total: itemTotal });
            totalCents += priceCents * quantity;
        }
    });
    
    if (!isValid) {
        form.classList.add('was-validated');
        return;
    }
    
    if (items.length === 0) {
        alert('Please add at least one item.');
        return;
    }
    
    // Calculate split
    const split = BillMoney.split(totalCents, peopleCount);
    const perPerson = split.base / 100;
    const totalAmount = totalCents / 100;
    
    // Display results
    const resultsDiv = document.getElementById('results');
    const detailsDiv = document.getElementById('split-details');
    
    let itemsHtml = '';
    items.forEach(item => {
        const name = document.createElement('span');
        name.textContent = item.name;
        itemsHtml += `<p><strong>${name.innerHTML}</strong> - ${item.quantity}x $${item.price.toFixed(2)} = $${item.total.toFixed(2)}</p>`;
    });
    
    detailsDiv.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6>Items:</h6>
                ${itemsHtml}
                <hr>
                <p><strong>Total:</strong> $${totalAmount.toFixed(2)}</p>
            </div>
            <div class="col-md-6">
                <div class="alert alert-success">
                    <h4 class="alert-heading">Per Person</h4>
                    <h2 class="mb-0">$${perPerson.toFixed(2)}</h2>
                    <small class="text-muted">${peopleCount - split.extra} people pay this amount${split.extra ? `; ${split.extra} pay $${((split.base + 1) / 100).toFixed(2)}` : ''}.</small>
                </div>
            </div>
        </div>
    `;
    
    resultsDiv.style.display = 'block';
    resultsDiv.scrollIntoView({ behavior: 'smooth' });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    addInputEventListeners();
    updateRemoveButtons();
});
</script>

<?php include 'includes/footer.php'; ?>
