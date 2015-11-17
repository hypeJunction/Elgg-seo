<?php

echo '<div class="pal">';
echo elgg_view('output/url', [
	'href' => 'action/seo/autogen',
	'text' => elgg_echo('seo:autogen'),
	'class' => 'elgg-button elgg-button-action',
	'is_action' => true,
]);
echo '</div>';