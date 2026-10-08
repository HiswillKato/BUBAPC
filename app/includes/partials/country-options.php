<?php
/**
 * The <option> list for a country <select>, built from the COUNTRY_LIST constant.
 * The <select> element itself belongs to the calling form.
 *
 * @var string $country_selected  the ISO 3166-1 alpha-2 code to preselect, '' for the placeholder
 * @var bool   $country_required  true in the signup forms: the placeholder reads "Select your country"
 */
if (!defined('APP_BOOTSTRAPPED')) { http_response_code(404); exit; } ?><option value=""><?= $country_required ? "Select your country" : "Select a country" ?></option>
<?php foreach (COUNTRY_LIST as $country_option_code => $country_option_name) { ?>
<option value="<?= e($country_option_code) ?>"<?= $country_selected === $country_option_code ? ' selected' : '' ?>><?= e($country_option_name) ?></option>
<?php } ?>
