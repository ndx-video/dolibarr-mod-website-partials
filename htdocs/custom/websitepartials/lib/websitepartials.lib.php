<?php
/* Copyright (C) 2026	Bray Park SDA Church / ndx-video
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/websitepartials/lib/websitepartials.lib.php
 * \ingroup websitepartials
 * \brief   Shared helpers to resolve Website CMS refs and pages.
 */

// Website::create/delete need these helpers (same includes as website/index.php).
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/website.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/website2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/website/class/website.class.php';
require_once DOL_DOCUMENT_ROOT.'/website/class/websitepage.class.php';

/**
 * Known Website container type_container codes (from llx_c_type_container).
 *
 * @return string[]
 */
function websitepartials_container_types()
{
	return array('page', 'blogpost', 'menu', 'banner', 'other', 'service', 'library', 'setup');
}

/**
 * Validate a type_container code.
 *
 * @param string $type Type code
 * @return bool
 */
function websitepartials_is_valid_type($type)
{
	return in_array((string) $type, websitepartials_container_types(), true);
}

/**
 * Normalize a website ref (alphanumeric, dash, underscore).
 *
 * @param string $ref Raw ref
 * @return string
 */
function websitepartials_normalize_ref($ref)
{
	$ref = preg_replace('/[^a-z0-9\-\_]/i', '', (string) $ref);
	$ref = preg_replace('/\-\-+/', '-', $ref);
	$ref = preg_replace('/^\-/', '', $ref);
	return (string) $ref;
}

/**
 * Fetch a Website by ref (e.g. testsite, main-website).
 *
 * @param DoliDB $db  Database handler
 * @param string $ref Website ref
 * @return Website|null
 */
function websitepartials_fetch_website(DoliDB $db, $ref)
{
	$ref = trim((string) $ref);
	if ($ref === '') {
		return null;
	}

	$website = new Website($db);
	$result = $website->fetch(0, $ref);
	if ($result <= 0) {
		return null;
	}

	return $website;
}

/**
 * Fetch a WebsitePage by website id + pageurl (slug).
 *
 * @param DoliDB $db         Database handler
 * @param int    $website_id Website rowid
 * @param string $slug       pageurl
 * @return WebsitePage|null
 */
function websitepartials_fetch_page(DoliDB $db, $website_id, $slug)
{
	$slug = trim((string) $slug);
	if ($website_id <= 0 || $slug === '') {
		return null;
	}

	$page = new WebsitePage($db);
	$result = $page->fetch(0, (string) $website_id, $slug);
	if ($result <= 0) {
		return null;
	}

	return $page;
}

/**
 * Normalize a public/API slug (pageurl).
 *
 * @param string $slug Raw slug
 * @return string
 */
function websitepartials_normalize_slug($slug)
{
	$slug = preg_replace('/[^a-z0-9\-\_]/i', '', (string) $slug);
	$slug = preg_replace('/\-\-+/', '-', $slug);
	$slug = preg_replace('/^\-/', '', $slug);
	return (string) $slug;
}

/**
 * Map a WebsitePage to the public JSON island shape (+ id/status for REST).
 *
 * @param WebsitePage $page Page object
 * @param bool        $includeContent Include HTML body
 * @return array{id:int,slug:string,title:string,body?:string,status:int,updatedAt:?string,type:string,description?:string,lang?:string}
 */
function websitepartials_page_to_array(WebsitePage $page, $includeContent = true)
{
	$updated = null;
	if (!empty($page->date_modification)) {
		$updated = dol_print_date($page->date_modification, 'dayhourrfc');
	} elseif (!empty($page->tms)) {
		$updated = dol_print_date($page->tms, 'dayhourrfc');
	}

	$out = array(
		'id' => (int) $page->id,
		'slug' => (string) $page->pageurl,
		'title' => (string) $page->title,
		'status' => (int) $page->status,
		'type' => (string) $page->type_container,
		'description' => (string) $page->description,
		'lang' => (string) $page->lang,
		'updatedAt' => $updated,
	);

	if ($includeContent) {
		$out['body'] = (string) $page->content;
	}

	return $out;
}

/**
 * Map a WebsitePage to the public JSON island contract (no id/status/type).
 *
 * @param WebsitePage $page Page object
 * @return array{slug:string,title:string,body:string,updatedAt:?string}
 */
function websitepartials_page_to_public_array(WebsitePage $page)
{
	$full = websitepartials_page_to_array($page, true);

	return array(
		'slug' => $full['slug'],
		'title' => $full['title'],
		'body' => isset($full['body']) ? (string) $full['body'] : '',
		'updatedAt' => $full['updatedAt'],
	);
}

