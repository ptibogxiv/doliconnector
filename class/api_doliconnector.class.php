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
require_once DOL_DOCUMENT_ROOT.'/core/class/ccountry.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/paiement/class/paiement.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/config.php';
require_once DOL_DOCUMENT_ROOT.'/stripe/class/stripe.class.php';
require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypal.lib.php';
require_once DOL_DOCUMENT_ROOT.'/paypal/lib/paypalfunctions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/companypaymentmode.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

        $this->company = new Societe($this->db);
        $this->invoice = new Facture($this->db);
  
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

        $doliconnector = new Daodoliconnector($this->db);
        $fk_soc = $doliconnector->getThirparty($id, '1');
        $doliconnector = new Daodoliconnector($this->db);
        $societeaccount = new SocieteAccount($this->db);
        $wdpr = $societeaccount->getCustomerAccount($fk_soc, 'wordpress', '1');
        
        if ( ! $wdpr ) {
            throw new RestException(404, 'wordpress #'.$id.' not found');
        }
	$this->company->fetch($fk_soc);
       
  if (! empty($conf->global->PRODUIT_MULTIPRICES))
{      
  $price_level=$this->company->price_level;
} 
  
  if (! empty($conf->adherent->enabled))
{  
  $member=new Adherent($this->db);
  $member->fetch('','',$this->company->id,'');
}

  if (! empty($conf->agefodd->enabled))
  { 
     $sql = "SELECT s.rowid as rowid, s.fk_soc, s.entity FROM ".MAIN_DB_PREFIX."agefodd_stagiaire as s";        
     $sql.= " WHERE s.entity IN (" . getEntity('agefodd') . ") AND s.fk_soc = '$fk_soc' ";

$result = $this->db->query($sql);
if ($result)
{
$trainee = $this->db->fetch_object($result);
} }
   
        return array(
            'fk_soc' => $fk_soc,
            'price_level' => $price_level,
            'outstanding_limit' => $this->company->outstanding_limit,
            'remise_percent' =>  $this->company->remise_percent,
            'fk_member' => $member->id,
            'member_end' => $member->datefin,             
            'fk_trainee' => $trainee->rowid, 
            'fk_user' => $member->user_id,
            'fk_order' => $doliconnector->doliconnectorder($fk_soc),
            'fk_order_nb_item' => $doliconnector->doliconnectorderitem($doliconnector->doliconnectorder($fk_soc))

        );
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
      
  if (! empty($conf->global->PRODUIT_MULTIPRICES))
{      
  $price_level=$this->company->price_level;
}

  if (! empty($conf->adherent->enabled))
{ 
  $member=new Adherent($this->db);
  $member->fetch('','',$this->company->id,'');
}

  if (! empty($conf->agefodd->enabled))
  { 
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
     * Get constante of an entity
     *
     * Return an array with entity informations
     *
     * @param     string     $id ID of entity
     * @return    array|mixed data without useless information
     *
     * @throws    RestException
     */
    function getConstante($id)
    {
        global $conf;
        return array("value" => $conf->global->$id);
    }
    
     /**
     * Get payment intent of an object
     *
     * Return an array with payment intent
     *
     * @param     string  $type Type of object (order, invoice...)
     * @param     int     $id ID of object
     * @return    array|mixed data without useless information
     *
     * @url	GET paymentintent/{type}/{id}
     *     
     * @throws    RestException
     */
    function getPaymentIntent($type, $id)
    {
        global $conf;

if (! empty($conf->stripe->enabled)) {
	$service = 'StripeTest';
	$servicestatus = 0;
  
	$publishable_key = $conf->global->STRIPE_TEST_PUBLISHABLE_KEY;  
  
	if (! empty($conf->global->STRIPE_LIVE))
	{
	$service = 'StripeLive';
	$servicestatus = 1;
    
  $publishable_key = $conf->global->STRIPE_LIVE_PUBLISHABLE_KEY; 
	}
  
if ($type == 'order')
{
	require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

	$order=new Commande($this->db);
	$result=$order->fetch($id);
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

	$fulltag='ORD='.$object->id.'.CUS='.$object->thirdparty->id;
	$tag=null;
	$fulltag=dol_string_unaccent($fulltag);  
}  
  
      $stripe = new Stripe($this->db); 
      $stripeacc = $stripe->getStripeAccount($service);
			$stripecu = null;
			if (is_object($object) && is_object($object->thirdparty)) $stripecu = $stripe->customerStripe($object->thirdparty, $stripeacc, $servicestatus, 1);

			$paymentintent=$stripe->getPaymentIntent($amount, $object->multicurrency_code, $tag, 'Stripe payment: '.$fulltag.(is_object($object)?' ref='.$object->ref:''), $object, $stripecu, $stripeacc, $servicestatus);
		}

        return $paymentintent;
    }    
    
    /**
     * List payment methods for a thirdparty
     *
     * @param 	int 	$id ID of thirdparty
     *
     * @url	GET {id}/paymentmethods
     *
     * @return int
     */
    function getListPaymentMethods($id)
    {
    global $conf, $mysoc;
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

    $result = $this->company->fetch($id);
      if( ! $result ) {
          throw new RestException(404, 'Thirdparty not found');
      }
      
      if( ! DolibarrApi::_checkAccessToResource('societe',$this->company->id)) {
        throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
      }
      
$amount_discount=$this->company->getAvailableDiscounts();

if (! empty($conf->stripe->enabled)) {
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
$customerstripe = $stripe->customerStripe($this->company, $stripeacc, $servicestatus, 1);
                                                                                               
if ($customerstripe->id) {
//$listofpaymentmethods = $stripe->getListOfPaymentMethods($this->company, $customerstripe, 'card', $stripeacc, $servicestatus);
$listofpaymentmethods = \Stripe\PaymentMethod::all(array("customer" => $customerstripe->id, "type" => "card"), array("stripe_account" => $stripeacc));
}

$list = array();

if ( $listofpaymentmethods != null ) {

foreach ( $listofpaymentmethods as $src ) {

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
$list[$src->id]['mandate_reference'] = $src->sepa_debit->mandate_reference;
$list[$src->id]['mandate_url'] = $src->sepa_debit->mandate_url;
$list[$src->id]['expiration'] =  null;
$list[$src->id]['country'] = $src->sepa_debit->country;

}

if ( ($customerstripe->invoice_settings->default_payment_method != $src->id) ) { $default = null; } else { $default="1"; }

$list[$src->id]['default_source']= $default;

} } else { $list=null; } 

$card=1;

if (!empty($conf->global->STRIPE_SEPA_DIRECT_DEBIT) && ( $this->company->isInEEC() ) ) {
$sepa=1;
}
}
 
if ($conf->global->FACTURE_RIB_NUMBER){
$bank = new Account($this->db);
$bank->fetch($conf->global->FACTURE_RIB_NUMBER);
$rib=array('IBAN' => $bank->iban,'BIC' => $bank->bic);
}
if ($conf->global->FACTURE_CHQ_NUMBER){
if ($conf->global->FACTURE_CHQ_NUMBER=='-1'){
$chq=array('proprio' => $bank->proprio,'owner_address' => $bank->owner_address);
} else {
$bank = new Account($this->db);
$bank->fetch($conf->global->FACTURE_CHQ_NUMBER);
$chq=array('proprio' => $bank->proprio,'owner_address' => $bank->owner_address);
}
}

if (! empty($conf->paypal->enabled)) {
$paypalurl=$conf->global->MAIN_MODULE_PAYPAL;
}
  
  		return array(
      'publishable_key' => $publishable_key,
      'secure_key' => $conf->global->PAYMENT_SECURITY_TOKEN,
      'code_account' => $stripeacc,
      'code_client' => $customerstripe->id,
      'com_countrycode' => getCountry($mysoc->country_code,2),
      'cus_countrycode' => $this->company->country_code,
			'paymentmethods' => $list,
      'discount' => $amount_discount,
      'card' => $card,
      'sepa_direct_debit' => $sepa,
      'payment_request_api' => $sepa,
      'RIB' => $rib,
      'CHQ' => $chq,
      'stripe' => $servicestatus,
      'paypal' => $paypalurl
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
	if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha'))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

$customerstripe=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);  

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
	if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha'))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

$customerstripe=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);  

