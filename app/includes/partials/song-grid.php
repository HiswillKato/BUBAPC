<?php
/**
 * A grid of song cards, or the caller's empty state when there is nothing to show.
 *
 * Seven pages render the same "if the list is empty show an empty state, otherwise
 * loop the rows through song-card.php" block. This partial owns that wiring; the
 * caller supplies the data and the copy.
 *
 * Every name it reads is prefixed `$grid_` because the include scope is the
 * caller's scope. It also leaves `$song`, `$card_opt` and the `$empty_*` set the
 * card and empty-state partials use, exactly as the inline blocks did.
 *
 * @var array  $grid_items       the rows to render
 * @var array  $grid_card_opt    options forwarded to song-card.php as $card_opt
 * @var string $grid_empty_icon  Font Awesome name for the empty state
 * @var string $grid_empty_title pre-escaped empty-state heading
 * @var string $grid_empty_text  pre-escaped body, '' to omit
 * @var string $grid_empty_cta   raw HTML after the message, '' for none
 * @var string $grid_row_class   wrapper classes, default 'row g-4'
 * @var bool   $grid_row_open    true when the caller has already opened the wrapper
 *                               and it must stay open across the empty state (the
 *                               homepage); false (default) lets this partial open and
 *                               close it around the cards only
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; }

$grid_row_class = $grid_row_class ?? 'row g-4';
$grid_row_open = $grid_row_open ?? false;

if (!$grid_row_open) { ?><div class="<?= e($grid_row_class) ?>"><?php } ?>
<?php if (empty($grid_items)) {
    $empty_icon = $grid_empty_icon;
    $empty_title = $grid_empty_title;
    $empty_text = $grid_empty_text;
    $empty_cta = $grid_empty_cta;
    require __DIR__ . '/empty-state.php';
} else { foreach ($grid_items as $song) {
    $card_opt = $grid_card_opt;
    require __DIR__ . '/song-card.php';
} } ?>
<?php if (!$grid_row_open) { ?></div><?php } ?>
