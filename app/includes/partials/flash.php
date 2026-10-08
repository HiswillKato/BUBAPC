<?php
/**
 * The one-shot flash message set by flash(). Required by all three shell headers,
 * each of which has already checked `if ($flash)` via flash().
 *
 * @var array $flash  ['type' => …, 'message' => …] straight from flash()
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert"><?= e($flash['message'] ?? '') ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
