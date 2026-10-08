<?php
/**
 * Song card with play button, optional favorite + download actions, and an optional
 * "plays" line. The play button is the global-player trigger; favorite state comes
 * from $card_opt['music'] unless $card_opt['active'] forces it (favorites page).
 *
 * @var array $song      a row with title/artist_name/slug/duration/artwork/audio/…
 * @var array $card_opt  favorite (default true), download (default true), plays,
 *                       compact (song.php related rail), active, danger (favorites page),
 *                       playlist (add-to-playlist button), music (a Music instance),
 *                       logged_in, cell (outer column classes)
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }
$card_compact = $card_opt['compact'] ?? false;
$card_favorite = $card_opt['favorite'] ?? !$card_compact;
$card_playlist = $card_opt['playlist'] ?? !$card_compact;
$card_download = $card_opt['download'] ?? !$card_compact;
$card_plays = $card_opt['plays'] ?? false;
$card_active = $card_opt['active'] ?? false;
$card_danger = $card_opt['danger'] ?? false;
$card_cell = $card_opt['cell'] ?? ($card_compact ? 'col-6 col-md-4 col-lg-2' : 'col-6 col-md-4 col-lg-3');

$card_id = (int)($song['id'] ?? 0);
$card_title = e($song['title'] ?? '');
$card_artist = e($song['artist_name'] ?? '');
$card_artwork = image($song['artwork'] ?? '', 'song');
$card_audio = asset($song['audio'] ?? '');
$card_href = url('song/' . ($song['slug'] ?? ''));

$card_is_fav = $card_active;
if (!$card_is_fav && $card_favorite && !empty($card_opt['music'])) {
    $card_is_fav = $card_opt['music']->favorited($card_id);
}
$card_logged_in = ($card_opt['logged_in'] ?? $user->authed()) ? '1' : '0';
$card_fav_class = $card_danger
    ? 'btn btn-outline-danger btn-sm btn-favorite' . ($card_active ? ' active' : '')
    : 'btn btn-outline-secondary btn-sm btn-favorite' . ($card_is_fav ? ' active text-danger' : '');
?><div class="<?= e($card_cell) ?>">
<div class="card song-card" data-href="<?= e($card_href) ?>">
<img src="<?= e($card_artwork) ?>" class="card-img-top" alt="<?= $card_title ?>">
<div class="card-body<?= $card_compact ? ' p-2' : '' ?>">
<h6 class="card-title text-truncate<?= $card_compact ? ' small' : '' ?> mb-1">
<a href="<?= e($card_href) ?>" class="text-decoration-none text-dark stretched-link"><?= $card_title ?></a>
</h6>
<?php
$card_duration = (int)($song['duration'] ?? 0);
$card_show_meta = $card_duration > 0 || $card_plays;
if (!$card_compact) { ?>
<p class="card-text small text-muted text-truncate<?= $card_show_meta ? ' mb-1' : ' mb-2' ?>"><?= $card_artist ?></p>
<?php } ?>
<?php if ($card_show_meta) { ?>
<p class="small text-muted mb-2 d-flex gap-3">
<?php if ($card_duration > 0) { ?><span><?= icon('clock', 'me-1') ?><?= duration($card_duration) ?></span><?php } ?>
<?php if ($card_plays) { ?><span><?= icon('circle-play', 'me-1') ?><?= e(abbrev($song['plays'] ?? 0)) ?> Plays</span><?php } ?>
</p>
<?php } ?>
<div class="d-flex align-items-center gap-1 song-card-controls">
<button class="btn btn-gold btn-sm btn-play-song<?= $card_compact ? ' flex-fill' : '' ?>"
 data-id="<?= $card_id ?>" data-title="<?= $card_title ?>" data-artist="<?= $card_artist ?>"
 data-cover="<?= e($card_artwork) ?>" data-audio="<?= e($card_audio) ?>" data-duration="<?= e($song['duration'] ?? '') ?>"
 aria-label="Play <?= $card_title ?>"><?= icon('play') ?></button>
<?php if ($card_favorite || $card_download || $card_playlist) { ?>
<div class="card-actions ms-auto">
<?php if ($card_favorite) { ?>
<button class="<?= e($card_fav_class) ?>" data-id="<?= $card_id ?>" data-logged-in="<?= $card_logged_in ?>"
 aria-label="<?= $card_is_fav ? "Remove from favorites" : "Toggle favorite" ?>"><?= icon('heart', $card_is_fav ? 'solid' : 'regular') ?></button>
<?php } ?>
<?php if ($card_playlist) { ?>
<button class="btn btn-outline-secondary btn-sm btn-add-playlist" data-id="<?= $card_id ?>" data-logged-in="<?= $card_logged_in ?>"
 data-title="<?= e($card_title) ?>"
 title="Add to playlist" aria-label="Add <?= $card_title ?> to a playlist"><?= icon('list') ?></button>
<?php } ?>
<?php if ($card_download) { ?>
<a href="<?= e(url('download/' . ($song['slug'] ?? ''))) ?>" class="song-download btn btn-outline-secondary btn-sm" title="Download"
 aria-label="Download <?= $card_title ?>"><?= icon('download') ?></a>
<?php } ?>
</div>
<?php } ?>
</div></div></div></div>
