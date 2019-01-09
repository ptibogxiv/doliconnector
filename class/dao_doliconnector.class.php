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
require_once (DOL_DOCUMENT_ROOT . "/core/class/commonobject.class.php");
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

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
  
  public function getThirparty($id, $status=0)
	{
		$sql = "SELECT sa.fk_soc as fk_soc, sa.entity";
		$sql.= " FROM " . MAIN_DB_PREFIX . "societe_account as sa";
		$sql.= " WHERE sa.key_account = " . $id;
		$sql.= " AND sa.entity IN (".getEntity('societe').")";
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
    	$sql.= " WHERE fk_soc ='" .$fk_soc."' AND fk_statut='0' ";
      $sql.= " ORDER BY rowid DESC LIMIT 1";
 
      $resql = $this->db->query($sql);
      if ($resql)
        {
      $num_prods = $this->db->fetch_object($resql);
      return  $num_prods->rowid;
        }
      else return 0;
      
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
  
public function doliconnectSync($methodc,$url,$datac)
{ 
global $conf;

if (empty($conf->global->DOLICONNECT_ALTERNATIVE_ENTITY)){
		$altentity = $conf->entity;
 }else{
		$altentity = $conf->global->DOLICONNECT_ALTERNATIVE_ENTITY;
}

$url=$conf->global->MAIN_INFO_SOCIETE_WEB."/wp-json/wp/v2".$url;

$curl=curl_init();
curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $methodc);
curl_setopt($curl,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36 Edge/15.15063');
curl_setopt($curl,CURLOPT_URL,$url);
curl_setopt($curl,CURLOPT_POSTFIELDS, $datac);
curl_setopt($curl,CURLOPT_CONNECTTIMEOUT,2); 
curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
$httpheader = ['Authorization: Basic ' . base64_encode( ''.$conf->global->DOLICONNECT_USER.':'.$conf->global->DOLICONNECT_PASSWORD.'' )];
$httpheader[] = "Content-Type:application/json";
curl_setopt($curl, CURLOPT_HTTPHEADER, $httpheader);
$response = curl_exec($curl);
curl_close($curl);
return $response;
} 
  
}