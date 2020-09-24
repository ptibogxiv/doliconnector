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
 *  \file       htdocs/societe/commerciaux.php
 *  \ingroup    societe
 *  \brief      Page of links to sales representatives
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/member.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php'; 
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';
dol_include_once('/doliconnector/class/dao_doliconnector.class.php');
dol_include_once('/doliconnector/lib/doliconnector.lib.php');

global $db, $langs, $user;
  
$langs->loadLangs(array("companies", "commercial", "customers", "suppliers", "banks", "users", "doliconnector@doliconnector"));

// Security check
$action	= GETPOST('action');
$socid = GETPOST('socid', 'int');
$confirm	= GETPOST('confirm');
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe','','');

$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$rowid = GETPOST("rowid", 'alpha');
$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) { $page = 0; }     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

/*
 *	Actions
 */
if (! empty($socid) && $action=='create' && $confirm=='yes')
{

	$object = new Societe($db);
	$result=$object->fetch($socid);

	if ($user->rights->societe->creer)
	{

require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';

$data = array(
    'username' => uniqid(),
    'name'  => trim($object->name),
    'email' => trim($object->email),
    'url' => trim($object->url), 
    'locale' => $object->default_lang,
    'password' => getRandomPassword(true)
);
$wordpress=new Daodoliconnector($db);
$result=$wordpress->doliconnectSync('POST', '/users/', $data);
             	 
if ( $result->id > 0 ) {
					$sql = "INSERT INTO " . MAIN_DB_PREFIX . "societe_account (fk_soc, login, key_account, site, status, entity, date_creation, fk_user_creat)";
					$sql .= " VALUES (".$socid.", '', '".$result->id."', 'wordpress', '1', " . $conf->entity . ", '".$db->idate(dol_now())."', ".$user->id.")";
					$resql = $db->query($sql);
}       
					if (! $resql)
					{
						$db->error = $db->lasterror();
            setEventMessages($langs->trans('SyncError', $langs->trans('SyncError')), null, 'errors');
            dol_syslog(get_class($db)."::del_commercial Erreur");           
					}  else  {
setEventMessages($langs->trans('SyncSuccess', $langs->trans('SyncSuccess')), null, 'mesgs');
          }

		header("Location: card.php?socid=".$socid);
		exit;
	}
	else
	{
		header("Location: ".$_SERVER["PHP_SELF"]."?socid=".$socid);
		exit;
	}
}
elseif (! empty($socid) && $action=='add' && $_GET["commid"])
{

	if ($user->rights->societe->creer)
	{

					$sql = "INSERT INTO " . MAIN_DB_PREFIX . "societe_account (fk_soc, login, key_account, site, status, entity, date_creation, fk_user_creat)";
					$sql .= " VALUES (".$socid.", '', '".$db->escape($_GET["commid"])."', 'wordpress', '1', " . $conf->entity . ", '".$db->idate(dol_now())."', ".$user->id.")";
					$resql = $db->query($sql);
					if (! $resql)
					{
						$this->error = $db->lasterror();
            setEventMessages($langs->trans('SyncError', $langs->trans('SyncError')), null, 'errors');
            dol_syslog(get_class($this)."::del_commercial Erreur");           
					}  else  {
setEventMessages($langs->trans('SyncSuccess', $langs->trans('SyncSuccess')), null, 'mesgs');
          }

		header("Location: card.php?socid=".$socid);
		exit;
	}
	else
	{
		header("Location: ".$_SERVER["PHP_SELF"]."?socid=".$socid);
		exit;
	}
}
elseif (! empty($socid) && $action=='delete' && $_GET["delcommid"])
{

	if ($user->rights->societe->creer)
	{

            $sql  = "DELETE FROM ".MAIN_DB_PREFIX."societe_account ";
            $sql .= " WHERE key_account =".$_GET["delcommid"];
            $sql.= " AND site = 'wordpress' ";            
            $sql.= " AND entity IN (" . getEntity('thirdparty') . ")";
            if (! $db->query($sql) )
            {
setEventMessages($langs->trans('SyncError', $langs->trans('SyncError')), null, 'errors');            
                dol_syslog(get_class($this)."::del_commercial Erreur");
            }
            else {
setEventMessages($langs->trans('DelSuccess', $langs->trans('DelSuccess')), null, 'mesgs');
            }

		header("Location: card.php?socid=".$socid);
		exit;
	}
	else
	{
		header("Location: ".$_SERVER["PHP_SELF"]."?socid=".$socid);
		exit;
	}
}


/*
 *	View
 */

$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('',$langs->trans("ThirdParty"),$help_url);

$form = new Form($db);

