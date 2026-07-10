#!/usr/bin/env php
<?php
/**
 * Minimal company constants so Dolibarr stops redirecting to setupnotcomplete.
 * Usage (inside container): php setup-company.php [Name] [CountryCode]
 */
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/ccountry.class.php';

$name = $argv[1] ?? getenv('DOLI_COMPANY_NAME') ?: 'Bray Park Partials Dev';
$countryCode = $argv[2] ?? getenv('DOLI_COMPANY_COUNTRYCODE') ?: 'AU';

if (!getDolGlobalString('MAIN_INFO_SOCIETE_NOM')) {
	dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_NOM', $name, 'chaine', 0, '', $conf->entity);
	echo "set MAIN_INFO_SOCIETE_NOM={$name}\n";
} else {
	echo "MAIN_INFO_SOCIETE_NOM already set\n";
}

if (!getDolGlobalString('MAIN_INFO_SOCIETE_COUNTRY')) {
	$country = new Ccountry($db);
	$res = $country->fetch(0, $countryCode);
	if ($res > 0) {
		$s = $country->id.':'.$country->code.':'.$country->label;
		dolibarr_set_const($db, 'MAIN_INFO_SOCIETE_COUNTRY', $s, 'chaine', 0, '', $conf->entity);
		echo "set MAIN_INFO_SOCIETE_COUNTRY={$s}\n";
	} else {
		fwrite(STDERR, "Unable to find country code {$countryCode}\n");
		exit(1);
	}
} else {
	echo "MAIN_INFO_SOCIETE_COUNTRY already set\n";
}

exit(0);
