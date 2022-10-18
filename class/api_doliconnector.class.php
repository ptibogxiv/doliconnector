<?php
/* Copyright (C) 2017-2019 	Thibault FOUCART        <support@ptibogxiv.net>
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

use Luracast\Restler\RestException;

/**
 * API class for towns (content of the ziptown dictionary)
 *
 * @access protected
 * @class DolibarrApiAccess {@requires user,external}
 */
class doliconnector extends DolibarrApi
{

    /**
     * Constructor
     */
    function __construct()
    {
        global $db,$conf,$langs;
        $this->db = $db;

dol_include_once('/doliconnector/class/dao_doliconnector.class.php'); 
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/ccountry.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/config.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/payments.lib.php';
//require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypal.lib.php';
//require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypalfunctions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

        $this->company = new Societe($this->db);
        $this->invoice = new Facture($this->db);
  
    }  
    
	/**
	 * Get translation of a variable
	 *
	 * Note that conf variables that stores security key or password hashes can't be loaded with API.
	 *
	 * @param	string			$constantname	Name of variable to translate
	 * @param	string			$filename	Name of loading file for translate
	 * @param	string			$langcode	Code of language for translate ie: en_US
	 * @return  array|mixed 				Data without useless information
	 *
	 * @url     GET translation/{constantname}
	 *
	 * @throws RestException 403 Forbidden
	 * @throws RestException 404 Error Bad or unknown value for constantname
	 */
	public function getTranslate($constantname, $filename, $langcode = '')
	{
    global $conf;

		if (!DolibarrApiAccess::$user->admin
			&& (empty($conf->global->API_LOGINS_ALLOWED_FOR_CONST_READ) || DolibarrApiAccess::$user->login != $conf->global->API_LOGINS_ALLOWED_FOR_CONST_READ)) {
			throw new RestException(403, 'Error API open to admin users only or to the users with logins defined into constant API_LOGINS_ALLOWED_FOR_CONST_READ');
		}

    $langs = new Translate('', $conf);
    $langs->setDefaultLang($langcode);
    $langs->load($filename);

		if (!preg_match('/^[a-zA-Z0-9_]+$/', $constantname)) {
			throw new RestException(404, 'Error Bad or unknown value for constantname');
    }
		//if (isASecretKey($constantname)) {
		//	throw new RestException(403, 'Forbidden. This parameter cant be read with APIs');
		//}


		return $langs->trans($constantname);
	}

    /**
     * Get properties of an thirparty / wordpress's user
     *
     * Return an array with entity informations
     *
     * @param     int     $id ID of wordpress user
     * @return    array|mixed data without useless information
     *
     * @throws    RestException
     */
    function get($id)
    {
        global $user,$conf;     
        $user = DolibarrApiAccess::$user;

        if ( $id <= 0 ) {
            throw new RestException(404, 'wordpress #'.$id.' not found');
        }

        $array = array();

        $doliconnector = new Daodoliconnector($this->db);
        if ($id > 0) {
        $array['fk_soc'] = $doliconnector->getThirdparty($id, '1');
        $array['fk_order'] = $doliconnector->doliconnectorder($array['fk_soc']);
        $array['fk_order_nb_item'] = $doliconnector->doliconnectorderitem($doliconnector->doliconnectorder($array['fk_soc']));
        }   
        $doliconnector = new Daodoliconnector($this->db);
        $societeaccount = new SocieteAccount($this->db);
        $wdpr = $societeaccount->getCustomerAccount($array['fk_soc'], 'wordpress', '1');
 
        if ( ! $wdpr && !empty($array['fk_soc'])  ) {
            throw new RestException(404, 'wordpress #'.$id.' not found');
        }
	$this->company->fetch($array['fk_soc']);
  $array['outstanding_limit'] = $this->company->outstanding_limit;
  $array['remise_percent'] = $this->company->remise_percent;
       
if (! getDolGlobalInt('PRODUIT_MULTIPRICES')) {      
  $array['price_level'] = $this->company->price_level;
} 
  
if (isModEnabled('adherent')) {  
  $member=new Adherent($this->db);
  $member->fetch('','',$this->company->id,'');
  $array['fk_member'] = $member->id;
  $array['member_end'] = $member->datefin;
  $array['fk_user'] = $member->user_id;
}

if (isModEnabled('agefodd')) { 
     $sql = "SELECT s.rowid as rowid, s.fk_soc, s.entity FROM ".MAIN_DB_PREFIX."agefodd_stagiaire as s";        
     $sql.= " WHERE s.entity IN (" . getEntity('agefodd') . ") AND s.fk_soc = '".$array['fk_soc']."' ";

  $result = $this->db->query($sql);
  if ($result > 0)
  {
  $trainee = $this->db->fetch_object($result);
  if (isset($trainee->rowid)) $array['fk_trainee'] = $trainee->rowid;
  } 
}
   
return $array;
    
}
    
