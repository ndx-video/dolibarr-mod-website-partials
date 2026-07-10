#!/usr/bin/env php
<?php
/**
 * Activate a Dolibarr module via activateModule() so init() / SQL tables run.
 * Usage (inside container): php activate-module.php modWebsite
 */
if ($argc < 2) {
	fwrite(STDERR, "Usage: activate-module.php <modClassName>\n");
	exit(1);
}

$mod = $argv[1];

define('NOLOGIN', 1);
define('NOCSRFCHECK', 1);
define('NOTOKENRENEWAL', 1);
define('NOREQUIREMENU', 1);
define('NOREQUIREHTML', 1);
define('NOREQUIREAJAX', 1);

require '/var/www/html/master.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$r = activateModule($mod, 1, 1);
$errors = $r['errors'] ?? array();
$fatal = array();
foreach ($errors as $e) {
	// Re-running up.sh may hit "menu already exists" — treat as non-fatal.
	if (stripos($e, 'already exists') === false) {
		$fatal[] = $e;
	}
}

if (!empty($fatal)) {
	fwrite(STDERR, "activateModule {$mod} failed: ".implode('; ', $fatal)."\n");
	exit(1);
}

foreach ($errors as $e) {
	fwrite(STDERR, "activateModule {$mod} warning: {$e}\n");
}

echo "activated {$mod} (nbmodules=".($r['nbmodules'] ?? 0).", nbperms=".($r['nbperms'] ?? 0).")\n";
exit(0);
