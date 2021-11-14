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
 *	    \file       doliconnector/lib/doliconnector.lib.php
 *		\brief      doliconnector functions
 * 		\ingroup	doliconnector
 *
 */


 /**
 * Return array of tabs to used on page
 *
 * @param	Object	$object		Object for tabs
 * @return	array				Array of tabs
 */
function doliconnector_admin_prepare_head()
{
	global $langs, $conf;
	$langs->load('doliconnector@doliconnector');

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/doliconnector/admin/doliconnector.php",1);
	$head[$h][1] = $langs->trans("Setup");
	$head[$h][2] = 'doliconnector';
	$h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    // $this->tabs = array('entity:+tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to add new tab
    // $this->tabs = array('entity:-tabname:Title:@mymodule:/mymodule/mypage.php?id=__ID__');   to remove a tab
    complete_head_from_modules($conf,$langs,$object,$head,$h,'doliconnector');

    $head[$h][0] = dol_buildpath("/doliconnector/admin/about.php",1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

	return $head;
} 
 
 
function doliconnector_prepare_head($object)
{
    global $langs, $conf, $user;
    $h = 0;
    $head = array();
    
 if ((float) DOL_VERSION >= 6.0) {
    $head[$h][0] = DOL_URL_ROOT.'/societe/card.php?socid='.$object->id;
 }
 else {
    $head[$h][0] = DOL_URL_ROOT.'/societe/soc.php?socid='.$object->id;
 }
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'company';
    $h++;
    
    $head[$h][0] = 'card.php?socid='.$object->id;
    $head[$h][1] = $langs->trans("LinkedToWordpress");
    $head[$h][2] = 'doliconnector';
    $h++;

    return $head;
}
