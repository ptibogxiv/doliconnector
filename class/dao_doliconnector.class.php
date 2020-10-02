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

// Put here all includes required by your class file
require_once DOL_DOCUMENT_ROOT . "/core/class/commonobject.class.php";

/**
 *	\class      Rewards
 *	\brief      Class for Rewards
 */
class Daodoliconnector extends CommonObject
{
	public $rowid;
  public $fk_soc;
  public $wordpress;
  public $entity;
	
	/**
	 * 	Constructor
	 *
	 * 	@param	DoliDB		$db			Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
	
	/**
	 * 
	 * @param 	Facture 	$facture	Invoice object
	 * @param 	double 		$points		Points to add/remove
	 * @param 	string 		$typemov	Type of movement (increase to add, decrease to remove)
	 * @return int			<0 if KO, >0 if OK
	 */
  
  public function getThirdparty($id, $status=0)
	{
		$sql = "SELECT sa.fk_soc as fk_soc, sa.entity";
		$sql.= " FROM " . MAIN_DB_PREFIX . "societe_account as sa";
		$sql.= " WHERE sa.entity IN (".getEntity('societe').")";
		$sql.= " AND sa.key_account = " . $id;
		$sql.= " AND sa.site = 'wordpress' AND sa.status = ".((int) $status);
		//$sql.= " AND key_account IS NOT NULL AND key_account <> ''";
		//$sql.= " ORDER BY sa.key_account DESC";

		dol_syslog(get_class($this) . "::getCustomerAccount Try to find the first system customer id for wordpress of thirdparty id=".$id." (exemple: cus_.... for stripe)", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$key = $obj->fk_soc;
			} else {
				$key = '';
			}
		} else {
			$key = '';
		}

		return $key;
	}
  
public function doliconnectorder($fk_soc)
	{
		global $conf;

    	$sql = "SELECT rowid";
    	$sql.= " FROM ".MAIN_DB_PREFIX."commande ";
    	$sql.= " WHERE entity IN (".getEntity('commande').") AND fk_soc ='" .$fk_soc."' AND fk_statut='0' and module_source='doliconnect' ";
      $sql.= " ORDER BY rowid DESC LIMIT 1";
 
      $resql = $this->db->query($sql);
      if ($resql)
        {
      $num_prods = $this->db->fetch_object($resql);
      return  $num_prods->rowid;
        }
      else return -1;
      
	}  
  
public function doliconnectorderitem($id)
	{
		global $conf;
    	$nb = 0;

    	$sql = "SELECT SUM(qty) as nb";
    	$sql.= " FROM ".MAIN_DB_PREFIX."commandedet ";
    	$sql.= " WHERE fk_commande = '" .$id."'";

      $resql = $this->db->query($sql);
    	if ($resql)
    	{
   			$obj = $this->db->fetch_object($resql);
   			if ($obj) $nb = $obj->nb;
        if ($nb==null) $nb=0;
   			$this->db->free($resql);
    		return $nb;
    	}
    	else
    	{
    		$this->error=$this->db->lasterror();
    		return 0;
    	}
	}  
  
public function doliconnectSync($method, $url, $data = null)
{ 
global $conf;

if (empty($conf->global->DOLICONNECT_ALTERNATIVE_ENTITY)){
		$altentity = $conf->entity;
 }else{
		$altentity = $conf->global->DOLICONNECT_ALTERNATIVE_ENTITY;
}

$url=$conf->global->MAIN_INFO_SOCIETE_WEB."/wp-json/wp/v2".$url;

$curl=curl_init();
curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($curl,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36 Edge/15.15063');
curl_setopt($curl,CURLOPT_URL,$url);
if ($data) curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,2); 
curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
$httpheader = ['Authorization: Basic ' . base64_encode( ''.$conf->global->DOLICONNECT_USER.':'.$conf->global->DOLICONNECT_PASSWORD.'' )];
$httpheader[] = "Content-Type:application/json";
curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);
$response = curl_exec($curl);
curl_close($curl);
return json_decode($response);
}

	/**
	 * Send reminders by emails before subscription end
	 * CAN BE A CRON TASK
	 *
	 * @param	string		$daysbeforeendlist		Nb of days before end of subscription (negative number = after subscription). Can be a list of delay, separated by a semicolon, for example '10;5;0;-5'
	 * @return	int									0 if OK, <>0 if KO (this function is used also by cron so only 0 is OK)
	 */
	public function DeleteExpiredBasket($secondbeforedelete = '3600', $doliconnect = 1)
	{

        global $conf, $langs, $user, $mc;

        $this->output = '';
        $this->error='';
        $oldentity = (!empty($conf->entity)? $conf->entity : 1);
		$now = dol_now();
		$nbok = 0;
		$nbko = 0;
    
    //$user = new User($this->db);
    //$user->fetch($conf->global->DOLICONNECT_USER_AUTOMATIC);

		$listofordersok = array();
		$listofordersko = array();
        
		if (empty($conf->commande->enabled)) // Should not happen. If module disabled, cron job should not be visible.
		{
			$langs->load("order");
			$this->output = $langs->trans('ModuleNotEnabled', $langs->transnoentitiesnoconv("Commande"));
			return 0;
		}
        $return = 0;
        dol_syslog(__METHOD__, LOG_DEBUG);
        require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

        $sql = "SELECT t.rowid, t.entity";
        $sql .= " FROM ".MAIN_DB_PREFIX."commande as t";
        $sql .= ' WHERE ';//t.entity IN ('.getEntity('commande').') AND';
        $sql .= " t.date_valid IS NULL AND"; // Join for the needed table to filter by sale     
        $sql .= " t.fk_statut = 0 AND"; // Join for the needed table to filter by sale
        if ($doliconnect) $sql .= " t.module_source = 'doliconnect'"; // Join for the needed table to filter by sale

        dol_syslog("API Rest request");
        $result = $this->db->query($sql);

        if ($result)
        {
            $num = $this->db->num_rows($result);
            $min = min($num, ($limit <= 0 ? $num : $limit));
            $i = 0;
            while ($i < $min)
            {
      $obj = $this->db->fetch_object($result);
      
      if (!empty($conf->multicompany->enabled) && is_object($mc) && $obj->entity != $conf->entity) {
      $ret = $mc->switchEntity($obj->entity);
      }

                $commande_static = new Commande($this->db);
                if ($commande_static->fetch($obj->rowid)) {
                    // Add external contacts ids
                if ($commande_static->date_modification < (dol_now()-$secondbeforedelete)) {
                $result2 = $commande_static->delete($user, 0);		
                if (!empty($result2)) { $nbok++; }
                }    

                }
            
            $i++;
            }
      if ($conf->entity != $oldentity) {
      $ret = $mc->switchEntity($oldentity);
      }                         
			$this->output = 'Found '.($i).' draft orders.';
			$this->output .= ' Delete '.$nbok.' draft orders';  
        }
        else {
            dol_syslog(__METHOD__.': Error when retrieve order list', LOG_ERR);
            $this->output .= "Error when retrieve order list\n";
        }
        //if (!$i) {
        //    $this->output .= "No draft order to delete found\n";
        //}

		return 0;
	} 
  
}