<?php

set_time_limit(0);

$entities = new ElggBatch('elgg_get_entities', [
	'limit' => 0,
	'order_by' => 'e.guid ASC',
		]);

$i = $s = 0;
foreach ($entities as $entity) {
	$i++;
	$data = seo_prepare_entity_data($entity);
	if ($data) {
		if (seo_save_data($data)) {
			$s++;
		}
	}
}

system_message(elgg_echo('seo:autogen:count', [$s, $i]));
forward(REFERRER);
