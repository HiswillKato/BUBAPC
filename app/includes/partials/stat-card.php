<?php
/**
 * A single admin/dashboard stat tile. The Bootstrap column wrapper comes from the page.
 *
 * @var string      $stat_label  pre-escaped label
 * @var int|string  $stat_value  formatted with number_format()
 * @var string      $stat_icon   Font Awesome name
 * @var string      $stat_color  Bootstrap contextual name: 'primary', 'danger', …
 * @var string      $stat_extra  extra classes for the outer .card, '' for none
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><div class="card<?= $stat_extra !== '' ? ' ' . e($stat_extra) : '' ?> stat-card">
<div class="card-body d-flex align-items-center gap-3">
<div class="stat-icon bg-<?= e($stat_color) ?> bg-opacity-10 text-<?= e($stat_color) ?>"><?= icon($stat_icon) ?></div>
<div><div class="text-muted small"><?= $stat_label ?></div><div class="fs-4 fw-bold"><?= number_format($stat_value) ?></div></div>
</div></div>
