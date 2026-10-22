<footer class="footer py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h5 class="mb-3">BILL SPLITTER</h5>
                    <p>Keep a receipt, assign items to people and calculate each person's share.</p>
                </div>
                
                <div class="col-md-2 offset-md-1 mb-3 mb-md-0">
                    <h5 class="mb-3">QUICK LINKS</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>" class="text-light">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#how-it-works" class="text-light">How It Works</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/dashboard.php" class="text-light">Dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/upload_receipt.php" class="text-light">Upload Receipt</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 offset-md-1">
                    <h5 class="mb-3">SOURCE</h5>
                    <p><a href="https://github.com/nachopalomares/billsplitter" class="text-light">View the code on GitHub</a></p>
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
