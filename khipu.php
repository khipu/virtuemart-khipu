<?php
defined('_JEXEC') or die('Restricted access');

/**
 *
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (C) 2013 Khipu SpA - All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.org
 */
if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentKhipu extends vmPSPlugin {

    public static $_this = false;

    function __construct(&$subject, $config) {
        parent::__construct($subject, $config);

        $this->_loggable = TRUE;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);

        $this->setCryptedFields(array('key'));

    }

    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

    function plgVmGetTablePluginParams($psType, $name, $id, &$xParams, &$varsToPush) {
        return $this->getTablePluginParams($psType, $name, $id, $xParams, $varsToPush);
    }

    public function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Khipu Table');
    }

    function getTableSQLFields() {
        $SQLfields = array('id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'order_number' => ' char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3) ',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
        );
        return $SQLfields;
    }

    function getCosts(VirtueMartCart $cart, $method, $cart_prices) {
        if (preg_match('/%$/', $method->cost_percent_total)) {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        } else {
            $cost_percent_total = $method->cost_percent_total;
        }

        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    function checkConditions($cart, $method, $cart_prices) {
        $this->convert($method);
        $amount = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount && $amount <= $method->max_amount || ($amount >= $method->min_amount && empty($method->max_amount)));
        return $amount_cond;
    }

    function convert($method) {
        $method->min_amount = (float)$method->min_amount;
        $method->max_amount = (float)$method->max_amount;
    }

    function plgVmConfirmedOrder($cart, $order) {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->_debug = $method->debug; // enable debug
        $session = JFactory::getSession();
        $return_context = $session->getId();
        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- order number: ' . $order['details']['BT']->order_number, 'message');

        $method->url = "https://khipu.com/api/1.3/createPaymentPage";

        $vendorModel = VmModel::getModel('vendor');
        $vendorName = $vendorModel->getVendorName($method->virtuemart_vendor_id);

        // set config parameters
        $params = array(
            'receiver_id' => $method->receiver_id,
            'subject' => JText::sprintf($vendorName . ' - Orden: %s', $order['details']['BT']->order_number),
            'amount' => sprintf("%.0f", $order['details']['BT']->order_total),
            'payer_email' => $order['details']['BT']->email,
            'transaction_id' => $order['details']['BT']->virtuemart_order_id,
            'notify_url' =>
                JROUTE::_(JURI::root() .
                    'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id ),
            'return_url' =>
                JROUTE::_(JURI::root() .
                    'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status_code=ok&on=' .
                    $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id .
                    '&transaction_id=' . $order['details']['BT']->virtuemart_order_id),
            'cancel_url' =>
                JROUTE::_(JURI::root() .
                    'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&status_code=cancel&on=' .
                    $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id .
                    '&transaction_id=' . $order['details']['BT']->virtuemart_order_id),
        );

        $cvars = "receiver_id=" . $params['receiver_id'] .
            "&subject=" . $params['subject'] .
            "&body=" .
            "&amount=" . $params['amount'] .
            "&payer_email=" . $params['payer_email'] .
            "&bank_id=" .
            "&expires_date=" .
            "&transaction_id=" . $params['transaction_id'] .
            "&custom=" .
            "&notify_url=" . $params['notify_url'] .
            "&return_url=" . $params['return_url'] .
            "&cancel_url=" . $params['cancel_url'] .
            "&picture_url=";


        $params['hash'] = hash_hmac('sha256', $cvars, $method->secret);
        // Set the language code
        $lang = JFactory::getLanguage();
        $lang->load('plg_vmpayment_' . $this->_name, JPATH_ADMINISTRATOR);

        $tag = substr($lang->get('tag'), 0, 2);
        //$language = in_array($tag, $api->getSupportedLanguages()) ? $tag : ($method->language ? $method->language : 'en');

        // Prepare data that should be stored in the database
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['payment_name'] = $this->renderPluginName($method, $order);
        $dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
        $dbValues[$this->_name . '_custom'] = $return_context;
        $this->storePSPluginInternalData($dbValues);

        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- payment data saved to table ' . $this->_tablename, 'message');
        $this->logInfo('plgVmOnConfirmedOrderGetPaymentForm -- user redirected to ' . $this->_name, 'message');

        // echo the redirect form
        echo $this->getConfirmFormHtml($method, $params, $order);

        $cart->_confirmDone = false;
        $cart->_dataValidated = false;
        $cart->setCartIntoSession();
        die(); // not save order, not send mail, do redirect
    }

    function getConfirmFormHtml($method, $params, $order) {

        $form = <<<EOD
<html>
<head>
  <title>Redirection</title>
  </head>
  <body>
  Redireccionando a khipu.com...
EOD;

        $form .= '<form action="' . $method->url . '" method="POST" name="vm_' . $this->_name . '_form" >' . "\n";
        foreach ($params as $k => $v) {
            $form .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />' . "\n";
        }
        $form .= '<script type="text/javascript">document.forms[0].submit();</script>';
        $form .= '</form>' . "\n";
        $form .= '</body></html>' . "\n";
        return $form;
    }

    function plgVmOnPaymentResponseReceived(&$html) {
        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

        $vendorId = 0;
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $this->_debug = true;
        $this->logInfo('plgVmOnPaymentResponseReceived -- user returned back from ' . $this->_name, 'message');


        $resp = JRequest::get('request');

        // Retrieve order info from database
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $virtuemart_order_id = $resp['transaction_id'];

        // Order not found
        if (!$virtuemart_order_id) {
            vmdebug('plgVmOnPaymentResponseReceived ' . $this->_name, $resp, $resp['transaction_id']);
            $this->logInfo('plgVmOnPaymentResponseReceived -- payment check attempted on non existing order : ' . $resp['transaction_id'], 'error');
            return null;
        }

        $order = VirtueMartModelOrders::getOrder($virtuemart_order_id);
        $order_status_code = $order['items'][0]->order_status;

        if ($resp['status_code'] == 'ok') {
            $html = $this->_getHtmlPaymentResponse('La orden se ha creado exitosamente', true, $resp['transaction_id']);
            $new_status = $method->status_pending;
        } else {
            $html = $this->_getHtmlPaymentResponse('SE HA CANCELADO LA ORDEN', false);
            $new_status = $method->status_canceled;
        }

        // Order not processed yet
        if ($order_status_code == 'P') {
            $this->managePaymentResponse($virtuemart_order_id, $resp, $new_status);
        }

        return null;
    }

    function plgVmOnUserPaymentCancel() {

        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        $order_number = JRequest::getString('on');
        if (!$order_number) {
            return false;
        }
        if (!$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number)) {
            return null;
        }
        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id))) {
            return null;
        }

        $session = JFactory::getSession();
        $return_context = $session->getId();
        $field = $this->_name . '_custom';
        if (strcmp($paymentTable->$field, $return_context) === 0) {
            $this->handlePaymentUserCancel($virtuemart_order_id);
        }
        return true;
    }

    function plgVmOnPaymentNotification() {

        $virtuemart_paymentmethod_id = JRequest::getInt('pm', 0);

        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        $this->_debug = $method->debug; // enable debug
        $this->logInfo('plgVmOnPaymentNotification -- notification from merchant', 'message');

        $resp = JRequest::get('request');

        $api_version = $resp['api_version'];
        $receiver_id = $resp['receiver_id'];
        $notification_id = $resp['notification_id'];
        $subject = $resp['subject'];
        $amount = $resp['amount'];
        $currency = $resp['currency'];
        $payer_email = $resp['payer_email'];
        $transaction_id = $resp['transaction_id'];
        $notification_signature = $resp['notification_signature'];

        if (strcmp($method->receiver_id, $receiver_id) != 0) {
            print "ERROR1";
            http_response_code(400);
            return null;
        }

        $to_validate = 'api_version=' . $api_version .
            '&receiver_id=' . $receiver_id .
            '&notification_id=' . $notification_id .
            '&subject=' . $subject .
            '&amount=' . $amount .
            '&currency=' . $currency .
            '&transaction_id=' . $transaction_id .
            '&payer_email=' . $payer_email .
            '&custom=';

        $filename = JPATH_PLUGINS . "/vmpayment/khipu/khipu.pem";
        $fp = fopen($filename, "r");
        $cert = fread($fp, filesize($filename));
        fclose($fp);
        $pubkey = openssl_get_publickey($cert);

        $notification_valid = openssl_verify($to_validate, base64_decode($notification_signature), $pubkey);

        openssl_free_key($pubkey);

        if ($notification_valid) {
            // Retrieve order info from database
            if (!class_exists('VirtueMartModelOrders')) {
                require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
            }

            $order = VirtueMartModelOrders::getOrder($transaction_id);

            // Order not found
            if (!$order) {
                vmdebug('plgVmOnPaymentNotification ' . $this->_name, $resp, $resp['transaction_id']);
                $this->logInfo('plgVmOnPaymentNotification -- payment merchant confirmation attempted on non existing order : ' . $resp['transaction_id'], 'error');
                $html = $this->_getHtmlPaymentResponse('VMPAYMENT_' . $this->_name . '_ERROR_MSG', false);
                print "ERROR2";
                http_response_code(400);
                return null;
            }

            // save order data
            $modelOrder = VmModel::getModel('orders');
            $order['order_status'] = "C";
            $order['virtuemart_order_id'] = $transaction_id;
            $order['customer_notified'] = 1;
            $order['comments'] = "Confirmation from khipu";
            vmdebug($this->_name . ' - PaymentNotification', $order);

            $modelOrder->updateStatusForOneOrder($transaction_id, $order, true);

            print "OK";
        } else {
            print "ERROR";
            http_response_code(400);
        }

        die();
    }

    function _getHtmlPaymentResponse($msg, $is_success = true, $order_id = null, $amount = null) {
        if (!$is_success) {
            return '<p style="text-align: center;">' . JText::_($msg) . '</p>';
        } else {
            $html = '<table>' . "\n";
            $html .= '<thead><tr><td colspan="2" style="text-align: center;">' . JText::_($msg) . '</td></tr></thead>';
            //$html .= $this->getHtmlRow('Número de orden: ', $order_id, 'style="width: 90px;" class="key"');
            //$html .= $this->getHtmlRow('Cantidad: ', $amount, 'style="width: 90px;" class="key"');
            $html .= '</table>' . "\n";

            return $html;
        }
    }

    function savePaymentData($virtuemart_order_id, $resp) {
        vmdebug($this->_name . 'response_raw', json_encode($resp));
        $response[$this->_tablepkey] = $this->_getTablepkeyValue($virtuemart_order_id);
        $response['virtuemart_order_id'] = $virtuemart_order_id;
        $response[$this->_name . '_response_payment_date'] = gmdate('Y-m-d H:i:s', time());
        $response[$this->_name . '_response_payment_status'] = $resp['status_code'];
        $response[$this->_name . '_response_trans_id'] = $resp['transaction_id'];;
        $this->storePSPluginInternalData($response, $this->_tablepkey, true);
    }

    function _getTablepkeyValue($virtuemart_order_id) {
        $db = JFactory::getDBO();
        $q = 'SELECT ' . $this->_tablepkey . ' FROM `' . $this->_tablename . '` ' . 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
        $db->setQuery($q);

        if (!($pkey = $db->loadResult())) {
            JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        return $pkey;
    }

    function emptyCart($session_id) {
        if ($session_id != null) {
            $session = JFactory::getSession();
            $session->close();

            // Recover session in wich the payment is done
            session_id($session_id);
            session_start();
        }

        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();
        return true;
    }

    function managePaymentResponse($virtuemart_order_id, $resp, $new_status, $return_context = null) {
        if (!class_exists('VirtueMartModelOrders')) {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        // save order data
        $modelOrder = new VirtueMartModelOrders();
        $order['order_status'] = $new_status;
        $order['virtuemart_order_id'] = $virtuemart_order_id;
        $order['customer_notified'] = 1;
        $date = JFactory::getDate();
        if (method_exists($date, 'toFormat')) {
            $order['comments'] = JText::sprintf('Notification from khipu', $date->toFormat('%Y-%m-%d %H:%M:%S'));
        } else {
            $order['comments'] = JText::sprintf('Notification from khipu', $date->format('%Y-%m-%d %H:%M:%S'));
        }
        vmdebug($this->_name . ' - managePaymentResponse', $order);

        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);

        if (!class_exists('VirtueMartCart')) {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }

        if ($resp['status_code'] == 'ok') {
            // Empty cart in session
            $this->emptyCart($return_context);
        }
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

    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
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

if (!function_exists('http_response_code')) {
    function http_response_code($code = NULL) {

        if ($code !== NULL) {

            switch ($code) {
                case 100: $text = 'Continue'; break;
                case 101: $text = 'Switching Protocols'; break;
                case 200: $text = 'OK'; break;
                case 201: $text = 'Created'; break;
                case 202: $text = 'Accepted'; break;
                case 203: $text = 'Non-Authoritative Information'; break;
                case 204: $text = 'No Content'; break;
                case 205: $text = 'Reset Content'; break;
                case 206: $text = 'Partial Content'; break;
                case 300: $text = 'Multiple Choices'; break;
                case 301: $text = 'Moved Permanently'; break;
                case 302: $text = 'Moved Temporarily'; break;
                case 303: $text = 'See Other'; break;
                case 304: $text = 'Not Modified'; break;
                case 305: $text = 'Use Proxy'; break;
                case 400: $text = 'Bad Request'; break;
                case 401: $text = 'Unauthorized'; break;
                case 402: $text = 'Payment Required'; break;
                case 403: $text = 'Forbidden'; break;
                case 404: $text = 'Not Found'; break;
                case 405: $text = 'Method Not Allowed'; break;
                case 406: $text = 'Not Acceptable'; break;
                case 407: $text = 'Proxy Authentication Required'; break;
                case 408: $text = 'Request Time-out'; break;
                case 409: $text = 'Conflict'; break;
                case 410: $text = 'Gone'; break;
                case 411: $text = 'Length Required'; break;
                case 412: $text = 'Precondition Failed'; break;
                case 413: $text = 'Request Entity Too Large'; break;
                case 414: $text = 'Request-URI Too Large'; break;
                case 415: $text = 'Unsupported Media Type'; break;
                case 500: $text = 'Internal Server Error'; break;
                case 501: $text = 'Not Implemented'; break;
                case 502: $text = 'Bad Gateway'; break;
                case 503: $text = 'Service Unavailable'; break;
                case 504: $text = 'Gateway Time-out'; break;
                case 505: $text = 'HTTP Version not supported'; break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }

            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            header($protocol . ' ' . $code . ' ' . $text);

            $GLOBALS['http_response_code'] = $code;

        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
        return $code;
    }
}
