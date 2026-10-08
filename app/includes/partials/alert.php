<?php
/**
 * A Bootstrap inline alert. Renders nothing when $alert_text is ''.
 *
 * @var string $alert_type  'success' | 'danger' | 'warning' | …
 * @var string $alert_text  the message; escaped here, so pass raw text
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
if ($alert_text === '') { return; } ?><div class="alert alert-<?= e($alert_type) ?>"><?= e($alert_text) ?></div>