     /**
     * Link a wordpress's user to a thirdparty
     *
     * @param int $id               ID of wordpress user
     * @param string $email         Email {@from body}
     * @param string $name         Name {@from body}
     * @return int  ID of subscription
     *
     * @url POST {id}
     */
    function linkThirdparty($id, $email, $name)
    {
        global $user,$conf;
       
      $user = DolibarrApiAccess::$user;

      $this->company->fetch('', '', '', '', '', '', '', '', '', '', $email, '');
      $doliconnector = new Daodoliconnector($this->db);
      
      if ( !$this->company->id > 0 ) {
      $this->company->name = $name;
      $this->company->email = $email;
      $this->company->client = 1;
      $this->company->status = 1;
      $this->company->code_client = -1;
      $fk_soc=$this->company->create(DolibarrApiAccess::$user);
      $this->company->fetch($fk_soc);
      }
      
      $societeaccount = new SocieteAccount($this->db);
      $wdpr = $societeaccount->getCustomerAccount($this->company->id, 'wordpress', '1');
      
      if ( $wdpr != $id ) { 
      		$sql = "INSERT INTO " . MAIN_DB_PREFIX . "societe_account (fk_soc, login, key_account, site, status, entity, date_creation, fk_user_creat)";
					$sql .= " VALUES (".$this->company->id.", '', '".$this->db->escape($id)."', 'wordpress', '1', " . $conf->entity . ", '".$this->db->idate(dol_now())."', ".DolibarrApiAccess::$user->id.")";
					$resql = $this->db->query($sql);
      }
      
if (! getDolGlobalInt('PRODUIT_MULTIPRICES')) {          
  $price_level=$this->company->price_level;
}

if (isModEnabled('adherent')) { 
  $member=new Adherent($this->db);
  $member->fetch('','',$this->company->id,'');
}

if (isModEnabled('agefodd')) { 
     $sql = "SELECT s.rowid as rowid, s.fk_soc, s.entity FROM ".MAIN_DB_PREFIX."agefodd_stagiaire as s";        
     $sql.= " WHERE s.entity IN (" . getEntity('agefodd') . ") AND s.fk_soc = '".$this->company->id."' ";

$result = $this->db->query($sql);
if ($result)
{
$trainee = $this->db->fetch_object($result);
} }
   
        return array(
            'fk_soc' => $this->company->id,
            'price_level' => $price_level,
            'outstanding_limit' => $this->company->outstanding_limit,
            'remise_percent' =>  $this->company->remise_percent,
            'fk_member' => $member->id,
            'member_end' => $member->datefin,             
            'fk_trainee' => $trainee->rowid,
            'fk_user' => $member->user_id,
            'fk_order' => $doliconnector->doliconnectorder($this->company->id),
            'fk_order_nb_item' => $doliconnector->doliconnectorderitem($doliconnector->doliconnectorder($this->company->id))

        );
    } 
    
    /**
     * List payment methods for a thirdparty
     *
     * @param 	int 	$id ID of thirdparty
     *
     * @url	GET {id}/paymentmethods
     * @param     string  $type Type of object (order, invoice...)
     * @param     int     $rowid ID of object
     *
     * @return int
     */
    function getListPaymentMethods($id, $type = null, $rowid = null)
    {
    global $conf, $mysoc;
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    if ( $id < 0  ) {
    throw new RestException(404, 'Thirdparty not found');
    }

    $result = $this->company->fetch($id);
    if( ! $result && !empty($id) ) {
      throw new RestException(404, 'Thirdparty not found');
    } 
      
    if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id) && !empty($id) ) {
      throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
    }
      
$amount_discount=$this->company->getAvailableDiscounts();

$infothirdparty =array(
						"email" => $this->company->email,
						"countrycode" => $this->company->country_code
            );
            
$list = array();

