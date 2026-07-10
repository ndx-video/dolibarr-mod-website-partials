<?php
/* Copyright (C) 2026	Bray Park SDA Church / ndx-video
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   websitepartials     Module WebsitePartials
 *  \brief      WebsitePartials module descriptor — publish Website CMS containers as HTTPS content islands.
 *
 *  \file       htdocs/custom/websitepartials/core/modules/modWebsitePartials.class.php
 *  \ingroup    websitepartials
 *  \brief      Description and activation file for module WebsitePartials
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module WebsitePartials
 */
class modWebsitePartials extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;

		$this->db = $db;

		// Id for module (must be unique).
		// TODO: Reserve an id at https://wiki.dolibarr.org/index.php/List_of_modules_id
		$this->numero = 500000;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'websitepartials';

		// Family: interface = link with external tools
		$this->family = "interface";

		// Module position in the family on 2 digits
		$this->module_position = '90';

		// Module label (no space allowed), used if translation string 'ModuleWebsitePartialsName' not found
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description
		$this->description = "WebsitePartialsDescription";
		$this->descriptionlong = "WebsitePartialsDescription";

		// Author
		$this->editor_name = 'Bray Park SDA Church / ndx-video';
		$this->editor_url = 'https://braypark.church';
		$this->editor_squarred_logo = '';

		// development | experimental | x.y.z — development needs MAIN_FEATURES_LEVEL >= 2
		$this->version = 'development';

		// Key used in llx_const table to save module status enabled/disabled
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'generic';

		// Features supported by module (none for P0 scaffold)
		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0,
		);

		// Data directories to create when module is enabled
		$this->dirs = array("/websitepartials/temp");

		// Admin setup page
		$this->config_page_url = array('setup.php@websitepartials');

		// Dependencies
		$this->hidden = getDolGlobalInt('MODULE_WEBSITEPARTIALS_DISABLED');
		$this->depends = array('modWebsite');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("websitepartials@websitepartials");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(22, 0);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Default consts created on enable (name, type, value, desc, visible, entity)
		$this->const = array(
			1 => array('WEBSITEPARTIALS_DEFAULT_WEBSITE_REF', 'chaine', 'main-website', 'Default Website CMS ref for public islands', 0),
			2 => array('WEBSITEPARTIALS_CACHE_CONTROL', 'chaine', 'public, max-age=60, stale-while-revalidate=300', 'Cache-Control for successful public partial responses', 0),
			3 => array('WEBSITEPARTIALS_PUBLIC_ALLOWED_IPS', 'chaine', '', 'Public path IP/CIDR allowlist (empty = allow all)', 0),
			4 => array('WEBSITEPARTIALS_CONSUMER_URLS', 'chaine', '', 'Consumer site URLs (one per line) for setup jump links', 0),
		);

		if (!isModEnabled("websitepartials")) {
			$conf->websitepartials = new stdClass();
			$conf->websitepartials->enabled = 0;
		}

		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes / widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions — nested toggles: websitepartials/{object}/{action}
		// Objects: website (sites) + each Website type_container code.
		$this->rights = array();
		$r = 0;
		$objects = array(
			'website' => 'websites (sites)',
			'page' => 'page containers',
			'blogpost' => 'blogpost containers',
			'menu' => 'menu containers',
			'banner' => 'banner containers',
			'other' => 'other containers',
			'service' => 'service containers',
			'library' => 'library containers',
			'setup' => 'setup containers',
		);
		$actions = array(
			'read' => 'Read',
			'write' => 'Create/modify',
			'delete' => 'Delete',
		);
		$offset = 10;
		foreach ($objects as $objectKey => $objectLabel) {
			foreach ($actions as $actionKey => $actionLabel) {
				$this->rights[$r][0] = $this->numero + $offset;
				$this->rights[$r][1] = $actionLabel.' '.$objectLabel;
				$this->rights[$r][3] = 0;
				$this->rights[$r][4] = $objectKey;
				$this->rights[$r][5] = $actionKey;
				$r++;
				$offset += 1;
			}
		}

		// Main menu entries — none (API-only module)
		$this->menu = array();

		// Exports / imports — none
		$this->export_code = array();
		$this->import_code = array();
	}

	/**
	 *  Function called when module is enabled.
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		// No SQL tables for this module (reads llx_website / llx_website_page only).
		// Do not call DolibarrModules table loader — there is no sql/ directory.

		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *	Function called when module is disabled.
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
