<?php
/* Copyright (C) 2017-2018 	PtibogXIV        <support@ptibogxiv.net>
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
 *	\file       htdocs/multicompany/actions_multicompany.class.php
 *	\ingroup    multicompany
 *	\brief      File Class multicompany
 */
 
dol_include_once('/doliconnector/lib/doliconnector.lib.php');
dol_include_once('/doliconnector/class/dao_doliconnector.class.php');
$langs->load("doliconnector@doliconnector");

/**
 *	\class      ActionsMulticompany
 *	\brief      Class Actions of the module multicompany
 */
class Actionsdoliconnector
{
	/** @var DoliDB */
	var $db;

	private $config=array();

	// For Hookmanager return
	var $resprints;
	var $results=array();
 

	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *
	 */
   
	function formObjectOptions($parameters=false, &$object, &$action='')
	{
		global $db,$conf,$user,$langs,$form;

		if (is_array($parameters) && ! empty($parameters))
		{
			foreach($parameters as $key=>$value)
			{
				$key=$value;
			}
		}

		if (is_object($object) && $object->element == 'societe')
		{ 
			if ($action == 'create' || $action == 'editparentwordpress')
			{
				$this->resprints.= '<tr><td>'.fieldLabel('LinkedToWordpress','linked_entity').'</td><td colspan="3" class="maxwidthonsmartphone">';
//				$s = $this->select_entities('', 'linked_entity', '', 0, array($conf->entity), true);
				$this->resprints.= $form->textwithpicto($s,$langs->trans("LinkedToWordpressDesc"),1);
				$this->resprints.= '</td></tr>';
			}
			else
			{
				$this->resprints.= '<tr><td>';
				$this->resprints.= '<table width="100%" class="nobordernopadding"><tr><td>';
				$this->resprints.= $form->textwithpicto($langs->trans('LinkedToWordpress'),$langs->trans("LinkedToWordpressDesc"),1);
				$this->resprints.= '</td><td align="right">';
				$this->resprints.= '<a class="editfielda" href="'.$dolibarr_main_url_root.dol_buildpath('/doliconnector/card.php?socid='.$object->id, 1).'">'.img_edit($langs->transnoentitiesnoconv('Edit'), 1).'</a>';
				$this->resprints.= '</td></tr></table>';
				$this->resprints.= '</td>';
				$this->resprints.= '<td colspan="3">';
        
		include_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';
		$societeaccount = new SocieteAccount($this->db);
		$wdpr = $societeaccount->getCustomerAccount($object->id, 'wordpress', '1');
    
if ( $wdpr > 0 ) {
$wordpress=new Daodoliconnector($this->db);
$result=$wordpress->doliconnectSync('GET', '/users/'.$wdpr.'/?context=edit', '');
$this->resprints.= $result->name.' ('.$result->slug.'), '.$result->email;

} else {
$this->resprints.= $langs->trans("NoSync");
		} 
				$this->resprints.= '</td></tr>';
			}
		} 

		return 0;
	}

}