if (isModEnabled('stripe')) {
	$service = 'StripeTest';
	$servicestatus = 0;
  
	$publishable_key = $conf->global->STRIPE_TEST_PUBLISHABLE_KEY;  
  
	if (! empty($conf->global->STRIPE_LIVE))
	{
	$service = 'StripeLive';
	$servicestatus = 1;
    
  $publishable_key = $conf->global->STRIPE_LIVE_PUBLISHABLE_KEY; 
	}
  
$stripe = new Stripe($this->db); 
$stripeacc = $stripe->getStripeAccount($service);
$stripecu = $stripe->customerStripe($this->company, $stripeacc, $servicestatus, 1);


$infostripe = array();
$infostripe['live'] = $servicestatus;
$infostripe['publishable_key'] = $publishable_key;
$infostripe['account'] = $stripeacc;
$infostripe['types'] = array("card");
 
$listofpaymentmethods1 = array();
$listofpaymentmethods2 = array();
$listofpaymentmethods3 = array();
if (isset($stripecu->id) &&!empty($stripecu->id)) {
//$listofpaymentmethods = $stripe->getListOfPaymentMethods($this->company, $stripecu, 'card', $stripeacc, $servicestatus);
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$listofpaymentmethods1 = \Stripe\PaymentMethod::all(array("customer" => $stripecu->id, "type" => "card"));
		} else {
			$listofpaymentmethods1 = \Stripe\PaymentMethod::all(array("customer" => $stripecu->id, "type" => "card"), array("stripe_account" => $stripeacc));
		}
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$listofpaymentmethods2 = \Stripe\PaymentMethod::all(array("customer" => $stripecu->id, "type" => "sepa_debit"));
		} else {
			$listofpaymentmethods2 = \Stripe\PaymentMethod::all(array("customer" => $stripecu->id, "type" => "sepa_debit"), array("stripe_account" => $stripeacc));
		}
$listofpaymentmethods3 = $stripecu->sources->data;
}

if ( empty($type) && empty($rowid) && !empty($id) ) {
$stripeClientSecret = $stripe->getSetupIntent(null, null, $stripecu->id, $stripeacc, $servicestatus, false);
} elseif (!empty($type)) {
if ($type == 'order')
{
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
	$order=new Commande($this->db);
	$result=$order->fetch($rowid);
	if ($result <= 0)
	{
		$mesg=$order->error;
		$error++;
	}
	else
	{
		$result=$order->fetch_thirdparty($order->socid);
	}
	$object = $order;

	$amount=$object->total_ttc;
	$amount=price2num($amount);
  $currency=$object->multicurrency_code;
	$fulltag='ORD='.$object->id.'.CUS='.$object->thirdparty->id;
	$tag=null;
	$fulltag=dol_string_unaccent($fulltag);  
}
if ($object->id > 0) {  
$stripeClientSecret=$stripe->getPaymentIntent($amount, $object->multicurrency_code, $tag, 'Stripe payment: '.$fulltag.(is_object($object)?' ref='.$object->ref:''), $object, $stripecu, $stripeacc, $servicestatus, 1, 'automatic', false, null, 0);
} else {
          throw new RestException(404, 'Object '.$type.' id='.$rowid.' not found');
}
}  

$infostripe['client_secret'] = (isset($stripeClientSecret->client_secret)?$stripeClientSecret->client_secret:null);

if ( $listofpaymentmethods1 != null ) { 
foreach ( $listofpaymentmethods1 as $src ) {

$list[$src->id]['id'] = $src->id;
$list[$src->id]['type'] = $src->type;

$list[$src->id]['holder'] = $src->billing_details->name;

$list[$src->id]['brand'] = $src->card->brand;
$list[$src->id]['reference'] = '&#8226;&#8226;&#8226;&#8226;'.$src->card->last4; 
$list[$src->id]['expiration'] = $src->card->exp_year.'/'.$src->card->exp_month; 
$list[$src->id]['country'] = $src->card->country;

if ( ($stripecu->invoice_settings->default_payment_method != $src->id) ) { $default = null; } else { $default="1"; }

$list[$src->id]['default_source']= $default;

}}

