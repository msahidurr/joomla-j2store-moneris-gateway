<?php
/**
 * @package J2Store
 * @copyright Copyright (c)2014-17 Ramesh Elamathi / J2Store.org
 * @license GNU GPL v3 or later
 */
/** ensure this file is being included by a parent file */
defined('_JEXEC') or die('Restricted access');

require_once(JPATH_ADMINISTRATOR . '/components/com_j2store/library/plugins/payment.php');

class plgJ2StorePayment_moneris extends J2StorePaymentPlugin
{
	/**
	 * @var $_element  string  Should always correspond with the plugin's filename,
	 *                         forcing it to be unique
	 */
	var $_element = 'payment_moneris';
	var $config;

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 2.5
	 */
	function __construct(&$subject, $config)
	{
		$this->config = json_decode($config['params']);
		parent::__construct($subject, $config);
		$this->loadLanguage('com_j2store', JPATH_ADMINISTRATOR);
	}

	/**
	 * Prepares the payment form
	 * and returns HTML Form to be displayed to the user
	 * generally will have a message saying, 'confirm entries, then click complete order'
	 *
	 * @param $data     array       form post data
	 * @return string   HTML to display
	 */
	function _prePayment($data)
	{
		$user = JFactory::getUser();
		$order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
		$order->load([
			'order_id' => $data['order_id']
		]);

		$orderInfo = F0FTable::getInstance('Orderinfo', 'J2StoreTable')->getClone();
		$orderInfo->load([
			'order_id' => $data['order_id']
		]);

		$url = "https://gatewayt.moneris.com/chktv2/request/request.php";
		if ($this->config->moneris_environment == 'prod') {
			$url = "https://gateway.moneris.com/chktv2/request/request.php";
		}

		$config = [
			"store_id" => $this->config->moneris_store_id,
			"api_token" => $this->config->moneris_api_token,
			"checkout_id" => $this->config->moneris_checkout_id,
			"environment" => $this->config->moneris_environment,
			"txn_total" => number_format($order->order_total, 2, ".", ""),
			"action" => "preload"
		];

		$config["ask_cvv"] = "Y";
        $config["order_no"] = (string) uniqid('ID', true);
        $config["cust_id"] = (string) $order->order_id;
        $config["dynamic_descriptor"] = "";
        $config["language"] = "en";
        
        $config["contact_details"] = [
            "first_name" => $user->name,
            "last_name" => "",
            "email" => $user->email,
            "phone" => $user->phone ?? '',
        ];

        $config["shipping_details"] = [
            "address_1" => (string) $orderInfo->shipping_address_1,
            "address_2" => (string) $orderInfo->shipping_address_2,
            "city" => $orderInfo->shipping_city,
            "province" => $orderInfo->shipping_zone_name,
            "country" => $orderInfo->shipping_country_name,
            "postal_code" => $orderInfo->shipping_zip
        ];

        $config["billing_details"] = [
            "address_1" => (string) $orderInfo->billing_address_1,
            "address_2" => (string) $orderInfo->billing_address_2,
            "city" => $orderInfo->billing_city,
            "province" => $orderInfo->billing_zone_name,
            "country" => $orderInfo->billing_country_name,
            "postal_code" => $orderInfo->billing_zip
        ];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);  //0 for a get request
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		$serverOutput = json_decode(curl_exec($ch));
		curl_close($ch);

		if (!empty($serverOutput)) {
			if ($serverOutput->response->success == true) {
				$vars = new JObject();
				$vars->ticket = $serverOutput->response->ticket;
				$vars->moneris_environment = $this->config->moneris_environment;
				$vars->redirect = JRoute::_('index.php?option=com_j2store&view=checkout&task=confirmPayment&paction=process&orderpayment_type=' . $this->_element . '&ticket=' . $serverOutput->response->ticket);
				$html = $this->_getLayout('checkout', $vars);
				echo $html;
				die();
			}
		}

		echo "payment method failed, please try again."; die();
	}

	/**
	 * Processes the payment form
	 * and returns HTML to be displayed to the user
	 * generally with a success/failed message
	 *
	 * @param $data     array       form post data
	 * @return string   HTML to display
	 */
	function _postPayment($data)
	{
		$vars = new JObject();

		$app = JFactory::getApplication();
		$paction = $app->input->getString('paction');

		switch ($paction) {
			case 'display':
				$vars->onafterpayment_text = JText::_('Payment Success');
				$html = $this->_getLayout('postpayment', $vars);
				$html .= $this->_displayArticle();
				break;
			case 'process':
				$ticket = $app->input->getString('ticket');
				$url = "https://gatewayt.moneris.com/chktv2/request/request.php";
				if ($this->config->moneris_environment == 'prod') {
					$url = "https://gateway.moneris.com/chktv2/request/request.php";
				}
				$config = [
					"store_id" => $this->config->moneris_store_id,
					"api_token" => $this->config->moneris_api_token,
					"checkout_id" => $this->config->moneris_checkout_id,
					"environment" => $this->config->moneris_environment,
					"ticket" => $ticket,
					"action" => "receipt",
				];

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, true);  //0 for a get request
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
				$serverOutput = json_decode(curl_exec($ch));
				curl_close($ch);

				if (!empty($serverOutput)) {
					if ($serverOutput->response->success == true) {
						if (strtolower($serverOutput->response->receipt->result) == 'a') {
							$result = $this->_process($data);
							header("location:" . $result['redirect'] ?? '');
							$app->close();
						}
					}
				}
				
				$url = 'index.php?option=com_j2store&view=checkout&task=confirmPayment&layout=postpayment&orderpayment_type='.$this->_element;
				header("location:" . $url);
				break;
			default:
				$vars->message = JText::_('Payment Failed');
				$html = $this->_getLayout('message', $vars);
				break;
		}

		return $html;
	}

	/**
	 * Processes the payment form
	 * and returns HTML to be displayed to the user
	 * generally with a success/failed message
	 *
	 * @param $data     array       form post data
	 * @return string   HTML to display
	 */
	function _process($data)
	{
		// Process the payment
		$json = array();
		F0FTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_j2store/tables');
		$order = F0FTable::getInstance('Order', 'J2StoreTable')->getClone();
		if (
			$order->load(array(
				'order_id' => $data['order_id']
			))
		) {
			$order->payment_complete();

			if ($order->store()) {
				//empty the cart
				$order->empty_cart();
				$json['success'] = JText::_( $this->config->onafterpayment);
				$return_url = $this->getReturnUrl();
				$json['redirect'] = JRoute::_($return_url);//JRoute::_ ( 'index.php?option=com_j2store&view=checkout&task=confirmPayment&orderpayment_type=' . $this->_element . '&paction=display' );
			} else {
				$json['error'] = $order->getError();
			}
		} else {
			$json['error'] = 'Order not found.';
		}

		return $json;
	}

	/**
	 * Prepares variables and
	 * Renders the form for collecting payment info
	 *
	 * @return unknown_type
	 */
	function _renderForm($data)
	{
		$vars = new JObject();
		$vars->onselection_text = $this->config->onselection;
		$html = $this->_getLayout('form', $vars);
		return $html;
	}
}
