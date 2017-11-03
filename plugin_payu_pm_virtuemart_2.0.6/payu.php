<?php
defined('_JEXEC') or die('Restricted access');

/****

 */
if (!class_exists('vmPSPlugin'))
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');

class plgVmPaymentPayu extends vmPSPlugin {

    // instance of class
    public static $_this = false;

    function __construct(& $subject, $config) {
		//if (self::$_this)
		//   return self::$_this;
		parent::__construct($subject, $config);
	
		$this->_loggable = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		$this->_tablepkey = 'id'; 
		$this->_tableId = 'id'; 
		$varsToPush = array(
			'salt' => array('','int'),
			'merchantkey' => array('','char'),
			'mode' => array('','char'),
			'description' => array('','text'),
		    'payment_logos' => array('', 'char'),
			'payment_currency' => array('', 'int'),
		    'status_pending' => array('', 'char'),
		    'status_success' => array('', 'char'),
		    'status_canceled' => array('', 'char'),
		    'countries' => array('', 'char'),
		    'min_amount' => array('', 'int'),
		    'max_amount' => array('', 'int'),
		    'secure_post' => array('', 'int'),
		    'ipn_test' => array('', 'int'),
		    'no_shipping' => array('', 'int'),
		    'address_override' => array('', 'int'),
		    'cost_per_transaction' => array('', 'int'),
		    'cost_percent_total' => array('', 'int'),
		    'tax_id' => array(0, 'int')
		);
	
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	
		//self::$_this = $this;
    }
    
 	public function getVmPluginCreateTableSQL() {
		return $this->createTableSQL('Payment PAYU Table');
    }
    
	function getTableSQLFields() {
		$SQLfields = array(
		    'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		    'virtuemart_order_id' => 'int(1) UNSIGNED',
		    'order_number' => ' char(64)',
		    'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
		    'payment_name' => 'varchar(5000)',
			'payu_custom' => ' varchar(255)',
		    'amount' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'status' => 'varchar(225)',
			'mode'=> 'varchar(225)',
			'mihpayid' => 'int(11)',
			'productinfo' => 'text',
			'mihpayid' => 'int(21)',
			'txnid' => 'varchar(29)',
		);
	return $SQLfields;
	}
	