if ( $listofpaymentmethods2 != null ) {
foreach ( $listofpaymentmethods2 as $src ) {

$list[$src->id]['id'] = $src->id;
$list[$src->id]['type'] = $src->type;

$list[$src->id]['holder'] = $src->billing_details->name;

$list[$src->id]['brand'] = 'sepa_debit';
$list[$src->id]['reference'] = '&#8226;&#8226;&#8226;&#8226;'.$src->sepa_debit->last4;
$list[$src->id]['expiration'] =  null;
$list[$src->id]['country'] = $src->sepa_debit->country;

$setupintent = \Stripe\SetupIntent::all(['customer' => $stripecu->id,'payment_method' => $src->id,'limit' => 1]);
if (isset($setupintent->data[0]->mandate) && !empty($setupintent->data[0]->mandate)) {
$mandate = \Stripe\Mandate::retrieve($setupintent->data[0]->mandate);
$type = $mandate->payment_method_details->type;
$list[$src->id]['mandate']['creation'] = $mandate->customer_acceptance->accepted_at;
$list[$src->id]['mandate']['reference'] = $mandate->payment_method_details->$type->reference;
$list[$src->id]['mandate']['url'] = $mandate->payment_method_details->$type->url;
$list[$src->id]['mandate']['type'] = $mandate->type;
}
if ( ($stripecu->invoice_settings->default_payment_method != $src->id) ) { $default = null; } else { $default="1"; }

$list[$src->id]['default_source']= $default;

}}
 
if ( $listofpaymentmethods3 != null ) {

foreach ( $listofpaymentmethods3 as $src ) {

$list[$src->id]['id'] = $src->id;
$list[$src->id]['type'] = $src->type;

$list[$src->id]['holder'] = $src->billing_details->name;

if ( $src->type == 'card' ) {

$list[$src->id]['brand'] = $src->card->brand;
$list[$src->id]['reference'] = '&#8226;&#8226;&#8226;&#8226;'.$src->card->last4; 
$list[$src->id]['expiration'] = $src->card->exp_year.'/'.$src->card->exp_month; 
$list[$src->id]['country'] = $src->card->country;

} elseif ( $src->type == 'sepa_debit' ) {

$list[$src->id]['brand'] = 'sepa_debit';
$list[$src->id]['reference'] = '&#8226;&#8226;&#8226;&#8226;'.$src->sepa_debit->last4;
$list[$src->id]['mandate']['creation'] = null;
$list[$src->id]['mandate']['reference'] = $src->sepa_debit->mandate_reference;
$list[$src->id]['mandate']['url'] = $src->sepa_debit->mandate_url;
$list[$src->id]['mandate']['type'] = null;
$list[$src->id]['date_creation'] =  null;
$list[$src->id]['expiration'] =  null;
$list[$src->id]['country'] = $src->sepa_debit->country;

}

if ( ($stripecu->invoice_settings->default_payment_method != $src->id) ) { $default = null; } else { $default="1"; }

$list[$src->id]['default_source'] = $default;

} }

if ($listofpaymentmethods1 == null && $listofpaymentmethods2 == null) { $list=null; } 

if (!empty($conf->global->STRIPE_PAYMENT_REQUEST_API)) {
$infostripe['types'][] = "payment_request_api";
}
if (!empty($conf->global->STRIPE_SEPA_DIRECT_DEBIT) && ($this->company->isInEEC())) {
$infostripe['types'][] = "sepa_debit";
}
if (!empty($conf->global->STRIPE_IDEAL) && $this->company->country_code == 'NL') {
$infostripe['types'][] = "ideal";
}
if (!empty($conf->global->STRIPE_KLARNA) ) { //} && $this->company->country_code == 'NL')
$infostripe['types'][] = "klarna";
}
if (!empty($conf->global->STRIPE_SOFORT) ) { //} && $this->company->country_code == 'NL')
$infostripe['types'][] = "sofort";
}
if (!empty($conf->global->STRIPE_BANCONTACT) ) { //} && $this->company->country_code == 'NL')
$infostripe['types'][] = "bancontact";
}

}
 
$vir = null;
if (getDolGlobalInt('FACTURE_RIB_NUMBER')) {
$bank = new Account($this->db);
$bank->fetch(getDolGlobalInt('FACTURE_RIB_NUMBER'));
$vir = $bank;
}

$chq = null;
if (getDolGlobalInt('FACTURE_CHQ_NUMBER')) {
if (getDolGlobalInt('FACTURE_CHQ_NUMBER')=='-1'){
$chq=array('proprio' => $bank->proprio, 'owner_address' => $bank->owner_address);
} else {
$bank = new Account($this->db);
$bank->fetch(getDolGlobalInt('FACTURE_CHQ_NUMBER'));
$chq = $bank;
}
}

