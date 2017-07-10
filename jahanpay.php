<?php
/**
 * @version		$Id: $
 * @author		Nguyen Dinh Luan
 * @package		Joomla!
 * @subpackage	Jahanpay Payment Plugin
 * @copyright	Copyright (C) 2008 - 2011 by Joomseller. All rights reserved.
 * @license		http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL version 3
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

/**
 * Joomseller Payment - Jahanpay Payment Plugin.
 * @package		Joomseller Payment
 * @subpackage	Payment Plugin
 */
class plgJSPaymentJahanpay extends JPlugin {
	/** @var plugin parameter */
	var $params				= null;
	/** @var string Code name of payment method */
	var $_name				= 'jahanpay';
	/** @var array Payment method information */
	var $_info				= null;
	/** @var string live site */
	var $live_site			= null;
	/** @var array Jahanpay payment data */
	var $data				= array();
	/** @var string Jahanpay notify URL */
	var $_url				= null;
	/** @var string Jahanpay PIN */
	var $_pin				= null;
	/** @var array Jahanpay notify */
	var $_ppnotify			= array(
								'valid_ip'			=> true,
								'order_stt'			=> '',
								'email_sbj'			=> '',
								'email_body'		=> ''
								);


	/**
	 * Constructor
	 */
	function __construct(& $subject, $params) {
		parent::__construct($subject, $params);
		
		// init variables
		$this->_name			= 'jahanpay';
		$this->_url				= '';
		$this->live_site		= JUri::base();
		$this->_pin				= $this->params->get('jahanpay_pin');
	}

	/**
	 * Get payment info method.
	 */
	function onPaymentInfo() {
		if (empty($this->_info)) {
			$this->_info = array(
				'code'		=> 'jahanpay',							// Code to separate payment plugin
				'name'		=> JText::_('Jahanpay'),					// Name to display of payment method
				'image'		=> $this->params->get('payment_image'),	// Image to display of payment method
				'use_cc'	=> 0,									// Use credit card or not?
			);
		}

		return $this->_info;
	}

	/**
	 * Process payment method.
	 */
	function onProcessPayment($order)
	{
		if ($order->payment_method != $this->_name)
		{
			return JOOMSELLER_PAYMENT_PROCESS_NO_CC;
		}
		// @todo: remove this
		// Phần kiểm tra ở trên luôn trả về JOOMSELLER_PAYMENT_PROCESS_NO_CC
		// trong hàm này có thể xuất ra submit form
		// hoặc là kiểm tra credit card với payment sử dụng credit card.
		// nếu không sử dung credit card thì luôn trả về JOOMSELLER_PAYMENT_PROCESS_NO_CC
		// nếu sử dụng thì trả về một trong 2 giá trị sau:
		// JOOMSELLER_PAYMENT_PROCESS_CC_SUCCESS
		// JOOMSELLER_PAYMENT_PROCESS_CC_FAIL
		//$products = htmlentities($products);

			$orderID  = rand(1,999999999);
			$client   = new SoapClient("http://www.jpws.me/directservice?wsdl");
			$res = $client->requestpayment($this->_pin, intval(round($order->total_price)), $order->notify_url, $orderID, urlencode('Invoice ID : '.$orderID));
			if($res['result'] == 1)
			{
				@session_start();
				$_SESSION['orderID'] = $orderID;
				$_SESSION['amount']  = intval(round($order->total_price));
				$_SESSION['au']      = $res['au'];
				$_SESSION['id']      = $order->id;
				echo '<div style="display:none;">'.$res['form'].'</div><script>document.forms[0].submit();</script>';
				echo '<p style="font:12px Tahoma; direction:rtl;text-align:center;color:#ff0000">در حال اتصال به درگاه ...</p>';
			}
			else
			{
				echo '<p style="font:12px Tahoma; direction:rtl;text-align:center;color:#ff0000">خطای غیر منتظره ('.$res['result'].') !!!</p>';
			}


		// init some default values
		//$this->addField('business',				$this->params->get('jahanpay_pin'));
		//$this->addField('cpp_header_image',		$this->params->get('merchant_image'));
		// this is an optional
		//$this->addField('bn',					$this->params->get('buynow_btn'));
		//if ($this->params->get('no_shipping'))
		//{
		//	$this->addField('no_shipping',		1);
		//}
		//$order->return_url);
		//$order->cancel_url);
		//$order->title);
		//$order->description);
		//'custom',			$order->id);
		//'currency_code',	$order->currency_code);
		//'invoice',			$order->invoice);
		//'order_id',			$order->id);

		return JOOMSELLER_PAYMENT_PROCESS_NO_CC;
	}

	/**
	 * Get order id from notification.
	 */
	function onPaymentNotify($payment_method) {		
		if ($payment_method != $this->_name) {
			return array();
		}

		@session_start();
		$au      = $_SESSION['au'];
		$id      = $_SESSION['id'];
		$orderID = (int)$_SESSION['orderID'];
		$amount  = (int)$_SESSION['amount'];
		$data	= array(			
			'order_id'			=> $id,//$post['custom'],
			'transaction_id'	=> $au//$post['txn_id']
		);
		return $data;
	}

	/**
	 * Verify payment notification.
	 */
	function onVerifyPayment($order) {
		if ($order->payment_method != $this->_name)
		{
			return false;
		}
		if($this->validate_ipn($order))
		{
			return array('status'	=>	$this->_ppnotify['order_stt']);
		}
		return true;
	}
	
	/**
	 * Get jahanpay IPN validation.
	 * @return boolean
	 */
	function validate_ipn($order)
	{
		@session_start();
		$id      = $_SESSION['id'];
		$au      = $_SESSION['au'];
		$orderID = (int)$_SESSION['orderID'];
		$amount  = (int)$_SESSION['amount'];
		$client = new SoapClient("http://www.jpws.me/directservice?wsdl");
		$res    = $client->verification($this->_api, $amount, $au, $orderID, $_POST + $_GET );
		if($res['result'] == 1)
		{
				$this->_ppnotify['order_stt']	= "COMPLETED";
				$mailsubject = "JahanPay IPN txn on your site";
				$mailbody = "Hello,\n\n";
				$mailbody .= "a JahanPay transaction for you has been made on your website!\n";
				$mailbody .= "-----------------------------------------------------------\n";
				$mailbody .= "Transaction ID: $trans_id\n";
				$mailbody .= "Order ID: ".$id."\n";
				$mailbody .= "Payment Status returned by JahanPay: $res[result]\n";
				$mailbody .= "Order Status Code: ".$this->_ppnotify['order_stt'];
				$this->_ppnotify['email_sbj']	= $mailsubject;
				$this->_ppnotify['email_body']	= $mailbody;
				return true;
		}
		else
		{
			$this->_ppnotify['order_stt']	= "FALSE";
			$mailsubject = "JahanPay IPN Transaction on your site";
			$mailbody = "Hello,
				 a Failed JahanPay Transaction on " . $this->_live_site . " requires your attention.
				 -----------------------------------------------------------
				 Order ID: " . $id . "
				 User ID: " . $order->user_id . "
				 Payment Status returned by JahanPay: $res[result]
			
				$error_description";
			$this->_ppnotify['email_sbj']	= $mailsubject;
			$this->_ppnotify['email_body']	= $mailbody;
			return true;			
		}
		return false;
	}
}