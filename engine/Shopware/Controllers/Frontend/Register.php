<?php
/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    Shopware_Controllers
 * @subpackage Frontend
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author     Stefan Hamann
 * @author     Heiner Lohaus
 * @author     $Author$
 */

/**
 * Register controller
 *
 * todo@all: Documentation
 */
class Shopware_Controllers_Frontend_Register extends Enlight_Controller_Action
{
	protected $session;
	protected $admin;
	protected $system;
	protected $post;
	protected $error;

	/**
	 * Calls when the controller will be initialized
	 *
	 * @return void
	 */
	public function init()
	{
		$this->session = Shopware()->Session();
		$this->admin = Shopware()->Modules()->Admin();
		$this->system = Shopware()->Modules()->System();
		$this->post = $this->request->getParam('register');
	}

	/**
	 * Will be called from the dispatcher before an action is processed
	 *
	 * @return void
	 */
	public function preDispatch()
	{
        $this->View()->setScope(Enlight_Template_Manager::SCOPE_PARENT);

		if(!isset($this->View()->register)) {
			$this->View()->register = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		
		if(!isset($this->session['sRegister'])) {
			$this->session['sRegister'] = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}

		if(in_array($this->Request()->getActionName(), array('ajax_validate_password', 'ajax_validate_billing', 'ajax_validate_email'))) {
			Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
		}
	}


	/**
	 * Will be called when no action is supplied
	 *
	 * @return void
	 */
	public function indexAction()
	{
		if(!empty($this->session['sUserId']))
		{
			if ($this->request->getParam('sValidation')||!Shopware()->Modules()->Basket()->sCountBasket()) {
				return $this->forward('index', 'account');
			} else {
				return $this->forward('confirm', 'checkout');
			}
		}
		$skipLogin = $this->request->getParam('skipLogin');
		if ($skipLogin=="1"){
			$this->View()->skipLogin = $skipLogin;
		}
		$this->personalAction();
		$this->billingAction();
		$this->shippingAction();
		$this->paymentAction();
	}

	/**
	 * Checks the registration
	 *
	 * @return void
	 */
	public function saveRegisterAction()
	{
		if($this->request->isPost())
		{
			$this->savePersonalAction();
			$this->saveBillingAction();
			if(!empty($this->post['billing']['shippingAddress']))
			{
				$this->saveShippingAction();
			}
			if(isset($this->post['payment']))
			{
				$this->savePaymentAction();
			}
			if(empty($this->error))
			{	
				$this->saveRegister();
			}
		}
		$this->forward('index');
	}

	/**
	 * Saves the registration
	 * 
	 * @return void
	 */
	public function saveRegister()
	{
		$paymentData = isset($this->session['sRegister']['payment']['object']) ? $this->session['sRegister']['payment']['object'] : false;
				
		$this->admin->sSaveRegister();

		if(!empty($paymentData))
		{
			$paymentObject = $this->admin->sInitiatePaymentClass($paymentData);
			$this->admin->sSYSTEM->_POST = $this->request->getPost();
			if (!empty($paymentObject)&&method_exists($paymentObject,'sInit'))
			{
				$checkPayment = $paymentObject->sInit(Shopware()->System());
			}
			if (!empty($paymentObject)&&method_exists($paymentObject,'sUpdate'))
			{
				$paymentObject->sUpdate();
			}
		}
	}

	/**
	 * Returns the personal information and validates it
	 *
	 * @return void
	 */
	public function personalAction()
	{
		if(!isset($this->View()->register->personal))
		{
			$this->View()->register->personal = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!isset($this->View()->register->personal->form_data))
		{
			$this->View()->register->personal->form_data = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
				
		if (!empty($this->session['sRegister']['auth']))
		foreach ($this->session['sRegister']['auth'] as $key => $value)
		{
			if(!isset($this->View()->register->personal->form_data->$key))
			{
				$this->View()->register->personal->form_data->$key = $value;
			}
		}
		
		if (!empty($this->session['sRegister']['billing']))
		foreach ($this->session['sRegister']['billing'] as $key => $value)
		{
			if(!isset($this->View()->register->personal->form_data->$key))
			{
				$this->View()->register->personal->form_data->$key = $value;
			}
		}
		
		if($this->request->getParam('sValidation'))
		{
			// For new b2bessentials plugin (replacement for customergroup module), do validation of this parameter
			$sValidation = $this->request->getParam('sValidation');
			// Simply check if this customergroup is valid
			if (Shopware()->Db()->fetchOne("SELECT id FROM s_core_customergroups WHERE `groupkey` = ? ",array($sValidation))){
				// New event to do further validations in b2b customergroup plugin
				if(!Enlight()->Events()->notifyUntil('Shopware_Controllers_Frontend_Register_CustomerGroupRegister', array('subject'=>$this,'sValidation'=>$sValidation))){
					$this->View()->register->personal->form_data->sValidation = $sValidation;
				}
			}else {
				throw new Enlight_Exception("Invalid customergroup");
			}
		}
	}

	/**
	 * Saves and validates the personal information
	 *
	 * @return void
	 */
	public function savePersonalAction()
	{
		if(!isset($this->View()->register->personal))
		{
			$this->View()->register->personal = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		
		if(!empty($this->post['personal']))
		{
			$this->View()->register->personal->form_data = new ArrayObject($this->post['personal'], ArrayObject::ARRAY_AS_PROPS);
		}
		
		$checkData = $this->validatePersonal();
		if (!empty($checkData['sErrorMessages']))
		{
			foreach ($checkData['sErrorMessages'] as $key=>$error_message) {
				$checkData['sErrorMessages'][$key] = $this->View()->fetch('string:'.$error_message);
			}
			$this->error = true;
			$this->View()->register->personal->error_flags = new ArrayObject($checkData['sErrorFlag'], ArrayObject::ARRAY_AS_PROPS);
			$this->View()->register->personal->error_messages = new ArrayObject($checkData['sErrorMessages'], ArrayObject::ARRAY_AS_PROPS);
		}
	}

	/**
	 * Returns the billing information and validates it
	 *
	 * @return void
	 */
	public function billingAction()
	{
		if(!isset($this->View()->register->billing))
		{
			$this->View()->register->billing = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!isset($this->View()->register->billing->form_data))
		{
			$this->View()->register->billing->form_data = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}

        $getCountryList =  $this->admin->sGetCountryList();

		$this->View()->register->billing->country_list = $getCountryList;

		if(!empty($this->session['sRegister']['billing']))
		foreach ($this->session['sRegister']['billing'] as $key => $value)
		{
			if(!isset($this->View()->register->billing->form_data->$key))
			{
				$this->View()->register->billing->form_data->$key = $value;
			}
		}
		
		if(!empty($this->session['sCountry'])&&empty($this->View()->register->billing->form_data->country)) {
			$this->View()->register->billing->form_data->country = $this->session['sCountry'];
		}
	}

	/**
	 * Saves and validates the billing information
	 *
	 * @return void
	 */
	public function saveBillingAction()
	{
		if(!isset($this->View()->register->billing))
		{
			$this->View()->register->billing = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!empty($this->post['billing']))
		{

			$this->View()->register->billing->form_data = new ArrayObject($this->post['billing'], ArrayObject::ARRAY_AS_PROPS);
			if(!empty($this->View()->register->billing->form_data['ustid']))
			{
				$this->View()->register->billing->form_data['ustid'] = preg_replace('#[^0-9A-Z\+\*\.]#','',strtoupper($this->View()->register->billing->form_data['ustid']));
			}
		}

		$checkData = $this->validateBilling();

		if (!empty($checkData['sErrorMessages']))
		{
			$this->error = true;
			$this->View()->register->billing->error_flags = new ArrayObject($checkData['sErrorFlag'], ArrayObject::ARRAY_AS_PROPS);
			$this->View()->register->billing->error_messages = new ArrayObject($checkData['sErrorMessages'], ArrayObject::ARRAY_AS_PROPS);
		}
	}

	/**
	 * Returns the shipping information and validates it
	 *
	 * @return void
	 */
	public function shippingAction()
	{
		if(!isset($this->View()->register->shipping))
		{
			$this->View()->register->shipping = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!isset($this->View()->register->shipping->form_data))
		{
			$this->View()->register->shipping->form_data = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		
		$this->View()->register->shipping->country_list = $this->admin->sGetCountryList();
		if(!empty($this->session['sRegister']['shipping']))
		foreach ($this->session['sRegister']['shipping'] as $key => $value)
		{
			$this->View()->register->shipping->form_data[$key] = $value;
		}
	}

	/**
	 * Saves and validates the shipping information
	 *
	 * @return void
	 */
	public function saveShippingAction()
	{
		if(!isset($this->View()->register->shipping))
		{
			$this->View()->register->shipping = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!empty($this->post['shipping']))
		{
			$this->View()->register->shipping->form_data = new ArrayObject($this->post['shipping'], ArrayObject::ARRAY_AS_PROPS);
		}

		$checkData = $this->validateShipping();
		
		if (!empty($checkData['sErrorMessages']))
		{
			$this->error = true;
			$this->View()->register->shipping->error_flags = new ArrayObject($checkData['sErrorFlag'], ArrayObject::ARRAY_AS_PROPS);
			$this->View()->register->shipping->error_messages = new ArrayObject($checkData['sErrorMessages'], ArrayObject::ARRAY_AS_PROPS);
		}
	}

	/**
	 * Returns the payment information and validates it
	 *
	 * @return void
	 */
	public function paymentAction()
	{
		if(!isset($this->View()->register->payment))
		{
			$this->View()->register->payment = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		
		if(!isset($this->View()->register->payment->form_data))
		if(!empty($this->session['sPayment']))
		{
			$this->View()->register->payment->form_data = array('payment'=>$this->session['sPayment']);
		}
		else
		{
			$this->View()->register->payment->form_data = array('payment'=>Shopware()->Config()->get('DefaultPayment'));
		}
		
		$this->View()->register->payment->payment_means = $this->admin->sGetPaymentMeans();
		
		if(!empty($this->session['sRegister']['shipping']))
		foreach ($this->session['sRegister']['shipping'] as $key => $value)
		{
			$this->View()->form_data->register['shipping'][$key] = $value;
		}
	}

	/**
	 * Saves and validates the payment information
	 *
	 * @return void
	 */
	public function savePaymentAction()
	{
		if(!isset($this->View()->register->payment))
		{
			$this->View()->register->payment = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
		}
		if(!empty($this->post['payment']))
		{
			$this->View()->register->payment->form_data = $this->request->getPost();
			$this->View()->register->payment->form_data['payment'] = $this->post['payment'];
		}
		$this->admin->sSYSTEM->_POST = $this->request->getPost();
		$checkData = $this->validatePayment();
		if(!empty($checkData['sErrorMessages']))
		{
			$this->error = true;
			$this->View()->register->payment->error_flags = new ArrayObject($checkData['sErrorFlag'], ArrayObject::ARRAY_AS_PROPS);
			$this->View()->register->payment->error_messages = new ArrayObject($checkData['sErrorMessages'], ArrayObject::ARRAY_AS_PROPS);
		}
		else
		{
			$this->session['sRegister']['payment'] = array('object'=>$checkData['paymentData']);
		}
	}

	/**
	 * Validates the personal informations
	 *
	 * @return array - personal data with error flags and msg
	 */
	public function validatePersonal()
	{
		$this->admin->sSYSTEM->_POST = $this->post['personal'];
		
		$result = array();
		
		$checkData = $this->admin->sValidateStep1();
		if(!empty($checkData['sErrorMessages']))
		{
			$result = $checkData;
		}
		
		$rules = array(
			'customer_type'=>array('required'=>0),
			'salutation'=>array('required'=>1),
			//'company'=>array('addicted'=>array('salutation'=>'company'),'required'=>1),
			'firstname'=>array('required'=>1),
			'lastname'=>array('required'=>1),
			//'street'=>array('required'=>1),
			//'streetnumber'=>array('required'=>1),
			//'zipcode'=>array('required'=>1),
			//'city'=>array('required'=>1),
			'phone'=>array('required'=>1),
			//'country'=>array('required'=>1),
			//'department'=>array('required'=>0),
			'fax'=>array('required'=>0),
			//'shippingAddress'=>array('required'=>0),
			//'ustid'=>array('required'=>0),
			'text1'=>array('required'=>0),
			'text2'=>array('required'=>0),
			'text3'=>array('required'=>0),
			'text4'=>array('required'=>0),
			'text5'=>array('required'=>0),
			'text6'=>array('required'=>0),
			'sValidation'=>array('required'=>0),
			'birthyear'=>array('required'=>0),
			'birthmonth'=>array('required'=>0),
			'birthday'=>array('required'=>0),
			'dpacheckbox'=>array('required'=>(Shopware()->Config()->get('ACTDPRCHECK'))?1:0)
		);
		$rules = Enlight()->Events()->filter('Shopware_Controllers_Frontend_Register_validatePersonal_FilterRules', $rules, array('subject'=>$this));

		$checkData = $this->admin->sValidateStep2($rules);
				
		if(!empty($checkData['sErrorMessages']))
		{
			$result = array_merge_recursive($result, $checkData);
		}
		
		return $result;
	}

	/**
	 * Validates the billing informations
	 *
	 * @return array - billing data with error flags and msg
	 */
	public function validateBilling()
	{
		$rules = array(
			//'salutation'=>array('required'=>1),
			'company'=>array('required'=>0),
			//'firstname'=>array('required'=>1),
			//'lastname'=>array('required'=>1),
			'street'=>array('required'=>1),
			'streetnumber'=>array('required'=>1),
			'zipcode'=>array('required'=>1),
			'city'=>array('required'=>1),
			//'phone'=>array('required'=>1),
			'country'=>array('required'=>1),
			'department'=>array('required'=>0),
			//'fax'=>array('required'=>0),
			'shippingAddress'=>array('required'=>0),
			//'ustid'=>array('required'=>0),
			//'text1'=>array('required'=>0),
			//'text2'=>array('required'=>0),
			//'text3'=>array('required'=>0),
			//'text4'=>array('required'=>0),
			//'text5'=>array('required'=>0),
			//'text6'=>array('required'=>0),
			//'sValidation'=>array('required'=>0),
			//'birthyear'=>array('required'=>0),
			//'birthmonth'=>array('required'=>0),
			//'birthday'=>array('required'=>0),
			//'dpacheckbox'=>array('required'=>Shopware()->Config()->get('sACTDPRCHECK'))?1:0
		);

        // Check if state selection is required
        if (!empty($this->post["billing"]["country"])){

            $stateSelectionRequired = Shopware()->Db()->fetchRow("
            SELECT display_state_in_registration, force_state_in_registration
            FROM s_core_countries WHERE id = ?
            ",array($this->post["billing"]["country"]));
            if ($stateSelectionRequired["display_state_in_registration"] == true && $stateSelectionRequired["force_state_in_registration"] == true){
                $rules["stateID"] = array("required" => true);
            }else {
                $rules["stateID"] = array("required" => false);
            }

            $this->post["billing"]["stateID"] = $this->post["billing"]["country_state_".$this->post["billing"]["country"]];

            unset($this->post["billing"]["country_state_".$this->post["billing"]["country"]]);
        }

        if(!empty($this->post['personal']['sValidation'])) {
            $this->post['personal']['customer_type'] = 'business';
        }

		if(!empty($this->post['personal']['customer_type']) && $this->post['personal']['customer_type'] == 'business') {
			$rules['company'] = array('required'=>1);
			$rules['ustid'] = array('required'=>(Shopware()->Config()->vatCheckRequired && Shopware()->Config()->vatCheckEndabled));
		}
		$rules = Enlight()->Events()->filter('Shopware_Controllers_Frontend_Register_validateBilling_FilterRules', $rules, array('subject'=>$this));

		$this->admin->sSYSTEM->_POST = $this->post['billing'];

		$checkData = $this->admin->sValidateStep2($rules, false);

		if(empty($checkData['sErrorMessages'])) {
			$this->session['sCountry'] = (int) $this->session['sRegister']['billing']['country'];
		}


		return $checkData;
	}

	/**
	 * Validates the shipping informations
	 *
	 * @return array - shipping data with error flags and msg
	 */
	public function validateShipping()
	{
		$rules = array(
			'salutation'=>array('required'=>1),
			'company'=>array('required'=>0),
			'firstname'=>array('required'=>1),
			'lastname'=>array('required'=>1),
			'street'=>array('required'=>1),
			'streetnumber'=>array('required'=>1),
			'zipcode'=>array('required'=>1),
			'city'=>array('required'=>1),
			'department'=>array('required'=>0),
			'text1'=>array('required'=>0),
			'text2'=>array('required'=>0),
			'text3'=>array('required'=>0),
			'text4'=>array('required'=>0),
			'text5'=>array('required'=>0),
			'text6'=>array('required'=>0),
			'country'=>array('required'=>(Shopware()->Config()->get('sCOUNTRYSHIPPING'))?1:0)
		);

        // Check if state selection is required
        if (!empty($this->post["shipping"]["country"]) && Shopware()->Config()->get('sCOUNTRYSHIPPING') == true){
           $stateSelectionRequired = Shopware()->Db()->fetchRow("
           SELECT display_state_in_registration, force_state_in_registration
           FROM s_core_countries WHERE id = ?
           ",array($this->post["shipping"]["country"]));
            if ($stateSelectionRequired["display_state_in_registration"] == true && $stateSelectionRequired["force_state_in_registration"] == true){
                 $rules["stateID"] = array("required" => true);
             }else {
                 $rules["stateID"] = array("required" => false);
             }
             $this->post["shipping"]["stateID"] = $this->post["shipping"]["country_shipping_state_".$this->post["shipping"]["country"]];
             unset($this->post["shipping"]["country_shipping_state_".$this->post["shipping"]["country"]]);
        }

		$rules = Enlight()->Events()->filter('Shopware_Controllers_Frontend_Register_validateShipping_FilterRules', $rules, array('subject'=>$this));

		$this->admin->sSYSTEM->_POST = $this->post['shipping'];	
			
		$checkData = $this->admin->sValidateStep2ShippingAddress($rules);

		return $checkData;
	}

	/**
	 * Validates the payment informations
	 *
	 * @return array - payment data with error flags and msg
	 */
	public function validatePayment()
	{
		if(empty($this->post['payment']))
		{
			return array(
				'sErrorFlag' => array('payment'),
				'sErrorMessages' => array(Shopware()->Snippets()->getSnippet()->get('sErrorBillingAdress')),
			);
		}
		$this->admin->sSYSTEM->_POST['sPayment'] = $this->post['payment'];
				
		$checkData = $this->admin->sValidateStep3();
		
		if(!empty($checkData['checkPayment']['sErrorMessages']))
		{
			return array(
				'sErrorFlag' => $checkData['checkPayment']['sErrorFlag'],
				'sErrorMessages' => $checkData['checkPayment']['sErrorMessages'],
			);
		}
		return $checkData;
	}

	/**
	 * Checks if the given email isn't already registered
	 */
	public function ajaxValidateEmailAction()
	{
		$error_flags = array();
		$error_messages = array();
		
		if (empty($this->post['personal']['email'])) {
			
		} elseif (($validator = new Zend_Validate_EmailAddress()) && !$validator->isValid($this->post['personal']['email'])) {
			$error_messages[] = Shopware()->Snippets()->getNamespace("frontend")->get('RegisterAjaxEmailNotValid', 'Please enter a valid mail address.', true);
			$error_flags['email'] = true;
			if(!empty($this->post['personal']['emailConfirmation'])) {
				$error_flags['emailConfirmation'] = true;
			}
		} elseif(empty($this->post['personal']['skipLogin'])&&$this->admin->sGetUserByMail($this->post['personal']['email'])) {
			$error_messages[] = Shopware()->Snippets()->getNamespace("frontend")->get('RegisterAjaxEmailForgiven', 'This mail address is already in use.', true);
			$error_flags['email'] = true;
			if(!empty($this->post['personal']['emailConfirmation'])) {
				$error_flags['emailConfirmation'] = true;
			}
		} elseif (empty($this->post['personal']['emailConfirmation'])) {
			$error_flags['email'] = false;
		} elseif($this->post['personal']['emailConfirmation']!=$this->post['personal']['email']) {
			$error_messages[] = Shopware()->Snippets()->getNamespace("frontend")->get('RegisterAjaxEmailNotEqual', 'The mail addresses you have entered are not equal.', true);
			$error_flags['email'] = true;
			$error_flags['emailConfirmation'] = true;
		} else {
			$error_flags['email'] = false;
			$error_flags['emailConfirmation'] = false;
		}
		
		foreach ($error_messages as $key=>$error_message) {
			$error_messages[$key] = utf8_encode($this->View()->fetch('string:'.$error_message));
		}
		
		echo Zend_Json::encode(array('success'=>empty($error_messages), 'error_flags'=>$error_flags, 'error_messages'=>$error_messages));
	}

	/**
	 * Checks if the two passwords matches
	 */
	public function ajaxValidatePasswordAction()
	{
		$error_messages = array();
		$error_flags = array();
		
		if(empty($this->post['personal']['password'])) {
			
		} elseif (strlen(utf8_decode($this->post['personal']['password'])) < Shopware()->Config()->get('MinPassword')){
			$error_messages[] = Shopware()->Snippets()->getNamespace("frontend")->get(
				'RegisterPasswordLength',
				'Please choose a password consisting of {config name="MinPassword"} signs at minimum.',
				true
			);
			$error_flags['password'] = true;
			if(!empty($this->post['personal']['passwordConfirmation'])) {
				$error_flags['passwordConfirmation'] = true;
			}
		} elseif(empty($this->post['personal']['passwordConfirmation'])) {
			$error_flags['password'] = false;
		} elseif (!empty($this->post['personal']['passwordConfirmation']) && $this->post['personal']['password']!=$this->post['personal']['passwordConfirmation'])  {
			$error_messages[] = Shopware()->Snippets()->getNamespace("frontend")->get('RegisterPasswordNotEqual', 'The passwords you have entered are not equal.', true);
			$error_flags['password'] = true;
			$error_flags['passwordConfirmation'] = true;
		} else {
			$error_flags['password'] = false;
			$error_flags['passwordConfirmation'] = false;
		}
		
		foreach ($error_messages as $key=>$error_message) {
			$error_messages[$key] = utf8_encode($this->View()->fetch('string:'.$error_message));
		}
		
		echo Zend_Json::encode(array('success'=>empty($error_messages), 'error_flags'=>$error_flags, 'error_messages'=>$error_messages));
	}

	/**
	 * Validates the billing informations
	 * and returns an json string with error
	 * codes and messages
	 *
	 * @return void
	 */
	public function ajaxValidateBillingAction()
	{
		$rules = array(
			'salutation'=>array('required'=>1),
			'company'=>array('required'=>0),
			'firstname'=>array('required'=>1),
			'lastname'=>array('required'=>1),
			'street'=>array('required'=>1),
			'streetnumber'=>array('required'=>1),
			'zipcode'=>array('required'=>1),
			'city'=>array('required'=>1),
			'country'=>array('required'=>1),
			'department'=>array('required'=>0),
		);
		if(!empty($this->post['personal']['customer_type'])&&$this->post['personal']['customer_type']=='business')
		{
			$rules['company']['required'] = 1;
		}
		$this->admin->sSYSTEM->_POST = array_merge($this->post['personal'], $this->post['billing']);
		$checkData = $this->admin->sValidateStep2($rules);

		$error_messages = array();
		$error_flags = array();
		
		if(!empty($checkData['sErrorMessages'])) {
			foreach ($checkData['sErrorMessages'] as $error_message) {
				$error_messages[] = utf8_encode($error_message);
			}
		}
		
		foreach ($rules as $field => $rule) {
			$error_flags[$field] = !empty($checkData['sErrorFlag'][$field]);
		}
		
		echo Zend_Json::encode(array('success'=>empty($error_messages), 'error_flags'=>$error_flags, 'error_messages'=>$error_messages));
	}
}