if ($result) {
$rib_list = $this->company->get_all_rib();
if (is_array($rib_list)) {
		foreach ($rib_list as $rib)
		{
$list[$rib->id]['id'] = $rib->id;
if (isModEnabled('prelevement'))
{
$list[$rib->id]['type'] = 'PRE';
$list[$rib->id]['brand'] = 'PRE';
} else {
$list[$rib->id]['type'] = 'VIR';
$list[$rib->id]['brand'] = 'VIR';
}
$list[$rib->id]['holder'] = !empty($rib->proprio)?$rib->proprio:$rib->label;
$list[$rib->id]['reference'] = '&#8226;&#8226;&#8226;&#8226;'.substr($rib->iban, -4);
$list[$rib->id]['mandate']['creation'] = $rib->date_rum;
$list[$rib->id]['mandate']['reference'] = $rib->rum;
$list[$rib->id]['mandate']['url'] = null;
$list[$rib->id]['mandate']['type'] = $rib->frstrecur;
$list[$rib->id]['date_creation'] =  $rib->datec;
$list[$rib->id]['expiration'] =  null;
$list[$rib->id]['country'] = substr($rib->iban, 0, 2);
$list[$rib->id]['default_source'] = $rib->default_rib;
}
}
}

$infopaypal = array();
if (! empty($conf->paypal->enabled)) {
$infopaypal['live'] = null;
$infopaypal['url'] = null;
}

$public_url = null;
if (!empty($type) && isset($object) && is_object($object) && isset($object->ref)) {
$public_url = getOnlinePaymentUrl(0, $type, $object->ref);
}  
  		return array(
      'thirdparty' => $infothirdparty,
			'payment_methods' => $list,
      'discount' => $amount_discount,
      'VIR' => $vir,
      'CHQ' => $chq,
      'stripe' => $infostripe,
      'paypal' => $infopaypal,
      'public_url' => $public_url
		);
    } 
    
    /**
     * Get payment method for a thirdparty
     *
     * @param 	int 	$id ID of thirdparty
     *
     * @url	GET {id}/paymentmethods/{method}
     *
     * @return int
     */
    function getPaymentMethod($id, $method)
    { 
    global $conf, $mysoc;

    $result = $this->company->fetch($id);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }
      
      if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
        throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
      }
         
if (! empty($conf->stripe->enabled))
{
	$service = 'StripeTest';
	$servicestatus = 0;
	if (! empty($conf->global->STRIPE_LIVE))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

$stripecu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);  

$payment_method = \Stripe\PaymentMethod::retrieve($method);    
    
return $payment_method;    
    }              
    
    /**
     * Attach a payment method to a thirdparty
     *
     * @param int $id               ID of thirdparty
     * @param string $method         ID of payment method
     * @param int $default         Default {@from body}
     * @return int  ID of subscription
     *
     * @throws 401
     *
     * @url POST {id}/paymentmethods/{method}
     */
    function addPaymentMethod($id, $method, $default=null) {
    global $conf, $mysoc;

    $result = $this->company->fetch($id);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }
      
      if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
        throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
      }
      
if (! empty($conf->stripe->enabled))
{
	$service = 'StripeTest';
	$servicestatus = 0;
	if (! empty($conf->global->STRIPE_LIVE))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

$stripecu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);  

$payment_method = \Stripe\PaymentMethod::retrieve($method, ["stripe_account" => $stripeacc]);

if ($payment_method && $stripecu) {
$result = $payment_method->attach(['customer' => $stripecu->id]);
}

if ($default) {
$stripecu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
$stripecu->invoice_settings->default_payment_method = (string) $method;
$result = $stripecu->save();
}

  
return $result;
    }
    
    /**
     * Update a payment method to a thirdparty
     *
     * @param int $id               ID of thirdparty
     * @param string $method         ID of payment method
     * @param int $default         Default {@from body}
     * @return int  ID of subscription
     *
     * @throws 401
     *
     * @url PUT {id}/paymentmethods/{method}
     */
    function updatePaymentMethod($id, $method, $default=null){
    global $conf, $mysoc;

    $result = $this->company->fetch($id);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }
      
      if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
        throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
      }
         
