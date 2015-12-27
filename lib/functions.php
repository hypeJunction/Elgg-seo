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
 * Extracts request path relative to site installation
 * 
 * @note We can't rely on full request path, because Elgg can be installed in a subdirectory
 * 
 * @param string $url URL
 * @return string|false
 */
function seo_get_path($url) {
	$url = elgg_normalize_url($url);
	$site_url = elgg_get_site_url();
	if (0 !== strpos($url, $site_url)) {
		return false;
	}
	return '/' . substr($url, strlen($site_url));
}
/**
 * Get URL data
 *
 * @param string $url URL
 * @return array|false
 */
function seo_get_data($url) {

	$path = seo_get_path($url);
	if (!$path) {
		return false;
	}
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
	$path = seo_get_path($entity->getURL());
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

	$sef_data = seo_get_data($entity->getURL());
	if (!is_array($sef_data)) {
		$sef_data = array();
	}

	$entity_sef_data = [
		'path' => $path,
		'title' => $entity->getDisplayName(),
		'description' => elgg_get_excerpt($entity->description),
		'keywords' => is_array($entity->tags) ? implode(',', $entity->tags) : $entity->tags,
		'guid' => $entity->guid,
	];

	if ($entity->guid != $entity->owner_guid) {
		$owner = $entity->getOwnerEntity();
		if ($owner) {
			$entity_sef_data['owner'] = seo_prepare_entity_data($owner);
		}
	}

	if ($entity->guid != $entity->container_guid && $entity->owner_guid != $entity->container_guid) {
		$container = $entity->getContainerEntity();
		if ($container) {
			$entity_sef_data['container'] = seo_prepare_entity_data($container);
		}
	}

	if (empty($sef_data['admin_defined'])) {
		$sef_data = array_merge($sef_data, $entity_sef_data);
	} else {
		foreach ($entity_sef_data as $key => $value) {
			if (empty($sef_data[$key])) {
				$sef_data[$key] = $value;
			}
		}
	}

	$entity_sef_metatags = elgg_trigger_plugin_hook('metatags', 'discovery', [
		'entity' => $entity,
		'url' => elgg_normalize_url($sef_path),
			], []);

	if (!empty($entity_sef_metatags)) {
		foreach ($entity_sef_metatags as $key => $value) {
			if (empty($sef_data['admin_defined']) || empty($sef_data['metatags'][$key])) {
				$sef_data['metatags'][$key] = $value;
			}
		}
	}

	return $sef_data;
}
