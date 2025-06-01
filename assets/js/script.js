/**
 * Bill Splitter main JS file
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    });
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    if (forms.length > 0) {
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
});

/**
 * Copy bill link to clipboard
 */
function copyBillLink() {
    const billUrl = document.getElementById('bill-url');
    if (billUrl) {
        navigator.clipboard.writeText(billUrl.value)
            .then(() => {
                const tooltip = bootstrap.Tooltip.getInstance(document.getElementById('copyLinkBtn'));
                tooltip.hide();
                document.getElementById('copyLinkBtn').setAttribute('data-bs-original-title', 'Copied!');
                tooltip.show();
                
                // Reset tooltip after 2 seconds
                setTimeout(() => {
                    document.getElementById('copyLinkBtn').setAttribute('data-bs-original-title', 'Copy Link');
                }, 2000);
            })
            .catch(err => {
                console.error('Failed to copy link: ', err);
            });
    }
}