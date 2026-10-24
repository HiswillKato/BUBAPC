<?php
/**
 * BUBAPC - The bottom of every page inside /dashboard/admin
 * It closes the tags opened by app/includes/admin/header.php and loads the
 * two scripts. There is no footer strip and no sidebar backdrop: Bootstrap
 * owns the offcanvas and the dimming layer behind it, and a line under every
 * list repeating the site name and the date said nothing worth scrolling to.
 */
?>
        </div>
    </div>
</div>

<script src="<?php echo asset('js/bootstrap.bundle.min.js'); ?>"></script>
<script src="<?php echo asset('js/main.js'); ?>"></script>
</body>
</html>
