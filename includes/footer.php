<footer class="footer py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5 class="mb-3">BILL SPLITTER</h5>
                    <p>Your one-stop solution to split bills effortlessly with friends and family. Upload your receipts, tag your friends, and let us do the math!</p>
                </div>
                
                <div class="col-md-2 offset-md-1 mb-3 mb-md-0">
                    <h5 class="mb-3">QUICK LINKS</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>" class="text-light">Home</a></li>
                        <li><a href="#how-it-works" class="text-light">How It Works</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/dashboard.php" class="text-light">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/upload_receipt.php" class="text-light">Upload Receipt</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 offset-md-1">
                    <h5 class="mb-3">CONTACT US</h5>
                    <p><a href="mailto:support@billsplitter.com" class="text-light">support@billsplitter.com</a></p>
                    <p><a href="tel:+1234567890" class="text-light">+1 234 567 890</a></p>
                </div>
            </div>
            
            <div class="row mt-4 border-top pt-3">
                <div class="col-12 text-center">
                    <p class="mb-0">© <?php echo date('Y'); ?> Bill Splitter. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

     <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/participant-handler.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($page_scripts)) echo $page_scripts; ?>

    <style>
        /* Fix for white space at bottom of page */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        
        main {
            flex: 1 0 auto;
        }
        
        footer {
            flex-shrink: 0;
            margin-top: auto;
        }
    </style>
</body>
</html>