if (! empty($conf->stripe->enabled))
{
	$service = 'StripeTest';
	$servicestatus = 0;
	if (! empty($conf->global->STRIPE_LIVE))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

if ($default) {
$stripecu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
$stripecu->invoice_settings->default_payment_method = (string) $method;
$result = $stripecu->save();
}
  
return $result;
    }
    
     /**
     * Detach payment method to a thirdparty
     *
     * @param int		$id	Id of thirdparty
     * @param string		$method	Id of payment method
     *
     * @return mixed
     * @throws 401
     * 
     * @url DELETE {id}/paymentmethods/{method}
     */
    function deletePaymentMethod($id, $method) {
    global $conf;
  
    $result = $this->company->fetch($id);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }
      
      if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
        throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
      }
         
if (! empty($conf->stripe->enabled))
{
	$service = 'StripeTest';
	$servicestatus = 0;
	if (! empty($conf->global->STRIPE_LIVE))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account (no network access here)
}

				if (preg_match('/pm_/', $method))
				{
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$payment_method = \Stripe\PaymentMethod::retrieve($method);
		} else {
			$payment_method = \Stripe\PaymentMethod::retrieve($method, array("stripe_account" => $stripeacc));
		}
      if ($payment_method)
			{
			$payment_method->detach();
			}
        }
        elseif (preg_match('/src_/', $method) || preg_match('/tok_/', $method))
				{
				$stripecu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
				$card=$stripecu->sources->retrieve("$method");
				if ($card)
				{
					// $card->detach();  Does not work with card_, only with src_
					if (method_exists($card, 'detach')) $card->detach();
					else $card->delete();
				}
        } else {
		$companybankaccount = new CompanyBankAccount($this->db);
		if ($companybankaccount->fetch($method))
		{
			$result = $companybankaccount->delete($user);
			if ($result > 0)
			{
				//success
			}
			else
			{
				//error
			}
		}
		else
		{
			//error
		}
        }
                                                                       
        return array(
            'success' => array(
                'code' => 200,
                'message' => 'Payment method deleted'
            )
        );
    
    }

      /**
     * Pay an object
     *
     * @param string  $modulepart         Name of module or area concerned ('proposal', 'order', 'invoice', 'supplier_invoice', 'shipment', 'project', ...) 
     * @param int   $id         Id of object to pay
     * @param string $paymentintent         Force payment intent {@from body}
     * @param string $paymentmethod         Payment method {@from body}
     * @param int $save         Save payment method {@from body}
     * @return int  ID of subscription
     *
     * @url POST pay/{modulepart}/{id}
     * 
    * @throws RestException
     */
    function payObject($modulepart, $id, $paymentmethod, $paymentintent = null, $save = null)
    {
    global $langs, $conf, $hookmanager;
      if(! DolibarrApiAccess::$user->rights->societe->creer) {
        throw new RestException(401);
      }

//$result = $this->company->fetch($id);

$hidedetails = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
$hidedesc = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
$hideref = (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);
 
if (! empty($conf->stripe->enabled))
{
	$service = 'StripeTest';
	$servicestatus = 0;
	if (! empty($conf->global->STRIPE_LIVE))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}
  
	// Force to use the correct API key
	global $stripearrayofkeysbyenv;
	$site_account = $stripearrayofkeysbyenv[$servicestatus]['publishable_key'];

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service); // Get Stripe OAuth connect account (no remote access to Stripe here)

if (preg_match('/pi_/', $paymentmethod)) {
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$pi = \Stripe\PaymentIntent::retrieve("$paymentmethod");
		} else {
			$pi = \Stripe\PaymentIntent::retrieve("$paymentmethod", array("stripe_account" => $stripeacc));
		}
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$src = \Stripe\PaymentMethod::retrieve($pi->payment_method);
		} else {
			$src = \Stripe\PaymentMethod::retrieve($pi->payment_method, array("stripe_account" => $stripeacc));
		}
} elseif (preg_match('/pm_/', $paymentmethod)) {
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$src = \Stripe\PaymentMethod::retrieve("$paymentmethod");
		} else {
			$src = \Stripe\PaymentMethod::retrieve("$paymentmethod", array("stripe_account" => $stripeacc));
		}
} elseif (preg_match('/src_/', $paymentmethod)) {
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$src = \Stripe\Source::retrieve("$paymentmethod");
		} else {
			$src = \Stripe\Source::retrieve("$paymentmethod", array("stripe_account" => $stripeacc));
		}
} elseif (preg_match('/tok_/', $paymentmethod)) {
$src = \Stripe\Source::create(array(
  "type" => "card",
  "token" => $paymentmethod
),array("stripe_account" => $stripeacc));
$paymentmethod=$src->id;
}
} 