	function plgVmConfirmedOrder($cart, $order) {		
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
		    return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
		    return false;
		}
		$session = JFactory::getSession();
		$return_context = $session->getId();
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists('VirtueMartModelOrders'))
		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		if (!class_exists('VirtueMartModelCurrency'))
		    require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');		
		    
		
		//$usr = JFactory::getUser();
		$new_status = '';	
		$usrBT = $order['details']['BT'];
		$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists('TableVendors'))
		    require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		$vendorModel = VmModel::getModel('Vendor');
		$vendorModel->setId(1);
		$vendor = $vendorModel->getVendor();
		$vendorModel->addImages($vendor, 1);
		/*$this->getPaymentCurrency($method);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
	
		$paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
		$cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
		if ($totalInPaymentCurrency <= 0) {
		     vmInfo(JText::_('VMPAYMENT_PAYU_PAYMENT_AMOUNT_INCORRECT'));
			    return false;
		}*/
		$salt = $this->_getMerchantSalt($method);
		if (empty($salt)) {
		    vmInfo(JText::_('VMPAYMENT_PAYU_MERCHANT_SALT_NOT_SET'));
		    return false;
		}
		$merchentkey = $method->merchantkey;
		$mode = $method->mode;
		$return_url = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id.'&DR={DR}');
		$description = $method->description;
		$ship_address = $address->address_1;
                $txnid = $order['details']['BT']->order_number;
                $hashSequence = $merchentkey ."|".$txnid."|".(int)$order['details']['BT']->order_total."|".JText::_('VMPAYMENT__ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number."|".$order['details']['BT']->first_name."|".$order['details']['BT']->email."|".$udf1."|".$udf2."|".$udf3."|".$udf4."|".$udf5."||||||".$salt;
                $secure_hash = strtolower(hash('sha512',$hashSequence));		
		//echo "<pre>";print_r($method);echo "</pre>";
		
		if(isset($address->address_2)){
	    	$ship_address .=  ", ".$address->address_2;
		}
		
		$post_variables = Array(
		   "key" => $merchentkey,
                    "txnid" => $txnid,
                    "reference_no" => $order['details']['BT']->order_number,		    
		    "productinfo" => JText::_('VMPAYMENT__ORDER_NUMBER') . ': ' . $order['details']['BT']->order_number,
		    "amount" =>(int)$order['details']['BT']->order_total,
			"mode" => $mode,
			"firstname" => $order['details']['BT']->first_name,
                        "lastname" => $order['details']['BT']->last_name,
                    "address" => $order['details']['BT']->address_1." ".$order['details']['BT']->address_2,
			"city" => $order['details']['BT']->city,
			"state" => isset($order['details']['BT']->virtuemart_state_id) ? ShopFunctions::getStateByID($order['details']['BT']->virtuemart_state_id) : '',
			"country" => ShopFunctions::getCountryByID($order['details']['BT']->virtuemart_country_id, 'country_2_code'),
			"zipcode" =>  $order['details']['BT']->zip,
			"phone" => $order['details']['BT']->phone_1,
			"email" => $order['details']['BT']->email,
		    "ship_name" => $address->first_name." ".$address->last_name,
			"ship_address" => $ship_address,			
		    "ship_zipcode" => $address->zip,
		    "ship_city" => $address->city,
		    "ship_state" => isset($address->virtuemart_state_id) ? ShopFunctions::getStateByID($address->virtuemart_state_id) : '',
		    "ship_country" => ShopFunctions::getCountryByID($address->virtuemart_country_id, 'country_2_code'),
		    "ship_phone" => $address->phone_1,
			"hash" => $secure_hash,
			"surl" => $return_url,
                        "furl" => $return_url,
                         "udf1" => "",
                         "udf2" => "",
                         "udf3" => "",
                         "udf4" => "",
                         "udf5" => "",
						 "service_provider" => "payu_paisa",
		);
		
		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['description'] = '$description';//$description;
		$dbValues['payu_custom'] = $return_context;
		$dbValues['billing_currency'] = $method->payment_currency;
		$dbValues['amount'] =(int) $totalInPaymentCurrency;
		$this->storePSPluginInternalData($dbValues);
	
		$url = $this->_getPAYUUrlHttps($method);
		
		// add spin image
		$html = '<html><head><title>Redirection</title></head><body><div style="margin: auto; text-align: center;">';
		$html .= '<form action="' . "https://" . $url . '" method="post" name="vm_payu_form" >';
		$html.= '<input type="submit"  value="' . JText::_('VMPAYMENT_PAYU_REDIRECT_MESSAGE') . '" />';
		foreach ($post_variables as $name => $value) {
		    $html.= '<input type="hidden" style="" name="' . $name . '" value="' . htmlspecialchars($value) . '" />';
		}
		$html.= '</form></div>';
		$html.= ' <script type="text/javascript">';
		$html.= ' document.vm_payu_form.submit();';
		$html.= ' </script></body></html>';
	
		// 	2 = don't delete the cart, don't send email and don't redirect
		$cart->_confirmDone = false;
		$cart->_dataValidated = false;
		$cart->setCartIntoSession();
		JRequest::setVar('html', $html);
    }
    
	function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId) {
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
		    return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
		    return false;
		}
		$this->getPaymentCurrency($method);
		$paymentCurrencyId = $method->payment_currency;
    }

    function plgVmOnPaymentResponseReceived(&$html) {
		if (!class_exists('VirtueMartCart'))
	    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
		if (!class_exists('shopFunctionsF'))
		    require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		if (!class_exists('VirtueMartModelOrders'))
		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
		$order_number = JRequest::getString('on', 0);	
		
		$vendorId = 0;
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
		    return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
		    return null;
		}	
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
		    return null;
		}
		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id) )) {
		    // JError::raiseWarning(500, $db->getErrorMsg());
		    return '';
		}
		$payment_name = $this->renderPluginName($method);
		
   		//$dr = JRequest::getString('DR', 0);
                $response = array();
                $response = $_POST;
                //print_r($_POST);die;
                //print_r($response['status']);
		//print_r($paymentTable);die;
   /*if(!class_exists('Crypt_RC4'))
		require(JPATH_ROOT .DS. 'plugins'.DS.'vmpayment'.DS.'ebs'.DS.'Rc43.php');
			
		$DR = preg_replace("/\s/","+",$dr);
		$rc4 = new Crypt_RC4($method->secret_key);
		$QueryString = base64_decode($DR);
		$rc4->decrypt($QueryString);
		$QueryString = split('&',$QueryString);
		$response = array();
		foreach($QueryString as $param){
			$param = split('=',$param);
			$response[$param[0]] = urldecode($param[1]);
		}
		if($response['ResponseCode']==0){
			if($response['IsFlagged']=='NO'){
				$new_status = $method->status_success;
			}
			else{
				$new_status = $method->status_pending;
			}
		}
		else{
			$new_status = $method->status_canceled;
		}	*///print_r($paymentTable->response_code);
                                          //print_r($paymentTable->is_flagged);	
		if($response['status']=='success'){
			if($response['mihpayid']!=''){
				$new_status = $method->status_success;
			}
			else{
				$new_status = $method->status_pending;
			}
		}
		else{
			$new_status = $method->status_canceled;
		}
                
               // print_r($new_status);die;
		$modelOrder = VmModel::getModel('orders');
		$order['order_status'] = $new_status;
                                //print_r($order);die;
		$order['customer_notified'] = 1;
		$order['comments'] = '';
		$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
                //print_r($_POST);die;
        
		$this->_storePayuInternalData($method, $response, $virtuemart_order_id,$paymentTable->payu_custom);
		if($response['status']=='success'){		
			$html = $this->_getPaymentResponseHtml($paymentTable, $payment_name, $response);
		}
		else{
			$cancel_return = JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' .$order_number.'&pm='.$virtuemart_paymentmethod_id);
			$html= ' <script type="text/javascript">';
			$html.= 'window.location = "'.$cancel_return.'"';
			$html.= ' </script>';
			JRequest::setVar('html', $html);
		}
	
		//We delete the old stuff
		// get the correct cart / session
		$cart = VirtueMartCart::getCart();
		$cart->emptyCart();
		return true;
    }
    
	function _getPaymentResponseHtml($paymentTable, $payment_name, $response) {
		$html = '<table>' . "\n";
		$html .= $this->getHtmlRow('PAYU_PAYMENT_NAME', $payment_name);		
		if (!empty($paymentTable)) {
		    //$html .= $this->getHtmlRow('PAYU_ORDER_NUMBER', $paymentTable->order_number);
                    $html .= $this->getHtmlRow('PAYU_VIRTUEMART_ORDER_ID', $paymentTable->virtuemart_order_id);
		}
		
		
		$tot_amount = $response['amount']." INR";
		$html .= $this->getHtmlRow('PAYU_AMOUNT', $tot_amount);
		
	
		return $html;
    }
    
	function _storePayuInternalData($method, $response, $virtuemart_order_id,$custom) {
      
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
		$response_fields['payment_name'] = $this->renderPluginName($method);	
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$response_fields['virtuemart_paymentmethod_id'] = $virtuemart_paymentmethod_id;
		$response_fields['order_number'] = $response['udf2'];
		$response_fields['payu_custom'] = $custom;
		$response_fields['amount'] = $response['amount'];
		$response_fields['status'] = $response['status'];
		$response_fields['mode'] = ucfirst($response['mode']);
		$response_fields['mihpayid'] = $response['mihpayid'];
		$response_fields['productinfo'] = $response['productinfo'];
		$response_fields['txnid'] = $response['txnid'];
		$response_fields['udf2'] = $response['udf2'];
  		
		$this->storePSPluginInternalData($response_fields, 'virtuemart_order_id', true);
    }
    
 	function plgVmOnUserPaymentCancel() {
		if (!class_exists('VirtueMartModelOrders'))
		    require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
	
		$order_number = JRequest::getString('on', '');
		$virtuemart_paymentmethod_id = JRequest::getInt('pm', '');
		if (empty($order_number) or empty($virtuemart_paymentmethod_id) or !$this->selectedThisByMethodId($virtuemart_paymentmethod_id)) {
		    return null;
		}
		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number))) {
			return null;
		}
		if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
		    return null;
		}
	
		VmInfo(Jtext::_('VMPAYMENT_PAYU_PAYMENT_CANCELLED'));
		$session = JFactory::getSession();
		$return_context = $session->getId();
		if (strcmp($paymentTable->payu_custom, $return_context) === 0) {
		    $this->handlePaymentUserCancel($virtuemart_order_id);
		}
		return true;
    }
    
	
	
    
	function plgVmOnShowOrderBEPayment($virtuemart_order_id, $payment_method_id) {
		if (!$this->selectedThisByMethodId($payment_method_id)) {
		    return null; // Another method was selected, do nothing
		}
		if (!($paymentTable = $this->_getPayuInternalData($virtuemart_order_id) )) {
		    // JError::raiseWarning(500, $db->getErrorMsg());
		    return '';
		}
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $paymentTable->billing_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3 = $db->loadResult();
		$html = '<table class="adminlist">' . "\n";
		$html .=$this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('PAYU_PAYMENT_NAME', $paymentTable->payment_name);		
		//echo "<pre>";print_r($paymentTable);echo "</pre>";
		$html .= $this->getHtmlRowBE('PAYU_VIRTUEMART_ORDER_ID', $paymentTable->virtuemart_order_id);
		$html .= $this->getHtmlRowBE('PAYU_RESPONSE_MESSAGE', $paymentTable->status);
		$html .= $this->getHtmlRowBE('PAYU_PAYMENT_ID', $paymentTable->mihpayid);
		$html .= $this->getHtmlRowBE('PAYU_AMOUNT', $paymentTable->amount.' INR');
		$html .= $this->getHtmlRowBE('PAYU_MODE', $paymentTable->mode);
		$html .= $this->getHtmlRowBE('PAYU_PAYMENT_TRANSACTION_ID', $paymentTable->txnid);
		$html .= $this->getHtmlRowBE('PAYU_PAYMENT_DATE', $paymentTable->modified_on);
		$html .= '</table>' . "\n";
		return $html;
    }

    function _getPayuInternalData($virtuemart_order_id, $order_number = '') {
		$db = JFactory::getDBO();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		if ($order_number) {
		    $q .= " `order_number` = '" . $order_number . "'";
		} else {
		    $q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
		}
		$db->setQuery($q);
		if (!($paymentTable = $db->loadObject())) {
		    // JError::raiseWarning(500, $db->getErrorMsg());
		    return '';
		}
		return $paymentTable;
    } 
	
	
    
	function _getMerchantSalt($method) {		
		return $method->salt;
    }
    
	function _getPAYUUrlHttps($method) {
		$url = 'secure.payu.in/_payment';
		return $url;
    }   
	
	
    
	function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
		if (preg_match('/%$/', $method->cost_percent_total)) {
		    $cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
		    $cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }
    
	protected function checkConditions($cart, $method, $cart_prices) {
		$this->convert($method);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		$amount = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0) ));
		$countries = array();
		if (!empty($method->countries)) {
		    if (!is_array($method->countries)) {
			$countries[0] = $method->countries;
		    } 
                    else {
			$countries = $method->countries;
		    }
		}
		// probably did not gave his BT:ST address
		if (!is_array($address)) {
		    $address = array();
		    $address['virtuemart_country_id'] = 0;
		}
		if (!isset($address['virtuemart_country_id']))
		    $address['virtuemart_country_id'] = 0;
		if (in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
		    if ($amount_cond) {
			return true;
		    }
		}
		return false;
    }
    
 	function convert($method) {
		$method->min_amount = (float) $method->min_amount;
		$method->max_amount = (float) $method->max_amount;
    }
    
	function plgVmOnStoreInstallPaymentPluginTable($jplugin_id) {
		return $this->onStoreInstallPluginTable($jplugin_id);
    }
    
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart) {
		return $this->OnSelectCheck($cart);
    }
    
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
		return $this->displayListFE($cart, $selected, $htmlIn);
    }
    
	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }
    
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(),   &$paymentCounter) {
		return $this->onCheckAutomaticSelected($cart, $cart_prices,  $paymentCounter);
    }
    
 	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		$this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }
    
 	function plgVmonShowOrderPrintPayment($order_number, $method_id) {
		return $this->onShowOrderPrint($order_number, $method_id);
    }
    
    function plgVmDeclarePluginParamsPayment($name, $id, &$data) {
		return $this->declarePluginParams('payment', $name, $id, $data);
    }

    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
		return $this->setOnTablePluginParams($name, $id, $table);
    }

}
