document.addEventListener('DOMContentLoaded', function() {
    function adjustBodyPadding() {
        const navbar = document.querySelector('.navbar.fixed-top');
        if (navbar) {
            const navbarHeight = navbar.offsetHeight;
            document.body.style.paddingTop = navbarHeight + 'px';
            
            // Optional: Add some extra spacing for better visual separation
            const extraSpacing = 10; // pixels
            document.body.style.paddingTop = (navbarHeight + extraSpacing) + 'px';
        }
    }
    
    // Adjust when page loads
    adjustBodyPadding();
    
    // Adjust when window is resized (responsive behavior)
    window.addEventListener('resize', adjustBodyPadding);
    
    // Adjust after fonts and all resources are loaded (they can change navbar height)
    window.addEventListener('load', adjustBodyPadding);

    if (window.ResizeObserver) {
        const navbar = document.querySelector('.navbar.fixed-top');
        if (navbar) {
            const resizeObserver = new ResizeObserver(entries => {
                adjustBodyPadding();
            });
            resizeObserver.observe(navbar);
        }
    }
});