if (isset($src->type) && ($src->type == 'card' || $src->type == 'card_present')) {
$mode_reglement_code = 'CB';
} elseif (isset($src->type) && $src->type == 'sepa_debit') {
$mode_reglement_code = 'PRE';
} elseif (isset($src->type) && $src->type == 'ideal') {
$mode_reglement_code = 'VAD';
} elseif (isset($src->type) && $src->type == 'klarna') {
$mode_reglement_code = 'CB';
} else {
$mode_reglement_code = $paymentmethod;
}

$mode_reglement_id = dol_getIdFromCode($this->db, $mode_reglement_code, 'c_paiement', 'code', 'id', 1);
if ($mode_reglement_id <= 0) {
throw new RestException(404, 'payment method '.$mode_reglement_code.' or '.$paymentmethod.' not found');
}

if (preg_match('/order/', $modulepart)) {
$object=new Commande($this->db);
$result = $object->fetch($id);
if (!$result) {
            throw new RestException(404, 'Order not found');
        }
if ($object->statut == 0 && $object->billed != 1) {
if (!empty($conf->stock->enabled) && !empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER)) { $idwarehouse = $conf->global->DOLICONNECT_ID_WAREHOUSE; } else { $idwarehouse = 0; }
$object->send_mail = 'on';
$result = $object->valid(DolibarrApiAccess::$user, $idwarehouse, 0); 
		if ($result == 0) {
		    throw new RestException(304, 'Error nothing done. May be object is already validated');
		}
		if ($result < 0) {
		    throw new RestException(500, 'Error when validating Order: '.$object->error);
		}     
$result = $object->fetch($id);
if (!$result) {
            throw new RestException(404, 'Order not found');
        }
}
if (!$error && $object->statut == 1 && $object->billed != 1) {
$object->mode_reglement_id = $mode_reglement_id; 
$result = $object->update(DolibarrApiAccess::$user, 1);
if (!$result) {
            throw new RestException(500, $object->error);
        }
} else {
throw new RestException(400, 'Order already billed');
}

				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				{
        $hookmanager->initHooks(array('ordercard', 'globalcard'));
        
				// Define output language
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang = $object->thirdparty->default_lang;
				if (! empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}

				$ret = $object->fetch($id); // Reload to get new records
        $modelpdf = !empty($object->modelpdf)?$object->modelpdf:$conf->global->COMMANDE_ADDON_PDF;
				$object->generateDocument($modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				}
        
$ref = $object->ref;
$currency = $object->multicurrency_code;
$total = price2num($object->total_ttc);
$origin = 'order';
} elseif (preg_match('/invoice/', $modulepart)) {
$object = new Facture($this->db);
$result = $object->fetch($id);
if (!$result) {
            throw new RestException(404, 'Invoice not found');
        }
if (!$error && $object->statut == 1 && $object->paye != 1) {
$object->mode_reglement_id = $mode_reglement_id; 
$result = $object->update(DolibarrApiAccess::$user, 1);
if (!$result) {
            throw new RestException(500, $object->error);
        }
} else {
throw new RestException(400, 'Invoice already paid');
}
$paiement = $object->getSommePaiement();
$creditnotes = $object->getSumCreditNotesUsed();
$deposits = $object->getSumDepositsUsed();
$ref = $object->ref;
$currency = $object->multicurrency_code;
$total = price2num($object->total_ttc - $paiement - $creditnotes - $deposits, 'MT');
$origin = 'invoice';
$object2 = $object;
} else {
throw new RestException(400, 'Modulepart not supported yet');
}

    $result = $this->company->fetch($object->socid);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }


if (! empty($conf->stripe->enabled))
{
$stripecu = $stripe->getStripeCustomerAccount($this->company->id, $servicestatus, $site_account); // Get remote Stripe customer 'cus_...' (no remote access to Stripe here)
}