$payment_method = \Stripe\PaymentMethod::retrieve($method, ["stripe_account" => $stripeacc]);

if ($payment_method && $customerstripe) {
$result = $payment_method->attach(['customer' => $customerstripe->id]);
}

if ($default) {
$customerstripe=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
$customerstripe->invoice_settings->default_payment_method = (string) $method;
$result = $customerstripe->save();
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
	if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha'))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);
}

if ($default) {
$customerstripe=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
$customerstripe->invoice_settings->default_payment_method = (string) $method;
$result = $customerstripe->save();
}
  
return $result;
    }
    

      /**
     * Pay an object
     *
     * @param int $id               ID of thirdparty
     * @param string  $object         Type of object to pay 
     * @param int   $item         Id of object to pay
     * @param string $source         Source {@from body}
     * @param string $url         Return_url {@from body}
     * @return int  ID of subscription
     *
     * @url POST {id}/pay/{object}/{item}
     */
    function paySource($id, $object, $item, $source, $url)
    {
    global $langs,$conf;
      if(! DolibarrApiAccess::$user->rights->societe->creer) {
        throw new RestException(401);
      }

$result = $this->company->fetch($id);
// PDF
$hidedetails = (GETPOST('hidedetails', 'int') ? GETPOST('hidedetails', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0));
$hidedesc = (GETPOST('hidedesc', 'int') ? GETPOST('hidedesc', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0));
$hideref = (GETPOST('hideref', 'int') ? GETPOST('hideref', 'int') : (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0));
 
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
$stripecu = $stripe->customerStripe($this->company, $stripeacc, $servicestatus, 1)->id;

$pos=strpos($source,'src_');

if ($pos !== false) {
$src = \Stripe\Source::retrieve("$source",array("stripe_account" => $stripeacc));
}
else {
$src = \Stripe\Source::create(array(
  "type" => "card",
  "token" => $source
),array("stripe_account" => $stripeacc));
$source=$src->id;
}
}

