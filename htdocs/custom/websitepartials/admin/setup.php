<?php
/* Copyright (C) 2026	Bray Park SDA Church / ndx-video
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/websitepartials/admin/setup.php
 * \ingroup websitepartials
 * \brief   Website Partials module setup page.
 */

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
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
dol_include_once('/websitepartials/lib/websitepartials.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'websitepartials@websitepartials'));

if (!$user->admin) {
	accessforbidden();
}
if (!isModEnabled('websitepartials')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');

$formSetup = new FormSetup($db);

// --- Access / IP ---
$formSetup->newItem('WebsitePartialsSectionAccess')->setAsTitle();

$apiIpNote = $langs->trans('WEBSITEPARTIALS_API_IP_NOTE');
$apiSetupUrl = DOL_URL_ROOT.'/api/admin/index.php';
$apiIpNote .= ' <a href="'.dol_escape_htmltag($apiSetupUrl).'">'.$langs->trans('WEBSITEPARTIALS_API_IP_LINK').'</a>';
$apiIpCurrent = getDolGlobalString('API_RESTRICT_ON_IP');
if ($apiIpCurrent !== '') {
	$apiIpNote .= '<br><span class="opacitymedium">'.$langs->trans('WEBSITEPARTIALS_API_IP_CURRENT').': '.dol_escape_htmltag($apiIpCurrent).'</span>';
} else {
	$apiIpNote .= '<br><span class="opacitymedium">'.$langs->trans('WEBSITEPARTIALS_API_IP_CURRENT_EMPTY').'</span>';
}
$item = $formSetup->newItem('WEBSITEPARTIALS_API_IP_INFO');
$item->fieldOverride = $apiIpNote;
$item->nameText = $langs->trans('WEBSITEPARTIALS_API_IP_INFO');

$item = $formSetup->newItem('WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS');
$item->setAsTextarea();
$item->cssClass = 'minwidth500';
$item->helpText = $langs->trans('WEBSITEPARTIALS_PUBLIC_ALLOWED_IPSTooltip');

// --- Public defaults ---
$formSetup->newItem('WebsitePartialsSectionPublic')->setAsTitle();

$item = $formSetup->newItem('WEBSITEPARTIALS_DEFAULT_WEBSITE_REF');
$item->defaultFieldValue = 'main-website';
$item->cssClass = 'minwidth300';
$item->helpText = $langs->trans('WEBSITEPARTIALS_DEFAULT_WEBSITE_REFTooltip');

$item = $formSetup->newItem('WEBSITEPARTIALS_CACHE_CONTROL');
$item->defaultFieldValue = 'public, max-age=60, stale-while-revalidate=300';
$item->cssClass = 'minwidth500';
$item->helpText = $langs->trans('WEBSITEPARTIALS_CACHE_CONTROLTooltip');

// --- Consumers (convenience) ---
$formSetup->newItem('WebsitePartialsSectionConsumers')->setAsTitle();

$item = $formSetup->newItem('WEBSITEPARTIALS_CONSUMER_URLS');
$item->setAsTextarea();
$item->cssClass = 'minwidth500';
$item->helpText = $langs->trans('WEBSITEPARTIALS_CONSUMER_URLSTooltip');

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

$page_name = 'WebsitePartialsSetup';
llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-websitepartials page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = websitepartialsAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'generic');

print '<span class="opacitymedium">'.$langs->trans('WebsitePartialsSetupPage').'</span><br><br>';

if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

$consumers = websitepartials_consumer_urls();
if (!empty($consumers)) {
	print load_fiche_titre($langs->trans('WEBSITEPARTIALS_CONSUMER_JUMPS'), '', '');
	print '<ul class="liste_titre" style="list-style: disc; margin-left: 1.5em;">';
	foreach ($consumers as $url) {
		print '<li><a href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag($url).'</a></li>';
	}
	print '</ul><br>';
}

print dol_get_fiche_end();
llxFooter();
$db->close();