if ($id > 0 && (preg_match('/src_/', $paymentmethod) || preg_match('/tok_/', $paymentmethod))) {
      $charge = $stripe->createPaymentStripe($total, $currency, $origin, $id, $paymentmethod, $stripecu, $stripeacc, $servicestatus);
      $paiementid = $charge->id;
} elseif ($id > 0 && preg_match('/pi_/', $paymentintent)) {
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$charge = \Stripe\PaymentIntent::retrieve("$paymentintent");
		} else {
			$charge = \Stripe\PaymentIntent::retrieve("$paymentintent", array("stripe_account" => $stripeacc));
		}
      $paiementid = $paymentmethod;
} elseif ($id > 0 && preg_match('/pm_/', $paymentmethod)) {
		if (empty($stripeacc)) {				// If the Stripe connect account not set, we use common API usage
    	$charge = \Stripe\PaymentMethod::retrieve("$paymentmethod");
		} else {
			$charge = \Stripe\PaymentMethod::retrieve("$paymentmethod", array("stripe_account" => $stripeacc));
		}
      $paiementid = $paymentmethod;
} else {
$paiementid='pending';
$error++;
}

if ($error) {//|| (isset($charge->id) && $charge->statut == 'error')
$code=$charge->code;
$error++;
} elseif (!$error && preg_match('/order/', $modulepart) && $object->billed != 1) {
$object2 = new Facture($this->db);
$idinv=$object2->createFromOrder($object, DolibarrApiAccess::$user);
if ($idinv > 0)
{
  if (!empty($conf->stock->enabled) && $object2->type != Facture::TYPE_DEPOSIT && !empty($conf->global->STOCK_CALCULATE_ON_BILL)) { $idwarehouse = $conf->global->DOLICONNECT_ID_WAREHOUSE; } else { $idwarehouse = 0; }
	$object2->send_mail = 'on';
  $result=$object2->validate(DolibarrApiAccess::$user, '', $idwarehouse);
	if ($result > 0) {
// no action if OK
} else {
throw new RestException(500, $invoice->error);
	}
} else {
throw new RestException(500, $invoice->error);
} 
}

      if (!$error && !empty($total) )
      {           
$datepaye = dol_now();
$amounts = array(); 
$amounts[$object2->id] = $total;
$multicurrency_amounts=array();
//$multicurrency_amounts[$id] = $total; 
      // Creation of payment line
	    $paiement = new Paiement($this->db);
	    $paiement->datepaye     = $datepaye;
	    $paiement->amounts      = $amounts;   // Array with all payments dispatching
	    $paiement->multicurrency_amounts = $multicurrency_amounts;   // Array with all payments dispatching
      $paiement->paiementid   = $mode_reglement_id;
	    $paiement->num_payment = $charge->message;
	    $paiement->note_public  = 'Online payment '.dol_print_date($datepaye, 'standard');
      $paiement->ext_payment_id   = $paiementid;
      $paiement->ext_payment_site = $service;
      $paiement_id = $paiement->create(DolibarrApiAccess::$user, 1, $this->company);
      
	    	if ($paiement_id < 0)
	        {
//throw new RestException(500, 'erreur');
	        }
}

if (!$error && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE) && preg_match('/invoice/', $modulepart))
			{
        $hookmanager->initHooks(array('invoicecard', 'globalcard'));
      
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang)) $newlang = $object2->thirdparty->default_lang;
				if (! empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				
				$ret = $object2->fetch($invoice->id); // Reload to get new records
        $modelpdf = !empty($object2->modelpdf)?$object2->modelpdf:$conf->global->FACTURE_ADDON_PDF;
				$object2->generateDocument($modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
			}
      
	    if (!$error && $paiement_id > 0 && ! empty($conf->banque->enabled))
	    {
	    	$label='(CustomerInvoicePayment)';
	    	if (GETPOST('type') == 2) $label='(CustomerInvoicePaymentBack)';
	        $result=$paiement->addPaymentToBank(DolibarrApiAccess::$user, 'payment', $label, $conf->global->STRIPE_BANK_ACCOUNT_FOR_PAYMENTS, '', '');
	        if ($result < 0)
	        {
throw new RestException(500, $paiement->errors);
	        } 
        if (preg_match('/order/', $modulepart) && empty($conf->global->WORKFLOW_INVOICE_AMOUNT_CLASSIFY_BILLED_ORDER)) {
        $object->classifyBilled(DolibarrApiAccess::$user);
        }                     
	    }          
            return array(
            'charge' => $paiementid,
            'charge_status' => $charge->status,
            'mode_reglement_id' => $mode_reglement_id,
            'mode_reglement_code' => $mode_reglement_code,
            'status' => $object->statut,
            'ref' => $object->ref
        );
    } 
    
    } 