if ($object=='orders') {
$order=new Commande($this->db);
$order->fetch($item);
if ($order->statut==0 && $order->billed!=1) {
$order->valid(DolibarrApiAccess::$user,0,0); // id warehouse to change !!!!!!       
$order->fetch($item);
}
if ($order->statut==1&&$order->billed!=1) {
if ($src->type=='card'){
$order->mode_reglement_id='6';
}
elseif ($src->type=='sepa_debit'){
$order->mode_reglement_id='3';
}
$order->update(DolibarrApiAccess::$user,1);
}
else {
$idref=0;
$msg="order already billed";
$error++;
}
				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				{
				// Define output language
				$outputlangs = $langs;
				$newlang = GETPOST('lang_id', 'alpha');
				if ($conf->global->MAIN_MULTILANGS && empty($newlang))
					$newlang = $order->thirdparty->default_lang;
				if (! empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}

				$ret = $order->fetch($order->id); // Reload to get new records
				$order->generateDocument($order->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
				}
        
$ref=$order->ref;
$currency=$order->multicurrency_code;
$total=price2num($order->total_ttc);
$origin='order';
}
elseif ($object=='invoice') {
$invoice = new Facture($this->db);
$invoice->fetch($item);
$paiement = $invoice->getSommePaiement();
$creditnotes=$invoice->getSumCreditNotesUsed();
$deposits=$invoice->getSumDepositsUsed();
$ref=$invoice->ref;
$ifverif=$invoice->socid;
$currency=$invoice->multicurrency_code;
$total=price2num($invoice->total_ttc - $paiement - $creditnotes - $deposits,'MT');
$origin='invoice';
}

if ($item>0)
{
if ($src->object=='source' && $src->type=='card' && isset($src->card->three_d_secure) && (($src->card->three_d_secure=='required') OR ($src->card->three_d_secure=='recommended') OR ($src->card->three_d_secure=='optional' && !empty($conf->global->STRIPE_USE_3DSECURE)))) {

		$arrayzerounitcurrency=array('BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF');
		if (! in_array($currency, $arrayzerounitcurrency)) $stripeamount=$stripeamount * 100;
    
$description = "ORD=" . $ref . ".CUS=" . $id.".PM=stripe";
		$metadata = array(
			'dol_id' => "" . $item . "",
			'dol_type' => "" . $origin . "",
			'dol_thirdparty_id' => "" . $id . "",
      'FULLTAG' => $description,
      'dol_thirdparty_name' => $this->company->name,
			'dol_version'=>DOL_VERSION,
			'dol_entity'=>$conf->entity,
			'ipaddress'=>(empty($_SERVER['REMOTE_ADDR'])?'':$_SERVER['REMOTE_ADDR'])
		);
    
$src2 = \Stripe\Source::create(array(
  "amount" => price2num($stripeamount, 'MU'),
  "currency" => "$currency",
  "type" => "three_d_secure",
  "three_d_secure" => array(
    "card" => "$source",
  ),
  "metadata" => $metadata,
  "redirect" => array(
    "return_url" => "$url&ref=$ref&statut=pending"
  ),
),array("stripe_account" => $stripe->getStripeAccount($service)));

if ($src2->three_d_secure->authenticated==false && $src2->redirect->status=='succeeded') {

$charge=$stripe->createPaymentStripe($total,$currency,$origin,$item,$source,$stripecu,$stripeacc,$servicestatus);
$redirect_url=$url."&ref=$ref&statut=".$charge->statut;

} else {

$redirect_url=$src2->redirect->url;
$error++;

}

} else {

$charge=$stripe->createPaymentStripe($total,$currency,$object,$item,$source,$stripecu,$stripeacc,$servicestatus);
$redirect_url=$url."&ref=$ref&statut=".$charge->statut;	

}
} 

if (isset($charge->id) && $charge->statut=='error'){

$msg=$charge->message;
$code=$charge->code;
$error++;

} elseif (isset($charge->id) && $charge->statut=='success' && $object=='order') {
$invoice = new Facture($this->db);
$idinv=$invoice->createFromOrder($order,DolibarrApiAccess::$user);
if ($idinv > 0)
{
	// Change status to validated
	$result=$invoice->validate(DolibarrApiAccess::$user);
	if ($result > 0) {
$invoice->fetch($idinv);
$paiement = $invoice->getSommePaiement();
$creditnotes=$invoice->getSumCreditNotesUsed();
$deposits=$invoice->getSumDepositsUsed();
$ref=$invoice->ref;
$ifverif=$invoice->socid;
$currency=$invoice->multicurrency_code;
$total=price2num($invoice->total_ttc - $paiement - $creditnotes - $deposits,'MT');
} else {
$msg=$invoice->error; 
$error++;
	}
} else {
$msg=$invoice->error;
$error++;
} 
}

      if (!$error)
      {           
$datepaye = dol_now();
$paiementcode ="CB"; 
$amounts=array(); 
$amounts[$invoice->id] = $total;
$multicurrency_amounts=array();
//$multicurrency_amounts[$item] = $total; 
      // Creation of payment line
	    $paiement = new Paiement($this->db);
	    $paiement->datepaye     = $datepaye;
	    $paiement->amounts      = $amounts;   // Array with all payments dispatching
	    $paiement->multicurrency_amounts = $multicurrency_amounts;   // Array with all payments dispatching
      $paiement->paiementid   = dol_getIdFromCode($this->db,$paiementcode,'c_paiement','code','id',1);
	    $paiement->num_paiement = $charge->message;
	    $paiement->note         = '';
      $paiement->ext_payment_id   = $charge->id;
      $paiement->ext_payment_site = $service;
}
      if (! $error)
	    {
	    $paiement_id=$paiement->create(DolibarrApiAccess::$user, 0);
  
if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE) && count($invoice->lines))
			{
				$outputlangs = $langs;
				$newlang = '';
				if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id','aZ09')) $newlang = GETPOST('lang_id','aZ09');
				if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $invoice->thirdparty->default_lang;
				if (! empty($newlang)) {
					$outputlangs = new Translate("", $conf);
					$outputlangs->setDefaultLang($newlang);
				}
				$model=$invoice->modelpdf;
				$ret = $invoice->fetch($invoice->id); // Reload to get new records

				$invoice->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref);

			}         
	    	if ($paiement_id < 0)
	        {
	            $msg=$paiement->errors;
	            $error++;
	        }else{ 
        if ($object=='order') {
        $order->classifyBilled(DolibarrApiAccess::$user);
        }        
          }
	    }
      
	    if (! $error && ! empty($conf->banque->enabled))
	    {
	    	$label='(CustomerInvoicePayment)';
	    	if (GETPOST('type') == 2) $label='(CustomerInvoicePaymentBack)';
	        $paiement->addPaymentToBank(DolibarrApiAccess::$user, 'payment', $label, $conf->global->STRIPE_BANK_ACCOUNT_FOR_PAYMENTS, '', '');
	        if ($result < 0)
	        {
	            $msg=$paiement->errors;
	            $error++;
	        } 
$invoice->set_paid(DolibarrApiAccess::$user);                    
	    }          
            return array(
            'charge' => $charge->id,
            'statut' => $charge->statut,
            'redirect_url' => $redirect_url,
            'code' => $code,
            'message' => $msg
        );
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
	if (! empty($conf->global->STRIPE_LIVE) && ! GETPOST('forcesandbox','alpha'))
	{
		$service = 'StripeLive';
		$servicestatus = 1;
	}

	$stripe = new Stripe($this->db);
	$stripeacc = $stripe->getStripeAccount($service);								// Get Stripe OAuth connect account (no network access here)
}

				if (preg_match('/pm_/', $method))
				{
            $payment_method = \Stripe\PaymentMethod::retrieve($method, ["stripe_account" => $stripeacc]);
            if ($payment_method)
				    {
					  $payment_method->detach();
				    }
        }
        else
				{
				$cu=$stripe->customerStripe($this->company, $stripeacc, $servicestatus);
				$card=$cu->sources->retrieve("$method");
				if ($card)
				{
					// $card->detach();  Does not work with card_, only with src_
					if (method_exists($card, 'detach')) $card->detach();
					else $card->delete();
				}
        }
                                                                       
        return array(
            'success' => array(
                'code' => 200,
                'message' => 'Payment method deleted'
            )
        );
    
    }
    
    } 