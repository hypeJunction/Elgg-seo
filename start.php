<?php

/**
 * SEO Tools for Elgg
 *
 * @author Ismayil Khayredinov <info@hypejunction.com>
 * @copyright Copyright (c) 2015, Ismayil Khayredinov
 */
require_once __DIR__ . '/autoloader.php';

elgg_register_event_handler('init', 'system', 'seo_init');

/**
 * Initialize the plugin
 * @return void
 */
function seo_init() {

	elgg_register_page_handler('seo', 'seo_page_handler');

	elgg_register_action('seo/autogen', __DIR__ . '/actions/seo/autogen.php', 'admin');
	elgg_register_action('seo/edit', __DIR__ . '/actions/seo/edit.php', 'admin');

	elgg_register_event_handler('create', 'object', 'seo_update_entity');
	elgg_register_event_handler('update', 'object', 'seo_update_entity');
	elgg_register_event_handler('create', 'user', 'seo_update_entity');
	elgg_register_event_handler('update', 'user', 'seo_update_entity');
	elgg_register_event_handler('create', 'group', 'seo_update_entity');
	elgg_register_event_handler('update', 'group', 'seo_update_entity');

	elgg_register_plugin_hook_handler('view_vars', 'output/url', 'seo_sef_url_rewrite');
	elgg_register_plugin_hook_handler('route', 'all', 'seo_route', 1);
	elgg_register_plugin_hook_handler('head', 'page', 'seo_page_head_setup');

	elgg_extend_view('elgg.css', 'seo.css');
	
	if (elgg_is_admin_logged_in()) {
		elgg_register_menu_item('extras', array(
			'name' => 'seo',
			'text' => elgg_view_icon('search'),
			'title' => elgg_echo('seo:edit'),
			'href' => elgg_http_add_url_query_elements('seo/edit', array(
				'page_uri' => current_page_url(),
			)),
			'link_class' => 'elgg-lightbox',
		));
	}
}

/**
 * SEO page handler
 * /seo/edit
 * 
 * @param array $segments URL segments
 * @return bool
 */
function seo_page_handler($segments) {

	$page = array_shift($segments);

	switch ($page) {
		case 'edit' :
			echo elgg_view_resource('seo/edit');
			return true;
	}

	return false;
}

/**
 * Populate SEF data when entity is created
 *
 * @param string     $event  'create'
 * @param string     $type   'object', 'user' or 'group'
 * @param ElggEntity $entity Entity
 * @return void
 */
function seo_update_entity($event, $type, $entity) {
	$data = seo_prepare_entity_data($entity);
	if ($data) {
		if (empty($data['admin_defined'])) {
			seo_save_data($data);
		}
	}
}

/**
 * Substitute URLs with their SEF equivalent
 *
 * @param string $hook   "view_vars"
 * @param string $type   "output/url"
 * @param array  $return View vars
 * @param array  $params Hook params
 * @return array
 */
function seo_sef_url_rewrite($hook, $type, $return, $params) {

	$href = elgg_extract('href', $return);
	$sef = seo_get_sef_url($href);
	if ($sef) {
		$return['href'] = $sef;
		$return['is_trusted'] = true;
	}

	return $return;
}

/**
 * Route SEF URLs to their original path
 *
 * @param string $hook   "route"
 * @param string $type   "all"
 * @param array  $return Segments and handler
 * @param array  $params Hook params
 * @return array
 */
function seo_route($hook, $type, $return, $params) {

	$identifier = elgg_extract('identifier', $params);
	$segments = (array) elgg_extract('segments', $params, []);

	array_unshift($segments, $identifier);

	$path = implode('/', $segments);
	$url = elgg_normalize_url($path);

	$data = seo_get_data($url);
	if ($data) {
		$sef_path = elgg_extract('sef_path', $data);
		$original_path = elgg_extract('path', $data);

		if (elgg_normalize_url($sef_path) == $url) {
			$segments = explode('/', trim($original_path, '/'));
			$identifier = array_shift($segments);
			return [
				'identifier' => $identifier,
				'segments' => $segments,
				'handler' => $identifier,
			];
		}
	}
}

/**
 * Setup SEO data in page head
 *
 * @param string $hook   "head"
 * @param string $type   "page"
 * @param array  $return Page head
 * @param array  $params Hook params
 * @return array
 */
function seo_page_head_setup($hook, $type, $return, $params) {

	$data = seo_get_data(current_page_url());
	if (!$data) {
		return;
	}
	$title = elgg_extract('title', $data);
	$description = elgg_extract('description', $data);
	$keywords = elgg_extract('keywords', $data);
	$metatags = elgg_extract('metatags', $data);

	if ($title) {
		$return['title'] = $title;
	}
	if ($description) {
		$return['metas']['description'] = [
			'name' => 'description',
			'content' => $description,
		];
	}
	if ($keywords) {
		$return['metas']['keywords'] = [
			'name' => 'keywords',
			'content' => $keywords
		];
	}
	if (!empty($metatags) && is_array($metatags)) {
		foreach ($metatags as $name => $content) {
			if (!$content) {
				continue;
			}
			$return['metas'][$name] = [
				'name' => $name,
				'content' => $content,
			];
		}
	}

	return $return;
}