/**
 * Map a Website to a REST array.
 *
 * @param Website $website Website object
 * @return array{id:int,ref:string,description:string,status:int,lang:string,otherlang:string,virtualhost:string}
 */
function websitepartials_website_to_array(Website $website)
{
	return array(
		'id' => (int) $website->id,
		'ref' => (string) $website->ref,
		'description' => (string) $website->description,
		'status' => (int) $website->status,
		'lang' => (string) $website->lang,
		'otherlang' => (string) $website->otherlang,
		'virtualhost' => (string) $website->virtualhost,
	);
}

/**
 * Prepare admin pages header tabs.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function websitepartialsAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load('websitepartials@websitepartials');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath('/websitepartials/admin/setup.php', 1);
	$head[$h][1] = $langs->trans('Settings');
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'websitepartials@websitepartials');

	return $head;
}

/**
 * Default Website CMS ref for public islands / aliases.
 *
 * @return string
 */
function websitepartials_default_website_ref()
{
	$ref = getDolGlobalString('WEBSITEPARTIALS_DEFAULT_WEBSITE_REF', 'main-website');
	$ref = websitepartials_normalize_ref($ref);
	return $ref !== '' ? $ref : 'main-website';
}

/**
 * Cache-Control value for successful public responses.
 *
 * @return string
 */
function websitepartials_cache_control()
{
	$v = trim(getDolGlobalString('WEBSITEPARTIALS_CACHE_CONTROL', 'public, max-age=60, stale-while-revalidate=300'));
	return $v !== '' ? $v : 'public, max-age=60, stale-while-revalidate=300';
}

/**
 * Consumer URLs from setup (convenience jump links only).
 *
 * @return string[]
 */
function websitepartials_consumer_urls()
{
	$raw = getDolGlobalString('WEBSITEPARTIALS_CONSUMER_URLS', '');
	$lines = preg_split('/\r\n|\r|\n/', (string) $raw);
	$out = array();
	if (!is_array($lines)) {
		return $out;
	}
	foreach ($lines as $line) {
		$url = trim($line);
		if ($url === '') {
			continue;
		}
		if (!preg_match('#^https?://#i', $url)) {
			continue;
		}
		$out[] = $url;
	}
	return $out;
}

/**
 * Public-path IP allowlist raw string from setup.
 *
 * @return string
 */
function websitepartials_public_allowed_ips_raw()
{
	return (string) getDolGlobalString('WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS', '');
}

/**
 * Whether a client IP is allowed against a space/newline-separated list of IPs and CIDRs.
 * Empty list means allow all.
 *
 * @param string $ip   Client IP (from getUserRemoteIP())
 * @param string $list Allowlist text
 * @return bool
 */
function websitepartials_ip_allowed($ip, $list = null)
{
	if ($list === null) {
		$list = websitepartials_public_allowed_ips_raw();
	}
	$list = trim((string) $list);
	if ($list === '') {
		return true;
	}

	$ip = trim((string) $ip);
	if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
		return false;
	}

	$tokens = preg_split('/[\s,;]+/', $list);
	if (!is_array($tokens)) {
		return false;
	}

	foreach ($tokens as $token) {
		$token = trim($token);
		if ($token === '') {
			continue;
		}
		if (strpos($token, '/') !== false) {
			if (websitepartials_ip_in_cidr($ip, $token)) {
				return true;
			}
			continue;
		}
		if (strcasecmp($ip, $token) === 0) {
			return true;
		}
	}

	return false;
}

/**
 * Check if an IP is inside a CIDR network (IPv4 or IPv6).
 *
 * @param string $ip   IP address
 * @param string $cidr CIDR notation
 * @return bool
 */
function websitepartials_ip_in_cidr($ip, $cidr)
{
	$parts = explode('/', $cidr, 2);
	if (count($parts) !== 2) {
		return false;
	}
	$subnet = trim($parts[0]);
	$prefix = (int) $parts[1];

	$ipBin = @inet_pton($ip);
	$subnetBin = @inet_pton($subnet);
	if ($ipBin === false || $subnetBin === false) {
		return false;
	}
	if (strlen($ipBin) !== strlen($subnetBin)) {
		return false;
	}

	$maxBits = strlen($ipBin) * 8;
	if ($prefix < 0 || $prefix > $maxBits) {
		return false;
	}
	if ($prefix === 0) {
		return true;
	}

	$bytes = intdiv($prefix, 8);
	$bits = $prefix % 8;

	if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
		return false;
	}
	if ($bits === 0) {
		return true;
	}

	$mask = (~((1 << (8 - $bits)) - 1)) & 0xFF;
	return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}
