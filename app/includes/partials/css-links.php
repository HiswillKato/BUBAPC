<?php
/**
 * The shared CSS <link> tags (Bootstrap, Font Awesome, custom).
 * Required by each shell's <head>, the installer and the two error pages.
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><link href="<?= asset('css/bootstrap.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/all.min.css') ?>" rel="stylesheet">
<link href="<?= asset('css/style.css') ?>" rel="stylesheet">
