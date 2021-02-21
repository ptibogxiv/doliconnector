<?php
/* Copyright (C) 2017-2019 	PtibogXIV        <support@ptibogxiv.net>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \defgroup Module Doliconnector
 * \brief doliconnector module descriptor.
 * \file /core/modules/moddoliconnector.class.php
 * \ingroup doliconnector
 * \brief Description and activation file for module doliconnector
 */
include_once DOL_DOCUMENT_ROOT . "/core/modules/DolibarrModules.class.php";

/**
 * \class modAgefodd
 * \brief Description and activation class for module agefodd
 */
class modDoliconnector extends DolibarrModules {
	var $error;
	/**
	 * Constructor.
	 *
	 * @param DoliDB		Database handler
	 */
	function __construct($db) {
		global $langs,$conf;
		
		$this->db = $db;
		
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 431310;
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'doliconnector';
		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "interface";
    // Can be enabled / disabled only in the main company with superadmin account
		$this->core_enabled = 1;
		// Module label, used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Module doliconnector";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = '13.0.1';
		// Module description used if translation string 'ModuleXXXDesc' not found (XXX is id value)
    $this->editor_name = 'ptibogxiv.net';
    $this->editor_url = 'https://www.ptibogxiv.net';
		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 1;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/images directory, use this->picto=DOL_URL_ROOT.'/module/images/file.png'
		$this->picto = 'doliconnector@doliconnector';
		
    
    // Dependencies
    $this->depends = array('modApi');		// List of modules id that must be enabled if this module is enabled
    $this->requiredby = array();	// List of modules id to disable if this one is disabled
    $this->phpmin = array(5,6);					// Minimum version of PHP required by module
    $this->need_dolibarr_version = array(8,0);	// Minimum version of Dolibarr required by module
    $this->langfiles = array("doliconnector@doliconnector");


       // Config pages. Put here list of php page, stored into oblyon/admin directory, to use to setup module.
    $this->config_page_url = array("doliconnector.php@doliconnector");
    
		// Defined all module parts (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = array(
				'triggers' => array(
						'data' => 1,
						'entity' => '0'
				),
				    'hooks' => array(
						'data' => array(
								'thirdpartycard'
						),
						'entity' => '0'
				),
		);

    // Data directories to create when module is enabled
    $this->dirs = array();
		// Exports
		$r=1;
    
    // Cronjobs
    $arraydate=dol_getdate(dol_now());
    $datestart=dol_mktime(22, 0, 0, $arraydate['mon'], $arraydate['mday'], $arraydate['year']);
    $this->cronjobs = array(
        0=>array(
            'label'=>'DeleteExpiredBasketTitle',
            'jobtype'=>'method', 'class'=>'/doliconnector/class/dao_doliconnector.class.php',
            'objectname'=>'Daodoliconnector',
            'method'=>'DeleteExpiredBasket',
            'parameters'=>'3600',
            'comment'=>'DeleteExpiredBasket',
            'frequency'=>5,
            'unitfrequency'=> 60,
            'priority'=>50,
            'status'=>1,
            'test'=>$conf->commande->enabled,
            'datestart'=>$datestart
        ),
    );
	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	public function init($options='')
	{
		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 * Remove from database constants, boxes and permissions from Dolibarr database.
	 * Data directories are not deleted
	 *
	 * @param      string	$options    Options when enabling module ('', 'noboxes')
	 * @return     int             	1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

}