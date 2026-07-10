<?php
/**
 * Audit enabled modules for incomplete activation (menus/rights/SQL).
 * Run inside partials-dolibarr.
 */
define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);
require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

$res = $db->query("SELECT name FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'MAIN_MODULE_%' AND value = '1' ORDER BY name");
$enabled = array();
while ($res && ($obj = $db->fetch_object($res))) {
	$key = strtolower(preg_replace('/^MAIN_MODULE_/', '', $obj->name));
	$enabled[$key] = $obj->name;
}

// Map const key → mod class (special cases)
$classMap = array(
	'api' => 'modApi',
	'fckeditor' => 'modFckeditor',
	'modulebuilder' => 'modModuleBuilder',
	'debugbar' => 'modDebugBar',
	'websitepartials' => 'modWebsitePartials',
);

$modulesdir = dolGetModulesDirs();

printf("%-18s %-22s %5s %5s %8s %8s %7s %s\n",
	'const_key', 'class', 'mDecl', 'rDecl', 'dbMenus', 'dbRights', 'SQL?', 'notes');

$needsFix = array();

foreach ($enabled as $key => $constName) {
	$modName = $classMap[$key] ?? ('mod'.str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
	// Try common variants
	$candidates = array_unique(array(
		$modName,
		'mod'.ucfirst($key),
		'mod'.strtoupper($key),
	));

	$obj = null;
	$file = null;
	foreach ($candidates as $cand) {
		foreach ($modulesdir as $dir) {
			$f = $dir.$cand.'.class.php';
			if (file_exists($f)) {
				require_once $f;
				$obj = new $cand($db);
				$file = $f;
				$modName = $cand;
				break 2;
			}
		}
	}

	if (!$obj) {
		printf("%-18s %-22s %s\n", $key, '?', 'DESCRIPTOR NOT FOUND');
		$needsFix[] = array($key, 'missing_descriptor');
		continue;
	}

	$menuDecl = is_array($obj->menu) ? count($obj->menu) : 0;
	$rightsDecl = 0;
	if (is_array($obj->rights)) {
		foreach ($obj->rights as $r) {
			if (!empty($r)) {
				$rightsDecl++;
			}
		}
	}
	$src = file_get_contents($file);
	$hasSql = (bool) preg_match('/\$this->_load_tables\s*\(/', $src);

	$resM = $db->query("SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."menu WHERE module = '".$db->escape($key)."'");
	$dbMenus = $resM ? (int) $db->fetch_object($resM)->c : -1;

	$rightsClass = $obj->rights_class ?: $key;
	$resR = $db->query("SELECT COUNT(*) as c FROM ".MAIN_DB_PREFIX."rights_def WHERE module = '".$db->escape($rightsClass)."' OR module = '".$db->escape($key)."'");
	$dbRights = $resR ? (int) $db->fetch_object($resR)->c : -1;

	$notes = array();
	if ($menuDecl > 0 && $dbMenus === 0) {
		$notes[] = 'MENUS_MISSING';
		$needsFix[] = array($modName, 'menus');
	}
	if ($rightsDecl > 0 && $dbRights === 0) {
		$notes[] = 'RIGHTS_MISSING';
		$needsFix[] = array($modName, 'rights');
	}
	if ($hasSql) {
		$notes[] = 'has_load_tables';
	}

	printf("%-18s %-22s %5d %5d %8d %8d %7s %s\n",
		$key, $modName, $menuDecl, $rightsDecl, $dbMenus, $dbRights,
		$hasSql ? 'yes' : 'no',
		$notes ? implode(',', $notes) : 'ok');
}

echo "\n--- modules needing activateModule re-run ---\n";
$uniq = array();
foreach ($needsFix as $pair) {
	$uniq[$pair[0]] = true;
}
foreach (array_keys($uniq) as $m) {
	echo $m."\n";
}
