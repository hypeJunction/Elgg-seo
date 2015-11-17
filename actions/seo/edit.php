<?php

$data = get_input('seo');

if (seo_save_data($data)) {
	system_message(elgg_echo('seo:edit:success'));
} else {
	register_error(elgg_echo('seo:edit:error'));
}

forward(REFERRER);
