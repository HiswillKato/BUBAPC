<?php
/**
 * Artist card linking to the artist page, with cover image, name and song count.
 *
 * @var array $artist    a row with name/slug/image/song_count
 * @var array $card_opt  compact (hides the count, tighter body), cell (outer column classes)
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
$card_compact = $card_opt['compact'] ?? false;
$card_cell = $card_opt['cell'] ?? 'col-6 col-md-4 col-lg-2';
?><div class="<?= e($card_cell) ?>">
<div class="card artist-card text-center" data-href="<?= e(url('artist/' . ($artist['slug'] ?? ''))) ?>">
<img src="<?= e(image($artist['image'] ?? '', 'artist')) ?>" class="card-img-top" alt="<?= e($artist['name'] ?? '') ?>">
<div class="card-body<?= $card_compact ? ' p-2' : '' ?>">
<h6 class="card-title<?= $card_compact ? ' small' : '' ?><?= $card_compact ? '' : ' mb-1' ?>"><a href="<?= e(url('artist/' . ($artist['slug'] ?? ''))) ?>" class="text-decoration-none text-dark stretched-link"><?= e($artist['name'] ?? '') ?></a></h6>
<?php if (!$card_compact) { ?>
<p class="small text-muted mb-0"><?= e(sprintf("%d songs", (int)($artist['song_count'] ?? 0))) ?></p>
<?php } ?>
</div></div></div>
