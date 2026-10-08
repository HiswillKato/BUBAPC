<?php
/**
 * Bootstrap pagination nav. Renders nothing when there is a single page.
 *
 * @var array  $pagination  from paginate() / the list method that called it
 * @var string $base_url    the current URL without its `page` parameter
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
if ($pagination['total_pages'] <= 1) { return; }
$pagination_sep = str_contains($base_url, '?') ? '&' : '?';
?><nav aria-label="Page navigation"><ul class="pagination justify-content-center gap-2">
<?php if ($pagination['has_prev']) { ?>
<li class="page-item"><a class="page-link" href="<?= e($base_url . $pagination_sep . 'page=' . ($pagination['current_page'] - 1)) ?>"><?= icon('chevron-left') ?></a></li>
<?php } else { ?>
<li class="page-item disabled"><span class="page-link"><?= icon('chevron-left') ?></span></li>
<?php } ?>
<?php
$pagination_start = max(1, $pagination['current_page'] - 2);
$pagination_end = min($pagination['total_pages'], $pagination['current_page'] + 2);
for ($pagination_i = $pagination_start; $pagination_i <= $pagination_end; $pagination_i++) {
    $pagination_active = $pagination_i === $pagination['current_page'] ? ' active' : '';
    ?>
<li class="page-item<?= $pagination_active ?>"><a class="page-link" href="<?= e($base_url . $pagination_sep . 'page=' . $pagination_i) ?>"><?= $pagination_i ?></a></li>
<?php } ?>
<?php if ($pagination['has_next']) { ?>
<li class="page-item"><a class="page-link" href="<?= e($base_url . $pagination_sep . 'page=' . ($pagination['current_page'] + 1)) ?>"><?= icon('chevron-right') ?></a></li>
<?php } else { ?>
<li class="page-item disabled"><span class="page-link"><?= icon('chevron-right') ?></span></li>
<?php } ?>
</ul></nav>
