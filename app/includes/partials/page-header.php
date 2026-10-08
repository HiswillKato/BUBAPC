<?php
/**
 * Page header banner (the `py-4 bg-purple-soft` band). With a blank $page_header_title
 * only $page_header_content is rendered inside the band (the artist bio / search box headers).
 *
 * All three values are echoed VERBATIM: escape raw DB values with e() at the call site.
 *
 * @var string $page_header_title     '' to render the band with content only
 * @var string $page_header_subtitle  '' to omit
 * @var string $page_header_content   raw HTML echoed inside the band
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><section class="py-4 bg-purple-soft"><div class="container">
<?php if ($page_header_title !== '') { ?>
<h1 class="fw-bold mb-1"><?= $page_header_title ?></h1>
<?php if ($page_header_subtitle !== '') { ?>
<p class="text-muted mb-0"><?= $page_header_subtitle ?></p>
<?php } ?>
<?php } ?>
<?= $page_header_content ?>
</div></section>
