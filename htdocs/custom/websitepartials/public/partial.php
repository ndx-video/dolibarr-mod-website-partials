<?php
/* Copyright (C) 2026	Bray Park SDA Church / ndx-video
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/websitepartials/public/partial.php
 * \ingroup websitepartials
 * \brief   Public front-controller for published HTML/JSON content islands.
 *
 * Canonical (via Caddy rewrite):
 *   GET /custom/websitepartials/public/partials/{website_ref}/{slug}.html
 *   GET /custom/websitepartials/public/partials/{website_ref}/{slug}.json
 *
 * Direct PATH_INFO (no rewrite):
 *   GET /custom/websitepartials/public/partial.php/partials/{website_ref}/{slug}.json
 */

if (!defined('NOLOGIN')) {
	define('NOLOGIN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOIPCHECK')) {
	define('NOIPCHECK', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = @include '../../../../main.inc.php';
}
if (!$res) {
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Include of main fails';
	exit;
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 */

dol_include_once('/websitepartials/lib/websitepartials.lib.php');

/**
 * Emit a plain-text error and exit.
 *
 * @param int    $code HTTP status
 * @param string $msg  Body text
 * @return void
 */
function websitepartials_public_error($code, $msg)
{
	http_response_code((int) $code);
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: no-store');
	echo $msg;
	exit;
}

if (!isModEnabled('websitepartials') || !isModEnabled('website')) {
	websitepartials_public_error(503, 'Module not available');
}

$clientIp = function_exists('getUserRemoteIP') ? getUserRemoteIP() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
if (!websitepartials_ip_allowed($clientIp)) {
	websitepartials_public_error(403, 'Forbidden');
}

$path = '';
if (!empty($_SERVER['PATH_INFO'])) {
	$path = (string) $_SERVER['PATH_INFO'];
} elseif (!empty($_SERVER['ORIG_PATH_INFO'])) {
	$path = (string) $_SERVER['ORIG_PATH_INFO'];
} else {
	$uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$uri = strtok($uri, '?');
	if (preg_match('#/partial\.php(/.*)$#', $uri, $m)) {
		$path = $m[1];
	} elseif (preg_match('#(/partials/.+)$#', $uri, $m)) {
		$path = $m[1];
	}
}

if (!preg_match('#(?:^|/)partials/([a-z0-9\-_]+)/([a-z0-9\-_]+)\.(html|json)$#i', $path, $m)) {
	websitepartials_public_error(400, 'Malformed partial path');
}

$websiteRef = websitepartials_normalize_ref($m[1]);
$slug = websitepartials_normalize_slug($m[2]);
$format = strtolower($m[3]);

if ($websiteRef === '' || $slug === '' || $websiteRef !== $m[1] || $slug !== $m[2]) {
	websitepartials_public_error(400, 'Malformed website ref or slug');
}

$website = websitepartials_fetch_website($db, $websiteRef);
if (!$website) {
	websitepartials_public_error(404, 'Not found');
}

$page = websitepartials_fetch_page($db, (int) $website->id, $slug);
if (!$page || (int) $page->status !== WebsitePage::STATUS_VALIDATED) {
	websitepartials_public_error(404, 'Not found');
}

$cacheControl = websitepartials_cache_control();

if ($format === 'html') {
	http_response_code(200);
	header('Content-Type: text/html; charset=utf-8');
	header('Cache-Control: '.$cacheControl);
	echo (string) $page->content;
	exit;
}

$payload = websitepartials_page_to_public_array($page);
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: '.$cacheControl);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
