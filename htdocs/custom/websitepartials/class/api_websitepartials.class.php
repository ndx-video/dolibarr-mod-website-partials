<?php
/* Copyright (C) 2026	Bray Park SDA Church / ndx-video
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/websitepartials/class/api_websitepartials.class.php
 * \ingroup websitepartials
 * \brief   REST API for Website CMS sites and containers (full CRUD, module rights).
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/api/class/api.class.php';
dol_include_once('/websitepartials/lib/websitepartials.lib.php');

/**
 * API class for websitepartials
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Websitepartials extends DolibarrApi
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Health / status
	 *
	 * @return array{module:string,enabled:bool,dolibarr_version:string,website_module:bool,db_ok:bool}
	 *
	 * @url GET status
	 */
	public function status()
	{
		$this->requireModule();
		// Any authenticated API user with the module enabled may hit health.
		// Prefer website/read when granted; otherwise allow if user has any websitepartials right.
		if (!$this->hasAnyModuleRight() && !DolibarrApiAccess::$user->hasRight('websitepartials', 'website', 'read')) {
			throw new RestException(403, 'Missing websitepartials permission');
		}

		$dbOk = false;
		$res = $this->db->query('SELECT 1');
		if ($res) {
			$dbOk = true;
		}

		return array(
			'module' => 'websitepartials',
			'enabled' => isModEnabled('websitepartials'),
			'dolibarr_version' => DOL_VERSION,
			'website_module' => isModEnabled('website'),
			'db_ok' => $dbOk,
		);
	}

	/**
	 * List websites
	 *
	 * @return array<int,array{id:int,ref:string,description:string,status:int,lang:string,otherlang:string,virtualhost:string}>
	 *
	 * @url GET websites
	 *
	 * @throws RestException 403
	 */
	public function getWebsites()
	{
		$this->checkPerm('website', 'read');

		$website = new Website($this->db);
		$records = $website->fetchAll('ASC', 'ref');
		if (!is_array($records) && $records < 0) {
			throw new RestException(500, 'Error fetching websites');
		}

		$out = array();
		if (is_array($records)) {
			foreach ($records as $obj) {
				$out[] = websitepartials_website_to_array($obj);
			}
		}

		return $out;
	}

	/**
	 * Get one website by ref
	 *
	 * @param string $ref Website ref
	 * @return array{id:int,ref:string,description:string,status:int,lang:string,otherlang:string,virtualhost:string}
	 *
	 * @url GET websites/{ref}
	 *
	 * @throws RestException 403
	 * @throws RestException 404
	 */
	public function getWebsite($ref)
	{
		$this->checkPerm('website', 'read');
		$website = $this->requireWebsite($ref);
		return websitepartials_website_to_array($website);
	}

	/**
	 * Create a website (site)
	 *
	 * @param array $request_data Request body
	 * @return array{id:int,ref:string,description:string,status:int,lang:string,otherlang:string,virtualhost:string}
	 *
	 * @url POST websites
	 *
	 * @throws RestException 400
	 * @throws RestException 403
	 * @throws RestException 409
	 * @throws RestException 500
	 */
	public function postWebsite($request_data = null)
	{
		$this->checkPerm('website', 'write');
		$data = $this->normalizeRequest($request_data);

		$ref = websitepartials_normalize_ref($data['ref'] ?? '');
		if ($ref === '') {
			throw new RestException(400, 'Missing ref');
		}
		if (websitepartials_fetch_website($this->db, $ref)) {
			throw new RestException(409, 'Website ref already exists');
		}

		$website = new Website($this->db);
		$website->ref = $ref;
		$website->description = (string) ($data['description'] ?? '');
		$website->lang = (string) ($data['lang'] ?? 'en');
		$website->otherlang = (string) ($data['otherlang'] ?? '');
		$website->virtualhost = (string) ($data['virtualhost'] ?? '');
		$website->status = isset($data['status']) ? (int) $data['status'] : 1;
		$website->position = isset($data['position']) ? (int) $data['position'] : 0;

		$result = $website->create(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to create website: '.($website->error ?: implode('; ', $website->errors)));
		}

		$created = websitepartials_fetch_website($this->db, $ref);
		if (!$created) {
			throw new RestException(500, 'Website created but could not be reloaded');
		}

		return websitepartials_website_to_array($created);
	}

	/**
	 * Update a website by ref
	 *
	 * @param string $ref Website ref
	 * @param array  $request_data Request body
	 * @return array{id:int,ref:string,description:string,status:int,lang:string,otherlang:string,virtualhost:string}
	 *
	 * @url PUT websites/{ref}
	 *
	 * @throws RestException 400
	 * @throws RestException 403
	 * @throws RestException 404
	 * @throws RestException 409
	 * @throws RestException 500
	 */
	public function putWebsite($ref, $request_data = null)
	{
		$this->checkPerm('website', 'write');
		$website = $this->requireWebsite($ref);
		$data = $this->normalizeRequest($request_data);

		if (isset($data['description'])) {
			$website->description = (string) $data['description'];
		}
		if (isset($data['lang'])) {
			$website->lang = (string) $data['lang'];
		}
		if (isset($data['otherlang'])) {
			$website->otherlang = (string) $data['otherlang'];
		}
		if (isset($data['virtualhost'])) {
			$website->virtualhost = (string) $data['virtualhost'];
		}
		if (isset($data['status'])) {
			$website->status = (int) $data['status'];
		}
		if (isset($data['position'])) {
			$website->position = (int) $data['position'];
		}
		if (isset($data['ref'])) {
			$newRef = websitepartials_normalize_ref($data['ref']);
			if ($newRef === '') {
				throw new RestException(400, 'Invalid ref');
			}
			if ($newRef !== $website->ref) {
				if (websitepartials_fetch_website($this->db, $newRef)) {
					throw new RestException(409, 'Website ref already exists');
				}
				$website->ref = $newRef;
			}
		}

		$result = $website->update(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to update website: '.($website->error ?: implode('; ', $website->errors)));
		}

		$updated = websitepartials_fetch_website($this->db, $website->ref);
		if (!$updated) {
			throw new RestException(500, 'Website updated but could not be reloaded');
		}

		return websitepartials_website_to_array($updated);
	}

	/**
	 * Delete a website by ref
	 *
	 * @param string $ref Website ref
	 * @return array{success:bool,ref:string}
	 *
	 * @url DELETE websites/{ref}
	 *
	 * @throws RestException 403
	 * @throws RestException 404
	 * @throws RestException 500
	 */
	public function deleteWebsite($ref)
	{
		$this->checkPerm('website', 'delete');
		$website = $this->requireWebsite($ref);

		$result = $website->delete(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to delete website: '.($website->error ?: implode('; ', $website->errors)));
		}

		return array('success' => true, 'ref' => $ref);
	}

	/**
	 * List containers (pages of any type_container) for a website ref
	 *
	 * @param string $ref    Website ref
	 * @param string $status published|draft|all (default published)
	 * @param string $type   type_container filter (optional; omit = union of types user can read)
	 * @param int    $limit  Max rows (0 = no limit)
	 * @param int    $page   Page index for offset
	 * @return array<int,array{id:int,slug:string,title:string,status:int,type:string,updatedAt:?string}>
	 *
	 * @url GET websites/{ref}/pages
	 *
	 * @throws RestException 400
	 * @throws RestException 403
	 * @throws RestException 404
	 */
	public function getPages($ref, $status = 'published', $type = '', $limit = 100, $page = 0)
	{
		$this->requireModule();
		$website = $this->requireWebsite($ref);

		$type = trim((string) $type);
		$allowedTypes = array();
		if ($type !== '') {
			if (!websitepartials_is_valid_type($type)) {
				throw new RestException(400, 'Invalid type (use one of: '.implode(', ', websitepartials_container_types()).')');
			}
			$this->checkPerm($type, 'read');
			$allowedTypes = array($type);
		} else {
			foreach (websitepartials_container_types() as $t) {
				if (DolibarrApiAccess::$user->hasRight('websitepartials', $t, 'read')) {
					$allowedTypes[] = $t;
				}
			}
			if (empty($allowedTypes)) {
				throw new RestException(403, 'Missing websitepartials/*/read for any container type');
			}
		}

		$filter = array('fk_website' => (int) $website->id);
		$status = strtolower((string) $status);
		if ($status === 'published' || $status === 'online') {
			$filter['status'] = WebsitePage::STATUS_VALIDATED;
		} elseif ($status === 'draft' || $status === 'offline') {
			$filter['status'] = WebsitePage::STATUS_DRAFT;
		} elseif ($status !== 'all') {
			throw new RestException(400, 'Invalid status filter (use published, draft, or all)');
		}

		// When a single type is requested, push it into SQL filter; otherwise filter in PHP.
		if (count($allowedTypes) === 1) {
			$filter['type_container'] = $allowedTypes[0];
		}

		$limit = max(0, (int) $limit);
		$offset = max(0, (int) $page) * ($limit > 0 ? $limit : 0);

		$pageObj = new WebsitePage($this->db);
		$records = $pageObj->fetchAll((int) $website->id, 'ASC', 'pageurl', $limit, $offset, $filter);
		if (!is_array($records) && $records < 0) {
			throw new RestException(500, 'Error fetching pages');
		}

		$out = array();
		if (is_array($records)) {
			foreach ($records as $obj) {
				$t = (string) $obj->type_container;
				if (!in_array($t, $allowedTypes, true)) {
					continue;
				}
				$out[] = websitepartials_page_to_array($obj, false);
			}
		}

		return $out;
	}

	/**
	 * Get one container by slug (includes content; drafts allowed with DOLAPIKEY)
	 *
	 * @param string $ref  Website ref
	 * @param string $slug Page pageurl
	 * @return array{id:int,slug:string,title:string,body:string,status:int,type:string,updatedAt:?string}
	 *
	 * @url GET websites/{ref}/pages/{slug}
	 *
	 * @throws RestException 403
	 * @throws RestException 404
	 */
	public function getPage($ref, $slug)
	{
		$this->requireModule();
		$website = $this->requireWebsite($ref);
		$page = websitepartials_fetch_page($this->db, (int) $website->id, $slug);
		if (!$page) {
			throw new RestException(404, 'Page not found');
		}
		$this->checkPerm((string) $page->type_container, 'read');

		return websitepartials_page_to_array($page, true);
	}

	/**
	 * Create a container on a website
	 *
	 * @param string $ref  Website ref
	 * @param array  $request_data Request body
	 * @return array{id:int,slug:string,title:string,body:string,status:int,type:string,updatedAt:?string}
	 *
	 * @url POST websites/{ref}/pages
	 *
	 * @throws RestException 400
	 * @throws RestException 403
	 * @throws RestException 404
	 * @throws RestException 409
	 * @throws RestException 500
	 */
	public function postPage($ref, $request_data = null)
	{
		$this->requireModule();
		$website = $this->requireWebsite($ref);
		$data = $this->normalizeRequest($request_data);

		$type = (string) ($data['type'] ?? ($data['type_container'] ?? 'page'));
		if (!websitepartials_is_valid_type($type)) {
			throw new RestException(400, 'Invalid type (use one of: '.implode(', ', websitepartials_container_types()).')');
		}
		$this->checkPerm($type, 'write');

		$slug = websitepartials_normalize_slug($data['slug'] ?? ($data['pageurl'] ?? ''));
		if ($slug === '') {
			throw new RestException(400, 'Missing slug (pageurl)');
		}
		if (strlen($slug) > 16) {
			throw new RestException(400, 'slug must be at most 16 characters (Dolibarr pageurl limit)');
		}

		$existing = websitepartials_fetch_page($this->db, (int) $website->id, $slug);
		if ($existing) {
			throw new RestException(409, 'Page slug already exists');
		}

		$page = new WebsitePage($this->db);
		$page->fk_website = (int) $website->id;
		$page->pageurl = $slug;
		$page->title = (string) ($data['title'] ?? $slug);
		$page->description = (string) ($data['description'] ?? '');
		$page->content = (string) ($data['body'] ?? ($data['content'] ?? ''));
		$page->type_container = $type;
		$page->lang = (string) ($data['lang'] ?? ($website->lang ?: 'en'));
		$page->keywords = (string) ($data['keywords'] ?? '');
		$page->htmlheader = (string) ($data['htmlheader'] ?? '');
		$page->author_alias = (string) ($data['author_alias'] ?? '');
		$page->aliasalt = (string) ($data['aliasalt'] ?? '');
		$page->image = (string) ($data['image'] ?? '');
		$page->allowed_in_frames = isset($data['allowed_in_frames']) ? (int) $data['allowed_in_frames'] : 0;
		$page->status = $this->parseStatus($data['status'] ?? 'draft');

		$result = $page->create(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to create page: '.($page->error ?: implode('; ', $page->errors)));
		}

		$created = websitepartials_fetch_page($this->db, (int) $website->id, $slug);
		if (!$created) {
			throw new RestException(500, 'Page created but could not be reloaded');
		}

		return websitepartials_page_to_array($created, true);
	}

	/**
	 * Update an existing container by slug
	 *
	 * @param string $ref  Website ref
	 * @param string $slug Page pageurl
	 * @param array  $request_data Request body
	 * @return array{id:int,slug:string,title:string,body:string,status:int,type:string,updatedAt:?string}
	 *
	 * @url PUT websites/{ref}/pages/{slug}
	 *
	 * @throws RestException 400
	 * @throws RestException 403
	 * @throws RestException 404
	 * @throws RestException 500
	 */
	public function putPage($ref, $slug, $request_data = null)
	{
		$this->requireModule();
		$website = $this->requireWebsite($ref);
		$page = websitepartials_fetch_page($this->db, (int) $website->id, $slug);
		if (!$page) {
			throw new RestException(404, 'Page not found');
		}

		$oldType = (string) $page->type_container;
		$this->checkPerm($oldType, 'write');

		$data = $this->normalizeRequest($request_data);

		if (isset($data['title'])) {
			$page->title = (string) $data['title'];
		}
		if (isset($data['description'])) {
			$page->description = (string) $data['description'];
		}
		if (array_key_exists('body', $data) || array_key_exists('content', $data)) {
			$page->content = (string) ($data['body'] ?? $data['content']);
		}
		if (isset($data['type']) || isset($data['type_container'])) {
			$newType = (string) ($data['type'] ?? $data['type_container']);
			if (!websitepartials_is_valid_type($newType)) {
				throw new RestException(400, 'Invalid type (use one of: '.implode(', ', websitepartials_container_types()).')');
			}
			if ($newType !== $oldType) {
				$this->checkPerm($newType, 'write');
			}
			$page->type_container = $newType;
		}
		if (isset($data['lang'])) {
			$page->lang = (string) $data['lang'];
		}
		if (isset($data['keywords'])) {
			$page->keywords = (string) $data['keywords'];
		}
		if (isset($data['htmlheader'])) {
			$page->htmlheader = (string) $data['htmlheader'];
		}
		if (isset($data['status'])) {
			$page->status = $this->parseStatus($data['status']);
		}
		if (isset($data['slug']) || isset($data['pageurl'])) {
			$newSlug = websitepartials_normalize_slug($data['slug'] ?? $data['pageurl']);
			if ($newSlug === '') {
				throw new RestException(400, 'Invalid slug');
			}
			if ($newSlug !== $page->pageurl) {
				$conflict = websitepartials_fetch_page($this->db, (int) $website->id, $newSlug);
				if ($conflict) {
					throw new RestException(409, 'Page slug already exists');
				}
				$page->pageurl = $newSlug;
			}
		}

		$result = $page->update(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to update page: '.($page->error ?: implode('; ', $page->errors)));
		}

		$updated = websitepartials_fetch_page($this->db, (int) $website->id, $page->pageurl);
		if (!$updated) {
			throw new RestException(500, 'Page updated but could not be reloaded');
		}

		return websitepartials_page_to_array($updated, true);
	}

	/**
	 * Delete a container by slug
	 *
	 * @param string $ref  Website ref
	 * @param string $slug Page pageurl
	 * @return array{success:bool,ref:string,slug:string}
	 *
	 * @url DELETE websites/{ref}/pages/{slug}
	 *
	 * @throws RestException 403
	 * @throws RestException 404
	 * @throws RestException 500
	 */
	public function deletePage($ref, $slug)
	{
		$this->requireModule();
		$website = $this->requireWebsite($ref);
		$page = websitepartials_fetch_page($this->db, (int) $website->id, $slug);
		if (!$page) {
			throw new RestException(404, 'Page not found');
		}
		$this->checkPerm((string) $page->type_container, 'delete');

		$result = $page->delete(DolibarrApiAccess::$user);
		if ($result <= 0) {
			throw new RestException(500, 'Failed to delete page: '.($page->error ?: implode('; ', $page->errors)));
		}

		return array('success' => true, 'ref' => $ref, 'slug' => $slug);
	}

	/**
	 * Ensure Website + websitepartials modules are enabled.
	 *
	 * @return void
	 * @throws RestException 503
	 */
	private function requireModule()
	{
		if (!isModEnabled('website')) {
			throw new RestException(503, 'Website module is not enabled');
		}
		if (!isModEnabled('websitepartials')) {
			throw new RestException(503, 'websitepartials module is not enabled');
		}
	}

	/**
	 * Check module-owned nested permission.
	 *
	 * @param string $object Object key (website, page, blogpost, …)
	 * @param string $action Action (read, write, delete)
	 * @return void
	 * @throws RestException 403
	 * @throws RestException 503
	 */
	private function checkPerm($object, $action)
	{
		$this->requireModule();
		if (!DolibarrApiAccess::$user->hasRight('websitepartials', $object, $action)) {
			throw new RestException(403, 'Missing websitepartials/'.$object.'/'.$action);
		}
	}

	/**
	 * @return bool
	 */
	private function hasAnyModuleRight()
	{
		$user = DolibarrApiAccess::$user;
		if ($user->admin) {
			return true;
		}
		$objects = array_merge(array('website'), websitepartials_container_types());
		foreach ($objects as $object) {
			foreach (array('read', 'write', 'delete') as $action) {
				if ($user->hasRight('websitepartials', $object, $action)) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param string $ref
	 * @return Website
	 * @throws RestException 400
	 * @throws RestException 404
	 */
	private function requireWebsite($ref)
	{
		$ref = trim((string) $ref);
		if ($ref === '') {
			throw new RestException(400, 'Missing website ref');
		}
		$website = websitepartials_fetch_website($this->db, $ref);
		if (!$website) {
			throw new RestException(404, 'Website not found');
		}
		return $website;
	}

	/**
	 * @param mixed $request_data
	 * @return array<string,mixed>
	 */
	private function normalizeRequest($request_data)
	{
		if ($request_data === null) {
			return array();
		}
		if (is_object($request_data)) {
			$request_data = (array) $request_data;
		}
		if (!is_array($request_data)) {
			throw new RestException(400, 'Invalid JSON body');
		}
		return $request_data;
	}

	/**
	 * @param mixed $status
	 * @return int
	 * @throws RestException 400
	 */
	private function parseStatus($status)
	{
		if (is_int($status) || (is_string($status) && ctype_digit($status))) {
			$n = (int) $status;
			if ($n === WebsitePage::STATUS_DRAFT || $n === WebsitePage::STATUS_VALIDATED) {
				return $n;
			}
		}

		$s = strtolower((string) $status);
		if (in_array($s, array('draft', 'offline', 'unpublished'), true)) {
			return WebsitePage::STATUS_DRAFT;
		}
		if (in_array($s, array('published', 'online', 'validated'), true)) {
			return WebsitePage::STATUS_VALIDATED;
		}

		throw new RestException(400, 'Invalid status (use draft/published or 0/1)');
	}
}
