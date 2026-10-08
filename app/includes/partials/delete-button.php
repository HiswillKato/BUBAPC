<?php
/**
 * The inline POST form that deletes a row after a confirm() dialog. Posts
 * `action=delete` + the row id (the classic admin delete button).
 *
 * @var int    $delete_id          the row id
 * @var string $delete_confirm     the confirm() text
 * @var string $delete_form_class  classes for the <form>
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><form method="POST" class="<?= e($delete_form_class) ?>" onsubmit="return confirm('<?= e(str_replace("'", "\\'", $delete_confirm)) ?>');">
<input type="hidden" name="csrf_token" value="<?= e(csrf()) ?>">
<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $delete_id ?>">
<button type="submit" class="btn btn-sm btn-outline-danger"><?= icon('trash') ?></button>
</form>
