<?php
/**
 * BUBAPC - The bottom of every page inside /dashboard/admin
 * It closes the tags opened by app/includes/admin/header.php.
 */
?>
        </div>

        <footer class="p-3 border-top bg-body">
            <p class="small text-body-secondary mb-0"><?php echo e(SITE_NAME); ?> admin &mdash; <?php echo date('j F Y'); ?></p>
        </footer>
    </div>
</div>

<div class="admin-sidebar-backdrop" data-sidebar-close hidden></div>

<script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