if (! empty($socid))
{
	$object = new Societe($db);
	$result=$object->fetch($socid);

	$action='view';

	$head=doliconnector_prepare_head($object);

	dol_fiche_head($head, 'doliconnector', $langs->trans("ThirdParty"),0,'company');

    $linkback = '<a href="'.DOL_URL_ROOT.'/societe/list.php">'.$langs->trans("BackToList").'</a>';
	
    dol_banner_tab($object, 'socid', $linkback, ($user->societe_id?0:1), 'rowid', 'nom');
        
	print '<div class="fichecenter">';

    print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent">';

	print '<tr>';
    print '<td class="titlefield">'.$langs->trans('CustomerCode').'</td><td'.(empty($conf->global->SOCIETE_USEPREFIX)?' colspan="3"':'').'>';
    print $object->code_client;
    if ( $object->check_codeclient() <> 0 ) print ' '.$langs->trans("WrongCustomerCode");
    print '</td>';
    if (! empty($conf->global->SOCIETE_USEPREFIX))  // Old not used prefix field
    {
       print '<td>'.$langs->trans('Prefix').'</td><td>'.$object->prefix_comm.'</td>';
    }
    print '</td>';
    print '</tr>';
$email=$object->email;

	print '<tr><td>'.$langs->trans('LinkedToWordpress').'</td>';
	print '<td colspan="3">';    
		$societeaccount = new SocieteAccount($db);
		$wdpr = $societeaccount->getCustomerAccount($object->id, 'wordpress', '1');
if ( $wdpr > 0 ) {
$wordpress=new Daodoliconnector($db);
$result=$wordpress->doliconnectSync('GET', '/users/'.$wdpr.'/?context=edit', '');
  			  print $result->name.' ('.$result->slug.') <a href="'.$_SERVER["PHP_SELF"].'?socid='.$socid.'&amp;action=delete&amp;delcommid='.$wdpr.'">';
			    print img_picto($langs->transnoentitiesnoconv("RemoveLink"), 'unlink');
			    print '</a>'; 
			}
     		else
		{
print $langs->trans("NoSync");
		} 


	print "</td></tr>";

	print '</table>';
	print "</div>\n";
	
	dol_fiche_end();
  
//	$formquestionremovepoints=array(
//			'text' => $langs->trans("ConfirmPoints"),
//			array('type' => 'text', 'name' => 'pointsremove','label' => $langs->trans("HowManyPointsRemove"), 'value' => '', 'size'=>5)
//	);
   
	print '<div class="tabsAction">';
	
	if ( $wdpr > 0 or ! isValidEMail($email)){
  if (! isValidEMail($email)){
  print 'Une adresse email valide est nécessaire pour créer un utilisateur Wordpress';
  }
	 print '<span id="action-removepoints" class="butActionRefused">'.$langs->trans('CreateUserWordpress').'</span>'."\n";
			}
  else {

 	 print ''; 
 	 print '<span id="action-removepoints" class="butActionDelete">'.$langs->trans('CreateUserWordpress').'</span>'."\n"; 
   print $form->formconfirm($_SERVER["PHP_SELF"].'?socid='.$socid, $langs->trans('CreateUserWordpress'), $langs->trans('CreateUserWordpressInfo'), 'create', $formquestionremovepoints, 'yes', 'action-removepoints', 200, 400);
  }
	
	print '</div>';

	if ( !$wdpr && $user->rights->societe->creer && $user->rights->societe->client->voir )
	{
		/*
		 * Liste
		 *
		 */ 

  $pagew = $page+1;
  $wordpress=new Daodoliconnector($db);
  $result=$wordpress->doliconnectSync('GET', '/users/?context=edit&per_page='.$limit.'&page='.$pagew, null); 
	$num = count($result)+1;
	$totalnboflines = '';

	$param = '';
	//if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage='.urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.urlencode($limit);
	$param .= '&socid='.$socid;
	$moreforfilter = '';

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?socid='.$socid.'">';
    if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
    print '<input type="hidden" name="action" value="list">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
    print '<input type="hidden" name="page" value="'.$page.'">';
  
  $title=$langs->trans("ListOfUsers");

	print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $totalnboflines, 'user', 0, '', '', $limit);

			// Lignes des titres
			print '<table class="noborder" width="100%">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("Name").'</td>';
			print '<td>'.$langs->trans("Login").'</td>';
			print '<td>'.$langs->trans("Email").'</td>';
			print '<td>&nbsp;</td>';
      print '<td>&nbsp;</td>';
			print "</tr>\n";

//print $result;
if ($result) {     
foreach ($result as $user ) { 
$wordpress->getThirdparty($user->id, '1');
$wdpr = $societeaccount->getCustomerAccount($wordpress->fk_soc, 'wordpress', '1');
print "<tr ".$bc[$var]."><td>";
print $user->name;
print '</td><td>'.$user->slug.'</td>';
print '<td>'.$user->email.'</td><td>';

 if ( isset($email) && $email == $user->email ) {
 				print $form->textwithpicto('',$langs->trans("FavoriteSync"),1,'star');
}  
				print '</td><td>';
        
        if ( $wdpr == $user->id ) {
				print $langs->trans("AlreadySync");
} elseif ( $wdpr > 0 && $wdpr == $user->id ) {
				print $langs->trans("Sync");
} else {
				print '<a href="'.$_SERVER["PHP_SELF"].'?socid='.$object->id.'&amp;action=add&amp;commid='.$user->id.'">'.$langs->trans("LinkSync").'</a>';
}

print '</td></tr>'."\n";
}
}
			print "</table>";
			//$db->free($resql);
		}
//		else
//		{
//			dol_print_error($db);
//		}
    
	}
// End of page
llxFooter();
$db->close();