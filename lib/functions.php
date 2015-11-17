<?php

/**
 * Get SEF equivalent for a given URL
 *
 * @param string $url URL
 * @return string
 */
function seo_get_sef_url($url) {

	$data = seo_get_data($url);
	if (!$data) {
		return $url;
	}
	$sef_path = elgg_extract('sef_path', $data);
	if ($sef_path) {
		return elgg_normalize_url($sef_path);
	}

	return $url;
}

/**
 * Get URL data
 *
 * @param string $url URL
 * @return array|false
 */
function seo_get_data($url) {

	$url = elgg_normalize_url($url);
	if (parse_url($url, PHP_URL_HOST) != parse_url(elgg_get_site_url(), PHP_URL_HOST)) {
		return false;
	}
	$path = parse_url($url, PHP_URL_PATH);
	$hash = sha1($path);

	$site = elgg_get_site_entity();
	$file = new ElggFile();
	$file->owner_guid = $site->guid;
	$file->setFilename("seo/{$hash}.json");

	$data = false;
	if ($file->exists()) {
		$file->open('read');
		$json = $file->grabFile();
		$file->close();
		$data = json_decode($json, true);
	}

	return $data;
}

/**
 * Save SEF data
 *
 * @param array $data Data
 * @return bool
 */
function seo_save_data($data) {

	$path = elgg_extract('path', $data);
	$sef_path = elgg_extract('sef_path', $data);
	if (!$path || !$sef_path) {
		return false;
	}

	$sef_hash = sha1($sef_path);
	$original_hash = sha1($path);

	$site = elgg_get_site_entity();
	$file = new ElggFile();
	$file->owner_guid = $site->guid;
	$file->setFilename("seo/{$sef_hash}.json");

	$file->open('write');
	$file->write(json_encode($data));
	$file->close();

	if ($sef_hash != $original_hash) {
		$file = new ElggFile();
		$file->owner_guid = $site->guid;
		$file->setFilename("seo/{$original_hash}.json");

		$file->open('write');
		$file->write(json_encode($data));
		$file->close();
	}

	return true;
}

/**
 * Prepare entity SEF data
 *
 * @param \ElggEntity $entity Entity
 * @return array|false
 */
function seo_prepare_entity_data(\ElggEntity $entity) {
	$path = parse_url($entity->getURL(), PHP_URL_PATH);
	if (!$path || $path == '/') {
		return false;
	}
	$type = $entity->getType();

	switch ($type) {

		case 'user' :
			$sef_path = "/profile/$entity->username";
			break;

		case 'group' :
		case 'object' :
			$prefix = $type;
			$subtype = $entity->getSubtype();
			if ($subtype) {
				$prefix = $subtype;
			}
			$friendly_title = elgg_get_friendly_title($entity->getDisplayName() ? : '');
			$sef_path = "/$prefix/{$entity->guid}-{$friendly_title}";
			break;
	}

	$sef_data = [
		'path' => $path,
		'sef_path' => $sef_path,
		'title' => $entity->getDisplayName(),
		'description' => elgg_get_excerpt($entity->description),
		'keywords' => is_array($entity->tags) ? implode(',', $entity->tags) : $entity->tags,
		'guid' => $entity->guid,
	];

	$sef_data['metatags'] = elgg_trigger_plugin_hook('metatags', 'discovery', [
		'entity' => $entity,
		'url' => elgg_normalize_url($sef_path),
			], []);

	if ($entity->guid != $entity->owner_guid) {
		$owner = $entity->getOwnerEntity();
		if ($owner) {
			$sef_data['owner'] = seo_prepare_entity_data($owner);
		}
	}

	if ($entity->guid != $entity->container_guid && $entity->owner_guid != $entity->container_guid) {
		$container = $entity->getContainerEntity();
		if ($container) {
			$sef_data['container'] = seo_prepare_entity_data($container);
		}
	}

	return $sef_data;
}
