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
 * \file       /doliconector/admin/doliconnector.php
 * \ingroup    paypal
 * \brief      Page to setup doliconnector module
 */

// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}
require_once '../class/actions_doliconnector.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

$servicename='doliconnector';

// Load translation files required by the page
$langs->loadLangs(array('admin', 'doliconnector@doliconnector')); 

if (! $user->admin) accessforbidden();

$action = GETPOST('action','alpha');

if ($action == 'setvalue' && $user->admin)
{
	$db->begin();
    $result = dolibarr_set_const($db, "DOLICONNECT_APIKEY", GETPOST('DOLICONNECT_APIKEY','alpha'),'chaine',0,'',0);
    if (! $result > 0) $error++;
    $result = dolibarr_set_const($db, "DOLICONNECT_USER_AUTOMATIC", GETPOST('DOLICONNECT_USER_AUTOMATIC','alpha'),'chaine',0,'',0);
    if (! $result > 0) $error++;
    $result = dolibarr_set_const($db, "DOLICONNECT_USER", GETPOST('DOLICONNECT_USER','alpha'),'chaine',0,'',0);
    if (! $result > 0) $error++;
    $result = dolibarr_set_const($db, "DOLICONNECT_PASSWORD", GETPOST('DOLICONNECT_PASSWORD','alpha'),'chaine',0,'',0);
    if (! $result > 0) $error++;
    $result = dolibarr_set_const($db, "DOLICONNECT_CATSHOP", GETPOST('DOLICONNECT_CATSHOP', 'alpha'), 'chaine', 0, '', $conf->entity);          
	  if (! $result > 0) $error++;
    $result = dolibarr_set_const($db, "DOLICONNECT_ID_WAREHOUSE", GETPOST('DOLICONNECT_ID_WAREHOUSE', 'alpha'), 'chaine', 0, '', $conf->entity);   
    if (! $result > 0) $error++;
  if (! $error)
  	{
  		$db->commit();
  		setEventMessage($langs->trans("SetupSaved"));
  	}
  	else
  	{
  		$db->rollback();
		dol_print_error($db);
    }
}


/*
 *	View
 */

$form=new Form($db);
$formproduct=new FormProduct($db);

llxHeader('',$langs->trans("DoliconnectorSetup"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("DoliconnectorSetup"),$linkback,'doliconnector@doliconnector');

$head = doliconnector_admin_prepare_head();

dol_fiche_head ( $head, 'doliconnector', $langs->trans ( "Module431310Name" ), 0, "" );

print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setvalue">';

print load_fiche_titre($langs->trans("DoliconnectorSetup"),'','');
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print "</tr>\n";

 // Root category for products
print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("RootCategoryForDolishop"), $langs->trans("RootCategoryForDolishop"));
print '<td colspan="2">';
print $form->select_all_categories(Categorie::TYPE_PRODUCT, $conf->global->DOLICONNECT_CATSHOP, 'DOLICONNECT_CATSHOP', 64, 0, 0);
print ajax_combobox('DOLICONNECT_CATSHOP');
print "</td></tr>\n";

print '<tr class="oddeven"><td>'.$langs->trans("DoliconnectIdWareHouse").'</td>';	// Force warehouse (this is not a default value)
print '<td colspan="2">';
print $formproduct->selectWarehouses($conf->global->{'DOLICONNECT_ID_WAREHOUSE'.$terminal}, 'DOLICONNECT_ID_WAREHOUSE', '', 1, '');
print ' <a href="'.DOL_URL_ROOT.'/product/stock/card.php?action=create&backtopage='.urlencode($_SERVER["PHP_SELF"]).'">('.$langs->trans("Create").')</a>';
print '</td></tr>';

$var=!$var;
print '<tr class="oddeven"><td class="fieldrequired">';
print $langs->trans("AutomaticUserAssign").'</td><td>';
print $form->select_dolusers($conf->global->DOLICONNECT_USER_AUTOMATIC, 'DOLICONNECT_USER_AUTOMATIC', 0);
print '</td></tr>';

$var=!$var;
print '<tr class="oddeven"><td class="fieldrequired">';
print $langs->trans("DOLICONNECT_USER").'</td><td>';
print '<input size="80" type="text" name="DOLICONNECT_USER" value="'.$conf->global->DOLICONNECT_USER.'">';
//print '<br />'.$langs->trans("Example").': https://www.votredomaine.com/';
print '</td></tr>';

$var=!$var;
print '<tr class="oddeven"><td class="fieldrequired">';
print $langs->trans("DOLICONNECT_PASSWORD").'</td><td>';
print '<input size="80" type="text" name="DOLICONNECT_PASSWORD" value="'.$conf->global->DOLICONNECT_PASSWORD.'">';
//print '<br />'.$langs->trans("Example").': https://www.votredomaine.com/';
print '</td></tr>';

print '</table>';

dol_fiche_end();

print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></div>';

print '</form>';

llxFooter();
$db->close();