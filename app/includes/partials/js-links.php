<?php
/**
 * The shared JS <script> tags (jQuery, Bootstrap, app). Required by each shell's footer.
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><script src="<?= asset('js/jquery.min.js') ?>"></script>
<script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
