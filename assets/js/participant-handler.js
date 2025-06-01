
document.addEventListener('DOMContentLoaded', function() {
    console.log('Participant handler loaded');
    
    // Handle participant form submissions
    const addParticipantForm = document.getElementById('addParticipantForm');
    if (addParticipantForm) {
        addParticipantForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            console.log('Adding participant...');
            const submitBtn = addParticipantForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Adding...';
            
            // Form data
            const formData = new FormData(addParticipantForm);
            const billId = formData.get('bill_id');
            
            // Send with fetch instead of jQuery
            fetch('ajax/add_participant.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server returned ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Participant added:', data);
                
                // Close modal and reload page
                const modal = bootstrap.Modal.getInstance(document.getElementById('addParticipantModal'));
                if (modal) {
                    modal.hide();
                }
                
                // Give feedback before reload
                showToast('Success', 'Participant added successfully!', 'success');
                
                // Reload after a short delay
                setTimeout(() => {
                    window.location.href = 'split_bill.php?id=' + billId;
                }, 1000);
            })
            .catch(error => {
                console.error('Error adding participant:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
                showToast('Error', 'Failed to add participant. Please try again.', 'danger');
            });
        });
    }
    
    // Handle assignment buttons
    document.querySelectorAll('.assign-btn, [data-action="assign"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            console.log('Assignment button clicked');
            const btnText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            
            // Get item ID and other data
            const itemId = this.dataset.itemId;
            
            // Delayed re-enable in case of issues
            setTimeout(() => {
                if (btn.disabled) {
                    btn.disabled = false;
                    btn.innerHTML = btnText;
                    showToast('Warning', 'The operation timed out. Please try again.', 'warning');
                }
            }, 5000);
        });
    });
    
    // Simple toast notification
    function showToast(title, message, type = 'info') {
        // Create toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.setAttribute('aria-live', 'assertive');
        toastEl.setAttribute('aria-atomic', 'true');
        
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        // Add to container
        toastContainer.appendChild(toastEl);
        
        // Initialize and show toast
        const toast = new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        });
        toast.show();
        
        // Remove from DOM after hiding
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
});