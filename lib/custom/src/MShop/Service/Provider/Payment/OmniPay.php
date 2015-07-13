<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Service
 */


use Omnipay\Omnipay;


/**
 * Payment provider for payment gateways supported by the Omnipay library.
 *
 * @package MShop
 * @subpackage Service
 */
class MShop_Service_Provider_Payment_OmniPay
	extends MShop_Service_Provider_Payment_Abstract
	implements MShop_Service_Provider_Payment_Interface
{
	private $_beConfig = array(
		'omnipay.type' => array(
			'code' => 'omnipay.type',
			'internalcode'=> 'omnipay.type',
			'label'=> 'Payment provider type',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
		'omnipay.address' => array(
			'code' => 'omnipay.address',
			'internalcode'=> 'omnipay.address',
			'label'=> 'Send address to payment gateway too',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'omnipay.authorize' => array(
			'code' => 'omnipay.authorize',
			'internalcode'=> 'omnipay.authorize',
			'label'=> 'Authorize payments and capture later',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'omnipay.onsite' => array(
			'code' => 'omnipay.onsite',
			'internalcode'=> 'omnipay.onsite',
			'label'=> 'Collect data locally',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'omnipay.testmode' => array(
			'code' => 'omnipay.testmode',
			'internalcode'=> 'omnipay.testmode',
			'label'=> 'Test mode without payments',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
	);

	private $_feConfig = array(
		'payment.firstname' => array(
			'code' => 'payment.firstname',
			'internalcode'=> 'firstName',
			'label'=> 'First name',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false
		),
		'payment.lastname' => array(
			'code' => 'payment.lastname',
			'internalcode'=> 'lastName',
			'label'=> 'Last name',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'payment.cardno' => array(
			'code' => 'payment.cardno',
			'internalcode'=> 'number',
			'label'=> 'Credit card number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'payment.cvv' => array(
			'code' => 'payment.cvv',
			'internalcode'=> 'cvv',
			'label'=> 'Verification number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'payment.expirymonth' => array(
			'code' => 'payment.expirymonth',
			'internalcode'=> 'expiryMonth',
			'label'=> 'Expiry month',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'payment.expiryyear' => array(
			'code' => 'payment.expiryyear',
			'internalcode'=> 'expiryYear',
			'label'=> 'Expiry year',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'payment.company' => array(
			'code' => 'payment.company',
			'internalcode'=> 'company',
			'label'=> 'Company',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.address1' => array(
			'code' => 'payment.address1',
			'internalcode'=> 'billingAddress1',
			'label'=> 'Street',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.address2' => array(
			'code' => 'payment.address2',
			'internalcode'=> 'billingAddress2',
			'label'=> 'Additional',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.city' => array(
			'code' => 'payment.city',
			'internalcode'=> 'billingCity',
			'label'=> 'City',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.postal' => array(
			'code' => 'payment.postal',
			'internalcode'=> 'billingPostcode',
			'label'=> 'Zip code',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.state' => array(
			'code' => 'payment.state',
			'internalcode'=> 'billingState',
			'label'=> 'State',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.countryid' => array(
			'code' => 'payment.countryid',
			'internalcode'=> 'billingCountry',
			'label'=> 'Country',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.telephone' => array(
			'code' => 'payment.telephone',
			'internalcode'=> 'billingPhone',
			'label'=> 'Telephone',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'payment.email' => array(
			'code' => 'payment.email',
			'internalcode'=> 'email',
			'label'=> 'E-Mail',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
	);

	private $_provider;


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing MW_Common_Critera_Attribute_Interface
	 */
	public function getConfigBE()
	{
		$list = parent::getConfigBE();

		foreach( $this->_beConfig as $key => $config ) {
			$list[$key] = new MW_Common_Criteria_Attribute_Default( $config );
		}

		return $list;
	}


	/**
	 * Checks the backend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes added by the shop owner in the administraton interface
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 * 	known by the provider but aren't valid
	 */
	public function checkConfigBE( array $attributes )
	{
		$errors = parent::checkConfigBE( $attributes );

		return array_merge( $errors, $this->_checkConfig( $this->_beConfig, $attributes ) );
	}


	/**
	 * Cancels the authorization for the given order if supported.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 */
	public function cancel( MShop_Order_Item_Interface $order )
	{
		$provider = $this->_getProvider();

		if( !$provider->supportsVoid() ) {
			return;
		}

		$base = $this->_getOrderBase( $order->getBaseId() );
		$service = $base->getService( MShop_Order_Item_Base_Service_Abstract::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $base->getPrice()->getValue(),
			'transactionId' => $order->getId(),
		);

		$response = $provider->void( $data )->send();

		if( $response->isSuccessful() )
		{
			$status = MShop_Order_Item_Abstract::PAY_CANCELED;
			$order->setPaymentStatus( $status );
			$this->_saveOrder( $order );
		}
	}


	/**
	 * Captures the money later on request for the given order if supported.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 */
	public function capture( MShop_Order_Item_Interface $order )
	{
		$provider = $this->_getProvider();

		if( !$provider->supportsCapture() ) {
			return;
		}

		$base = $this->_getOrderBase( $order->getBaseId() );
		$service = $base->getService( MShop_Order_Item_Base_Service_Abstract::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $base->getPrice()->getValue(),
			'transactionId' => $order->getId(),
		);

		$response = $provider->capture( $data )->send();

		if( $response->isSuccessful() )
		{
			$status = MShop_Order_Item_Abstract::PAY_RECEIVED;
			$order->setPaymentStatus( $status );
		}
	}


	/**
	 * Checks what features the payment provider implements.
	 *
	 * @param integer $what Constant from abstract class
	 * @return boolean True if feature is available in the payment provider, false if not
	 */
	public function isImplemented( $what )
	{
		$provider = $this->_getProvider();

		switch( $what )
		{
			case MShop_Service_Provider_Payment_Abstract::FEAT_CAPTURE:
				return $provider->supportsCapture();
			case MShop_Service_Provider_Payment_Abstract::FEAT_CANCEL:
				return $provider->supportsVoid();
			case MShop_Service_Provider_Payment_Abstract::FEAT_REFUND:
				return $provider->supportsRefund();
		}

		return false;
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return MShop_Common_Item_Helper_Form_Default Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( MShop_Order_Item_Interface $order, array $params = array() )
	{
		if( $this->_getValue( 'onsite' ) == true && ( !isset( $params['number'] ) || !isset( $params['cvv'] ) ) ) {
			return $this->_getPaymentForm( $order, $params );
		}

		return $this->_process( $order, $params );
	}


	/**
	 * Refunds the money for the given order if supported.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 */
	public function refund( MShop_Order_Item_Interface $order )
	{
		$provider = $this->_getProvider();

		if( !$provider->supportsRefund() ) {
			return;
		}

		$base = $this->_getOrderBase( $order->getBaseId() );
		$service = $base->getService( MShop_Order_Item_Base_Service_Abstract::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $base->getPrice()->getValue(),
			'transactionId' => $order->getId(),
		);

		$response = $provider->refund( $data )->send();

		if( $response->isSuccessful() )
		{
			$attr = array( 'REFUNDID' => $response->getTransactionReference() );
			$this->_setAttributes( $service, $attr, 'payment/omnipay' );
			$this->_saveOrderBase( $base );

			$status = MShop_Order_Item_Abstract::PAY_REFUND;
			$order->setPaymentStatus( $status );
			$this->_saveOrder( $order );
		}
	}


	/**
	 * Updates the orders for which status updates were received via direct requests (like HTTP).
	 *
	 * @param array $params Associative list of request parameters
	 * @param string|null $body Information sent within the body of the request
	 * @param string|null &$output Response body for notification requests
	 * @param array &$header Response headers for notification requests
	 * @return MShop_Order_Item_Interface|null Order item if update was successful, null if the given parameters are not valid for this provider
	 */
	public function updateSync( array $params = array(), $body = null, &$output = null, array &$header = array() )
	{
		if( !isset( $params['orderid'] ) ) {
			return null;
		}

		$order = $this->_getOrder( $params['orderid'] );
		$baseItem = $this->_getOrderBase( $order->getBaseId() );

		$params['transactionId'] = $order->getId();
		$params['amount'] = $baseItem->getPrice()->getValue();
		$params['currency'] = $baseItem->getLocale()->getCurrencyId();

		try
		{
			$provider = $this->_getProvider();

			if( $this->_getValue( 'authorize', false ) && $provider->supportsCompleteAuthorize() )
			{
				$response = $provider->completeAuthorize( $params )->send();
				$status = MShop_Order_Item_Abstract::PAY_AUTHORIZED;
			}
			elseif( $provider->supportsCompletePurchase() )
			{
				$response = $provider->completePurchase( $params )->send();
				$status = MShop_Order_Item_Abstract::PAY_RECEIVED;
			}
			else
			{
				return $order;
			}

			if( $response->isSuccessful() )
			{
				$this->_saveTransationRef( $baseItem, $response->getTransactionReference() );

				$order->setPaymentStatus( $status );
				$this->_saveOrder( $order );
			}
			elseif( $response->isRedirect() )
			{
				$url = $response->getRedirectUrl();
				$header[] = array( 'HTTP/1.1 500 Unexpected redirect' );
				throw new MShop_Service_Exception( sprintf( 'Unexpected redirect: %1$s', $url ) );
			}
			else
			{
				$order->setPaymentStatus( MShop_Order_Item_Abstract::PAY_REFUSED );
				$this->_saveOrder( $order );

				throw new MShop_Service_Exception( $response->getMessage() );
			}
		}
		catch( Exception $e )
		{
			throw new MShop_Service_Exception( $e->getMessage() );
		}

		return $order;
	}


	/**
	 * Returns the Omnipay gateway provider object.
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function _getProvider()
	{
		if( !isset( $this->_provider ) )
		{
			$this->_provider = Omnipay::create( $this->_getProviderType() );
			$this->_provider->setTestMode( (bool) $this->_getValue( 'testmode', false ) );
			$this->_provider->initialize( $this->getServiceItem()->getConfig() );
		}

		return $this->_provider;
	}


	/**
	 * Returns the Omnipay gateway provider name.
	 *
	 * @return string Gateway provider name
	 */
	protected function _getProviderType()
	{
		return $this->_getValue( 'type' );
	}


	/**
	 * Returns the required URLs
	 *
	 * @return array List of the Omnipay URL name as key and the URL string as value
	 */
	protected function _getPaymentUrls()
	{
		return array(
			'returnUrl' => $this->_getConfigValue( array( 'payment.url-success' ) ),
			'cancelUrl' => $this->_getConfigValue( array( 'payment.url-cancel', 'payment.url-success' ) ),
			'notifyUrl' => $this->_getConfigValue( array( 'payment.url-update' ) ),
		);
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return string Configuration value
	 */
	protected function _getValue( $key, $default = null )
	{
		return $this->_getConfigValue( array( 'omnipay.' . $key ), $default );
	}


	/**
	 * Returns the payment form for entering payment details at the shop site.
	 *
	 * @param MShop_Order_Item_Interface $order Order object
	 * @param array $params Request parameter if available
	 * @return MShop_Common_Item_Helper_Form_Interface Form helper object
	 */
	protected function _getPaymentForm( MShop_Order_Item_Interface $order, array $params )
	{
		$list = array();
		$feConfig = $this->_feConfig;
		$baseItem = $this->_getOrderBase( $order->getBaseId(), MShop_Order_Manager_Base_Abstract::PARTS_ADDRESS );

		try
		{
			$address = $baseItem->getAddress();

			if( !isset( $params[ $feConfig['payment.firstname']['internalcode'] ] )
				|| $params[ $feConfig['payment.firstname']['internalcode'] ] == ''
			) {
				$feConfig['payment.firstname']['default'] = $address->getFirstname();
			}

			if( !isset( $params[ $feConfig['payment.lastname']['internalcode'] ] )
				|| $params[ $feConfig['payment.lastname']['internalcode'] ] == ''
			) {
				$feConfig['payment.lastname']['default'] = $address->getLastname();
			}

			if( $this->_getValue( 'address' ) )
			{
				$feConfig['payment.address1']['default'] = $address->getAddress1();
				$feConfig['payment.address2']['default'] = $address->getAddress2();
				$feConfig['payment.city']['default'] = $address->getCity();
				$feConfig['payment.postal']['default'] = $address->getPostal();
				$feConfig['payment.state']['default'] = $address->getState();
				$feConfig['payment.countryid']['default'] = $address->getCountryId();
				$feConfig['payment.telephone']['default'] = $address->getTelephone();
				$feConfig['payment.company']['default'] = $address->getCompany();
				$feConfig['payment.email']['default'] = $address->getEmail();
			}
		}
		catch( MShop_Order_Exception $e ) { ; } // If address isn't available

		$year = date( 'Y' );
		$feConfig['payment.expirymonth']['default'] = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 );
		$feConfig['payment.expiryyear']['default'] = array( $year, $year+1, $year+2, $year+3, $year+4, $year+5, $year+6, $year+7 );

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new MW_Common_Criteria_Attribute_Default( $config );
		}

		$url = $this->_getConfigValue( array( 'payment.url-self' ) );
		return new MShop_Common_Item_Helper_Form_Default( $url, 'POST', $list, false );
	}


	/**
	 * Returns the form for redirecting customers to the payment gateway.
	 *
	 * @param \Omnipay\Common\Message\RedirectResponseInterface $response Omnipay response object
	 * @return MShop_Common_Item_Helper_Form_Interface Form helper object
	 */
	protected function _getRedirectForm( \Omnipay\Common\Message\RedirectResponseInterface $response )
	{
		$list = array();

		foreach( (array) $response->getRedirectData() as $key => $value )
		{
			$list[$key] = new MW_Common_Criteria_Attribute_Default( array(
				'label' => $key,
				'code' => $key,
				'type' => 'string',
				'internalcode' => $key,
				'internaltype' => 'string',
				'default' => $value,
				'public' => false,
			) );
		}

		$url = $response->getRedirectUrl();
		$method = $response->getRedirectMethod();

		return new MShop_Common_Item_Helper_Form_Default( $url, $method, $list );
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return MShop_Common_Item_Helper_Form_Default Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	protected function _process( MShop_Order_Item_Interface $order, array $params = array() )
	{
		$urls = $this->_getPaymentUrls();
		$baseItem = $this->_getOrderBase( $order->getBaseId() );

		$desc = $this->_getContext()->getI18n()->dt( 'mshop', 'Order %1$s' );
		$orderid = $order->getId();

		$data = array(
			'token' => '',
			'card' => $params,
			'transactionId' => $orderid,
			'description' => sprintf( $desc, $orderid ),
			'amount' => $baseItem->getPrice()->getValue(),
			'currency' => $baseItem->getLocale()->getCurrencyId(),
			'clientIp' => $this->_getConfigValue( array( 'client.ipaddress' ) ),
		) + $urls;

		try
		{
			$provider = $this->_getProvider();

			if( $this->_getValue( 'authorize', false ) && $provider->supportsAuthorize() )
			{
				$response = $provider->authorize( $data )->send();
				$status = MShop_Order_Item_Abstract::PAY_AUTHORIZED;
			}
			else
			{
				$response = $provider->purchase( $data )->send();
				$status = MShop_Order_Item_Abstract::PAY_RECEIVED;
			}

			if( $response->isSuccessful() )
			{
				$this->_saveTransationRef( $baseItem, $response->getTransactionReference() );

				$order->setPaymentStatus( $status );
				$this->_saveOrder( $order );
			}
			elseif( $response->isRedirect() )
			{
				return $this->_getRedirectForm( $response );
			}
			else
			{
				$order->setPaymentStatus( MShop_Order_Item_Abstract::PAY_REFUSED );
				$this->_saveOrder( $order );

				throw new MShop_Service_Exception( $response->getMessage() );
			}
		}
		catch( Exception $e )
		{
			throw new MShop_Service_Exception( $e->getMessage() );
		}

		return new MShop_Common_Item_Helper_Form_Default( $urls['returnUrl'], 'POST', array() );
	}


	/**
	 * Addes the transation reference to the order service attributes.
	 *
	 * @param MShop_Order_Item_Base_Interface $baseItem Order base object with service items attached
	 * @param string $ref Transaction reference from the payment gateway
	 */
	protected function _saveTransationRef( MShop_Order_Item_Base_Interface $baseItem, $ref )
	{
		$serviceItem = $baseItem->getService( MShop_Order_Item_Base_Service_Abstract::TYPE_PAYMENT );

		$attr = array( 'TRANSACTIONID' => $ref );
		$this->_setAttributes( $serviceItem, $attr, 'payment/omnipay' );
		$this->_saveOrderBase( $baseItem );
	}
}
