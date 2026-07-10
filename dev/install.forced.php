<?php
/* Forced install profile for PostgreSQL (module-dev stack). */

$force_install_nophpinfo = true;
$force_install_noedit = 2;
$force_install_message = 'Dolibarr PostgreSQL install (website-partials module-dev)';

$force_install_main_data_root = '/var/www/documents';
$force_install_mainforcehttps = false;

$force_install_database = 'dolibarr';
$force_install_type = 'pgsql';
$force_install_dbserver = 'db';
$force_install_port = 5432;
$force_install_prefix = 'llx_';

$force_install_createdatabase = false;
$force_install_createuser = false;

$force_install_databaselogin = 'dolibarr_user';
$force_install_databasepass = 'changeme_database_app_pass';
$force_install_databaserootlogin = 'dolibarr_user';
$force_install_databaserootpass = 'changeme_database_app_pass';

$force_install_dolibarrlogin = 'admin';
$force_install_lockinstall = true;
$force_install_module = 'modApi';
