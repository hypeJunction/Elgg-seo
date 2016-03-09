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

	elgg_register_plugin_hook_handler('page_owner', 'system', 'seo_page_owner_fix');

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
		seo_save_data($data);
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
			// Replace __elgg_uri
			set_input(\Elgg\Application::GET_PATH_KEY, $original_path);

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
			$name_parts = explode(':', $name);
			$namespace = array_shift($name_parts);

			if (in_array($namespace, array('og', 'article', 'profile', 'book', 'music', 'video', 'profile', 'website'))) {
				// OGP tags use 'property=""' attribute
				$return['metas'][$name] = [
					'property' => $name,
					'content' => $content,
				];
			} else {
				$return['metas'][$name] = [
					'name' => $name,
					'content' => $content,
				];
			}
		}
	}

	return $return;
}

/**
 * Page owner magic relies on current_page_url(), which fails to reflect final route
 *
 * @param string $hook   "page_owner"
 * @param string $type   "system"
 * @param int    $return Page owner guid
 * @return int
 */
function seo_page_owner_fix($hook, $type, $return) {

	if ($return) {
		return;
	}

	$ia = elgg_set_ignore_access(true);

	$data = seo_get_data(current_page_url());
	if (empty($data['path'])) {
		return;
	}

	// ignore root and query
	$uri = elgg_normalize_url($data['path']);

	$path = str_replace(elgg_get_site_url(), '', $uri);
	$path = trim($path, "/");
	if (strpos($path, "?")) {
		$path = substr($path, 0, strpos($path, "?"));
	}

	// @todo feels hacky
	$segments = explode('/', $path);
	if (isset($segments[1]) && isset($segments[2])) {
		switch ($segments[1]) {
			case 'owner':
			case 'friends':
				$user = get_user_by_username($segments[2]);
				if ($user) {
					elgg_set_ignore_access($ia);
					return $user->getGUID();
				}
				break;
			case 'view':
			case 'edit':
				$entity = get_entity($segments[2]);
				if ($entity) {
					elgg_set_ignore_access($ia);
					return $entity->getContainerGUID();
				}
				break;
			case 'add':
			case 'group':
				$entity = get_entity($segments[2]);
				if ($entity) {
					elgg_set_ignore_access($ia);
					return $entity->getGUID();
				}
				break;
		}
	}

	elgg_set_ignore_access($ia);
}
