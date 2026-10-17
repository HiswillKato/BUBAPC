<?php
/**
 * The shared JS <script> tags (Bootstrap, behaviour). Required by each shell's footer.
 */
if (!defined('APP_INITIALIZED')) { http_response_code(404); exit; } ?><script src="<?= asset('js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= asset('js/main.js') ?>"></script>
