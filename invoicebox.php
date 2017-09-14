<?php
if (!defined('_VALID_MOS') && !defined('_JEXEC')) die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');

if (!class_exists('vmPSPlugin')) require (JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');


class plgVmPaymentInvoiceBox extends vmPSPlugin {


    function __construct (& $subject, $config) {

        parent::__construct ($subject, $config);
        //      vmdebug('Plugin stuff',$subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys ($this->getTableSQLFields ());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush ();
        $this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
    }
    
    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Invoicebox Table');
    }
    
    function getTableSQLFields () {

        $SQLfields = array(
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'email_currency'              => 'char(3)',
            'cost_per_transaction'        => 'decimal(10,2)',
            'cost_percent_total'          => 'decimal(10,2)',
            'tax_id'                      => 'smallint(1)'
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
        
        $lang = JFactory::getLanguage();
        $filename = 'com_virtuemart';
        $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;
        
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
        
        $html = "";
        
        if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        if (!$method->payment_currency) $this->getPaymentCurrency($method);
        
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db =  JFactory::getDBO();
        $db->setQuery($q);
        $currency_code_3 = $db->loadResult();
        switch ($method->payment_currency) {
            case 144:
                $currency = 840;
            break;
            case 199:
                $currency = 980;
            break;
            case 164:
                $currency = 398;
            break;
            case 153:
                $currency = 710;
            break;
            default: {
                        $currency = 643;
                        $method->payment_currency = 131;
                };
            }
			
            $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
            $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false), 2);
            $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
            
            $this->_virtuemart_paymentmethod_id = $order['details']['BT']->virtuemart_paymentmethod_id;
            $dbValues['payment_name'] = $this->renderPluginName($method);
            $dbValues['order_number'] = $order['details']['BT']->order_number;
            $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
            $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
            $dbValues['cost_percent_total'] = $method->cost_percent_total;
            $dbValues['payment_currency'] = $currency_code_3;
            $dbValues['payment_order_total'] = $totalInPaymentCurrency;
            $dbValues['tax_id'] = $method->tax_id;
            $this->storePSPluginInternalData($dbValues);
			
            $signatureValue = md5(
			$method->itransfer_participant_id.
			$virtuemart_order_id.
			$totalInPaymentCurrency.
			$currency_code_3.
			$method->invoicebox_api_key
			); 
			
            $params = array(
                "itransfer_participant_id" => $method->itransfer_participant_id,
				"itransfer_participant_ident" => $method->itransfer_participant_ident,
				"itransfer_order_id" => $virtuemart_order_id,
                "itransfer_order_amount" => $totalInPaymentCurrency,
                "itransfer_order_currency_ident" => $currency_code_3,
                "itransfer_testmode" => $method->itransfer_testmode,
                "itransfer_body_type" => "PRIVATE",
                "itransfer_participant_sign" => $signatureValue,
                "CMS" => 'Joomla-' . JVERSION,
                "itransfer_order_description" => 'Оплата заказа ' . $order['details']['BT']->order_number,
				"itransfer_person_name" => $order['details']['BT']->first_name.' '.$order['details']['BT']->last_name,
				"itransfer_person_email" => $order['details']['BT']->email,
                "itransfer_url_notify" => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component'),
				"itransfer_url_return" => JROUTE::_(JURI::root() . 'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order['details']['BT']->order_number . '&order_pass=' . $order['details']['BT']->order_pass)
            );
			if($order['details']['BT']->phone_1){
				$params['itransfer_person_phone'] = $order['details']['BT']->phone_1;
			}elseif($order['details']['BT']->phone_2){
				$params['itransfer_person_phone'] = $order['details']['BT']->phone_2;
			}
            
			
   

	$vatrate = 0;
	if(count($order['calc_rules'])){
		$vatrate = round($order['calc_rules'][0]->calc_value);
		
	}
    $product_quantity = $i = 0;
    foreach ($order['items'] as $product) { 
		$i++;
		$product_quantity += $product->product_quantity;
		 $params['itransfer_item'.$i.'_name'] = $product->product_name;
		 $params['itransfer_item'.$i.'_quantity'] = $product->product_quantity;
		 $params['itransfer_item'.$i.'_price'] = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $product->product_final_price, false), 2);;
		 $params['itransfer_item'.$i.'_vatrate'] = $vatrate;
		 $params['itransfer_item'.$i.'_vat'] = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $product->product_tax, false), 2);
		 $params['itransfer_item'.$i.'_measure'] = 'шт.';
		
	}	
	$params['itransfer_order_quantity'] = $product_quantity;
	if($order['details']['BT']->order_shipment > 0){
		$i++;
		 $params['itransfer_item'.$i.'_name'] = 'Доставка';
		 $params['itransfer_item'.$i.'_quantity'] = 1;
		 $params['itransfer_item'.$i.'_price'] = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_shipment+$order['details']['BT']->order_shipment_tax, false), 2);
		 $params['itransfer_item'.$i.'_vatrate'] = $order['details']['BT']->order_shipment_tax/$order['details']['BT']->order_shipment*100;
		 $params['itransfer_item'.$i.'_vat'] = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_shipment_tax, false), 2);
		 $params['itransfer_item'.$i.'_measure'] = 'шт.';
	}
	
            // VMInvoicebox::_setFields($params);
            // $params                  = VMInvoicebox::_getFields();
            // $params["WMI_SIGNATURE"] = VMInvoicebox::getHash($method->invoicebox_secret);
            
            $html = '<form method="post" action="https://go.invoicebox.ru/module_inbox_auto.u" accept-charset="UTF-8" name="vm_invoicebox_form">';
            foreach ($params as $key => $param) {
                $html.= '<input type="hidden" name="' . $key . '" value="' . $param . '">';
            }
            $html.= '<input type="submit" value="Оплатить"/></form>';
            $html.= ' <script type="text/javascript">';
            $html.= ' document.forms.vm_invoicebox_form.submit();';
            $html.= ' </script>';
            //$html.= '<pre>'.print_r($order,1).'</pre>';
			return $this->processConfirmedOrderPaymentResponse(true, $cart, $order, $html, $this->renderPluginName($method, $order), 'P');
        }
        
        function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id) {
            if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
                return null; // Another method was selected, do nothing
                
            }
            
            $db = JFactory::getDBO();
            $q = 'SELECT * FROM `' . $this->_tablename . '` ' . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
            $db->setQuery($q);
            if (!($paymentTable = $db->loadObject())) {
                vmWarn(500, $q . " " . $db->getErrorMsg());
                return '';
            }
            $this->getPaymentCurrency($paymentTable);
            
            $html = '<table class="adminlist">' . "\n";
            $html.= $this->getHtmlHeaderBE();
            $html.= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
            $html.= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
            $html.= '</table>' . "\n";
            return $html;
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
            $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
            
            $amount = $cart_prices['salesPrice'];
            $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount OR ($method->min_amount <= $amount AND ($method->max_amount == 0)));
            if (!$amount_cond) {
                return false;
            }
            $countries = array();
            if (!empty($method->countries)) {
                if (!is_array($method->countries)) {
                    $countries[0] = $method->countries;
                } else {
                    $countries = $method->countries;
                }
            }
            // probably did not gave his BT:ST address
            if (!is_array($address)) {
                $address = array();
                $address['virtuemart_country_id'] = 0;
            }
            
            if (!isset($address['virtuemart_country_id'])) $address['virtuemart_country_id'] = 0;
            if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
                return true;
            }
            
            return false;
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
        
        public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array & $cart_prices, &$cart_prices_name) {
            return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
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
        
        function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array()) {
            return $this->onCheckAutomaticSelected($cart, $cart_prices);
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
        function plgVmDeclarePluginParamsPaymentVM3( &$data) {
            return $this->declarePluginParams('payment', $data);
        }
        function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {
            return $this->setOnTablePluginParams($name, $id, $table);
        }
        
        protected function displayLogos($logo_list) {
            $img = "";
            
            if (!(empty($logo_list))) {
                $url = JURI::root() . str_replace(JPATH_ROOT, '', dirname(__FILE__)) . '/';
                if (!is_array($logo_list)) $logo_list = (array)$logo_list;
                foreach ($logo_list as $logo) {
                    $alt_text = substr($logo, 0, strpos($logo, '.'));
                    $img.= '<img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /> ';
                }
            }
            return $img;
        }
        
        public function plgVmOnPaymentNotification() {
            // $log = print_r($_POST,1)." \n";
        // file_put_contents(dirname(__FILE__)."/invoicebox_log.log", $log, FILE_APPEND);
		    
            if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
			$app   = JFactory::getApplication();
            $input = $app->input;
			$participantId 		= IntVal(JRequest::getVar("participantId"));
			$participantOrderId 	= IntVal(JRequest::getVar("participantOrderId")) ;
			if ( !($participantId && $participantOrderId )){
				die( 'Данные запроса не переданы' );
			}
			$ucode 		= JRequest::getVar("ucode");
			$timetype 	= JRequest::getVar("timetype");
			$time 		= str_replace(' ','+',JRequest::getVar("time"));
			$amount 	= JRequest::getVar("amount");
			$currency 	= JRequest::getVar("currency");
			$agentName 	= JRequest::getVar("agentName");
			$agentPointName = JRequest::getVar("agentPointName");
			$testMode 	= JRequest::getVar("testMode");
			$sign	 	= JRequest::getVar("sign");
            $orderid = $participantOrderId;
            $payment = $this->getDataByOrderId($orderid);
            $method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);
            $order_model = new VirtueMartModelOrders();
            $order_info = $order_model->getOrder($orderid);
			if ( !$order_info){
				die( 'Указанный номер заказа не найден в системе' );
			}
            $order_number = $order_info['details']['BT']->order_number;
			
            if (!$method->payment_currency) $this->getPaymentCurrency($method);
            $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
            $db = JFactory::getDBO();
            $db->setQuery($q);
            $currency_code_3 = $db->loadResult();
            if (!class_exists('CurrencyDisplay')) require (JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php');
            $paymentCurrency = CurrencyDisplay::getInstance($method->payment_currency);
            $totalInPaymentCurrency = round($paymentCurrency->convertCurrencyTo($method->payment_currency, $order_info['details']['BT']->order_total, false), 2);
            
            $sig = JRequest::getVar('WMI_SIGNATURE');
            $postData    = $input->getArray($_POST);
			
            $sign_strA = md5(
			$participantId .
			$participantOrderId .
			$ucode .
			$timetype .
			$time .
			$amount .
			$currency .
			$agentName .
			$agentPointName .
			$testMode .
			$method->invoicebox_api_key);
			if ($sign == $sign_strA) {
                if(totalInPaymentCurrency == $amount){
					$order['order_status'] = $method->status_success;
					$order['virtuemart_order_id'] = $orderid;
					$order['customer_notified'] = 0;
					$order['comments'] = JTExt::sprintf('VMPAYMENT_INVOICEBOX_PAYMENT_CONFIRMED', $order_number);
					if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
					$modelOrder = new VirtueMartModelOrders();
					ob_start();
					$modelOrder->updateStatusForOneOrder($orderid, $order, true);
					ob_end_clean();
					echo 'OK';
					exit();
				}else{
					die( 'Сумма оплаты не совпадает с суммой заказа' );
				}
            }
            die( 'Подпись запроса неверна' );
            return null;
        }
        
        function plgVmOnPaymentResponseReceived(&$html) {
            
            $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);
            
            $vendorId = 0;
            if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
                return null; // Another method was selected, do nothing
                
            }
            if (!$this->selectedThisElement($method->payment_element)) {
                return false;
            }
            
            if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            
            $order_number = JRequest::getVar('on');
            $virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
            $payment_name = $this->renderPluginName($method);
            $html = '<table>' . "\n";
            $html.= $this->getHtmlRow('INVOICEBOX_PAYMENT_NAME', $payment_name);
            $html.= $this->getHtmlRow('INVOICEBOX_ORDER_NUMBER', $virtuemart_order_id);
            $html.= $this->getHtmlRow('INVOICEBOX_STATUS', JText::_('VMPAYMENT_INVOICEBOX_STATUS_SUCCESS'));
            
            $html.= '</table>' . "\n";
            
            if ($virtuemart_order_id) {
                if (!class_exists('VirtueMartCart')) require (JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
                $cart = VirtueMartCart::getCart();
                if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
                $order = new VirtueMartModelOrders();
                $orderitems = $order->getOrder($virtuemart_order_id);
                $cart->sentOrderConfirmedEmail($orderitems);
                $cart->emptyCart();
            }
            
            return true;
        }
        
        function plgVmOnUserPaymentCancel() {
            if (!class_exists('VirtueMartModelOrders')) require (JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            
            $order_number = JRequest::getVar('on');
            if (!$order_number) return false;
            $db = JFactory::getDBO();
            $query = 'SELECT ' . $this->_tablename . '.`virtuemart_order_id` FROM ' . $this->_tablename . " WHERE  `order_number`= '" . $order_number . "'";
            
            $db->setQuery($query);
            $virtuemart_order_id = $db->loadResult();
            
            if (!$virtuemart_order_id) {
                return null;
            }
            $this->handlePaymentUserCancel($virtuemart_order_id);
            
            return true;
        }
        
        private function notifyCustomer($order, $order_info) {
            $lang = JFactory::getLanguage();
            $filename = 'com_virtuemart';
            $lang->load($filename, JPATH_ADMINISTRATOR);
            if (!class_exists('VirtueMartControllerVirtuemart')) require (JPATH_VM_SITE . DS . 'controllers' . DS . 'virtuemart.php');
            
            if (!class_exists('shopFunctionsF')) require (JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
            $controller = new VirtueMartControllerVirtuemart();
            $controller->addViewPath(JPATH_VM_ADMINISTRATOR . DS . 'views');
            
            $view = $controller->getView('orders', 'html');
            if (!$controllerName) $controllerName = 'orders';
            $controllerClassName = 'VirtueMartController' . ucfirst($controllerName);
            if (!class_exists($controllerClassName)) require (JPATH_VM_SITE . DS . 'controllers' . DS . $controllerName . '.php');
            
            $view->addTemplatePath(JPATH_COMPONENT_ADMINISTRATOR . '/views/orders/tmpl');
            
            $db = JFactory::getDBO();
            $q = "SELECT CONCAT_WS(' ',first_name, middle_name , last_name) AS full_name, email, order_status_name
            FROM #__virtuemart_order_userinfos
            LEFT JOIN #__virtuemart_orders
            ON #__virtuemart_orders.virtuemart_user_id = #__virtuemart_order_userinfos.virtuemart_user_id
            LEFT JOIN #__virtuemart_orderstates
            ON #__virtuemart_orderstates.order_status_code = #__virtuemart_orders.order_status
            WHERE #__virtuemart_orders.virtuemart_order_id = '" . $order['virtuemart_order_id'] . "'
            AND #__virtuemart_orders.virtuemart_order_id = #__virtuemart_order_userinfos.virtuemart_order_id";
            $db->setQuery($q);
            $db->query();
            $view->user = $db->loadObject();
            $view->order = $order;
            JRequest::setVar('view', 'orders');
            $user = $this->sendVmMail($view, $order_info['details']['BT']->email, false);
            if (isset($view->doVendor)) {
                $this->sendVmMail($view, $view->vendorEmail, true);
            }
        }
        
        private function sendVmMail(&$view, $recipient, $vendor = false) {
            ob_start();
            $view->renderMailLayout($vendor, $recipient);
            $body = ob_get_contents();
            ob_end_clean();
            
            $subject = (isset($view->subject)) ? $view->subject : JText::_('COM_VIRTUEMART_DEFAULT_MESSAGE_SUBJECT');
            $mailer = JFactory::getMailer();
            $mailer->addRecipient($recipient);
            $mailer->setSubject($subject);
            $mailer->isHTML(VmConfig::get('order_mail_html', true));
            $mailer->setBody($body);
            
            if (!$vendor) {
                $replyto[0] = $view->vendorEmail;
                $replyto[1] = $view->vendor->vendor_name;
                $mailer->addReplyTo($replyto);
            }
            
            if (isset($view->mediaToSend)) {
                foreach ((array)$view->mediaToSend as $media) {
                    $mailer->addAttachment($media);
                }
            }
            return $mailer->Send();
        }
    }
    