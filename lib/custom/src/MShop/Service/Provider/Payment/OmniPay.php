<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2016
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


use Omnipay\Omnipay as OPay;


/**
 * Payment provider for payment gateways supported by the Omnipay library.
 *
 * @package MShop
 * @subpackage Service
 */
class OmniPay
	extends \Aimeos\MShop\Service\Provider\Payment\Base
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $beConfig = array(
		'type' => array(
			'code' => 'type',
			'internalcode'=> 'type',
			'label'=> 'Payment provider type',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
		'onsite' => array(
			'code' => 'onsite',
			'internalcode'=> 'onsite',
			'label'=> 'Collect data locally',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'address' => array(
			'code' => 'address',
			'internalcode'=> 'address',
			'label'=> 'Send address to payment gateway too',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'authorize' => array(
			'code' => 'authorize',
			'internalcode'=> 'authorize',
			'label'=> 'Authorize payments and capture later',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'testmode' => array(
			'code' => 'testmode',
			'internalcode'=> 'testmode',
			'label'=> 'Test mode without payments',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
	);

	private $feConfig = array(
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

	private $provider;


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigBE()
	{
		$list = parent::getConfigBE();

		$prefix = $this->getConfigPrefix();
		$config = $this->beConfig;

		if( $prefix !== 'omnipay' ) {
			unset( $config['type'], $config['onsite'] );
		}

		foreach( $config as $key => $config )
		{
			$config['code'] = $prefix . '.' . $config['code'];
			$list[$prefix.'.'.$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
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

		$prefix = $this->getConfigPrefix();
		$config = $this->beConfig;
		$list = array();

		if( $prefix !== 'omnipay' ) {
			unset( $config['type'], $config['onsite'] );
		}

		foreach( $config as $key => $config )
		{
			$config['code'] = $prefix . '.' . $config['code'];
			$list[$prefix.'.'.$key] = $config;
		}

		return array_merge( $errors, $this->checkConfig( $list, $attributes ) );
	}


	/**
	 * Cancels the authorization for the given order if supported.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 */
	public function cancel( \Aimeos\MShop\Order\Item\Iface $order )
	{
		$provider = $this->getProvider();

		if( !$provider->supportsVoid() ) {
			return;
		}

		$base = $this->getOrderBase( $order->getBaseId() );
		$service = $base->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'transactionId' => $order->getId(),
		);

		$response = $provider->void( $data )->send();

		if( $response->isSuccessful() )
		{
			$status = \Aimeos\MShop\Order\Item\Base::PAY_CANCELED;
			$order->setPaymentStatus( $status );
			$this->saveOrder( $order );
		}
	}


	/**
	 * Captures the money later on request for the given order if supported.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 */
	public function capture( \Aimeos\MShop\Order\Item\Iface $order )
	{
		$provider = $this->getProvider();

		if( !$provider->supportsCapture() ) {
			return;
		}

		$base = $this->getOrderBase( $order->getBaseId() );
		$service = $base->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'transactionId' => $order->getId(),
		);

		$response = $provider->capture( $data )->send();

		if( $response->isSuccessful() )
		{
			$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
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
		$provider = $this->getProvider();

		switch( $what )
		{
			case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_CAPTURE:
				return $provider->supportsCapture();
			case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_CANCEL:
				return $provider->supportsVoid();
			case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REFUND:
				return $provider->supportsRefund();
		}

		return false;
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Standard Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = array() )
	{
		if( $this->getValue( 'onsite' ) == true && ( !isset( $params['number'] ) || !isset( $params['cvv'] ) ) ) {
			return $this->getPaymentForm( $order, $params );
		}

		return $this->processOrder( $order, $params );
	}


	/**
	 * Refunds the money for the given order if supported.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 */
	public function refund( \Aimeos\MShop\Order\Item\Iface $order )
	{
		$provider = $this->getProvider();

		if( !$provider->supportsRefund() ) {
			return;
		}

		$base = $this->getOrderBase( $order->getBaseId() );
		$service = $base->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT );

		$data = array(
			'transactionReference' => $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' ),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'transactionId' => $order->getId(),
		);

		$response = $provider->refund( $data )->send();

		if( $response->isSuccessful() )
		{
			$attr = array( 'REFUNDID' => $response->getTransactionReference() );
			$this->setAttributes( $service, $attr, 'payment/omnipay' );
			$this->saveOrderBase( $base );

			$status = \Aimeos\MShop\Order\Item\Base::PAY_REFUND;
			$order->setPaymentStatus( $status );
			$this->saveOrder( $order );
		}
	}


	/**
	 * Updates the orders for which status updates were received via direct requests (like HTTP).
	 *
	 * @param array $params Associative list of request parameters
	 * @param string|null $body Information sent within the body of the request
	 * @param string|null &$output Response body for notification requests
	 * @param array &$header Response headers for notification requests
	 * @return \Aimeos\MShop\Order\Item\Iface|null Order item if update was successful, null if the given parameters are not valid for this provider
	 */
	public function updateSync( array $params = array(), $body = null, &$output = null, array &$header = array() )
	{
		if( !isset( $params['orderid'] ) ) {
			return null;
		}

		$order = $this->getOrder( $params['orderid'] );
		$base = $this->getOrderBase( $order->getBaseId() );
		$service = $base->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT );

		$params['transactionId'] = $order->getId();
		$params['transactionReference'] = $service->getAttribute( 'TRANSACTIONID', 'payment/omnipay' );
		$params['amount'] = $this->getAmount( $base->getPrice() );
		$params['currency'] = $base->getLocale()->getCurrencyId();

		try
		{
			$provider = $this->getProvider();

			if( $this->getValue( 'authorize', false ) && $provider->supportsCompleteAuthorize() )
			{
				$response = $provider->completeAuthorize( $params )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
			}
			elseif( $provider->supportsCompletePurchase() )
			{
				$response = $provider->completePurchase( $params )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
			}
			else
			{
				return $order;
			}

			if( $response->isSuccessful() )
			{
				$this->saveTransationRef( $base, $response->getTransactionReference() );

				$order->setPaymentStatus( $status );
				$this->saveOrder( $order );
			}
			elseif( $response->isRedirect() )
			{
				$url = $response->getRedirectUrl();
				$header[] = array( 'HTTP/1.1 500 Unexpected redirect' );
				throw new \Aimeos\MShop\Service\Exception( sprintf( 'Unexpected redirect: %1$s', $url ) );
			}
			else
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED );
				$this->saveOrder( $order );

				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return $order;
	}


	/**
	 * Returns an Omnipay credit card object
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Order base object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return \Omnipay\Common\CreditCard Credit card object
	 */
	protected function getCardDetails( \Aimeos\MShop\Order\Item\Base\Iface $base, array $params )
	{
		if( $this->getValue( 'address' ) )
		{
			$addresses = $base->getAddresses();

			if( isset( $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT ] ) )
			{
				$addr = $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT];

				$params['billingName'] = $addr->getFirstname() . ' ' . $addr->getLastname();
				$params['billingFirstName'] = $addr->getFirstname();
				$params['billingLastName'] = $addr->getLastname();
				$params['billingCompany'] = $addr->getCompany();
				$params['billingAddress1'] = $addr->getAddress1();
				$params['billingAddress2'] = $addr->getAddress2();
				$params['billingCity'] = $addr->getCity();
				$params['billingPostcode'] = $addr->getPostal();
				$params['billingState'] = $addr->getState();
				$params['billingCountry'] = $addr->getCountryId();
				$params['billingPhone'] = $addr->getTelephone();
				$params['billingFax'] = $addr->getTelefax();
				$params['email'] = $addr->getEmail();

				if( isset( $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_DELIVERY ] ) ) {
					$addr = $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_DELIVERY];
				}

				$params['shippingName'] = $addr->getFirstname() . ' ' . $addr->getLastname();
				$params['shippingFirstName'] = $addr->getFirstname();
				$params['shippingLastName'] = $addr->getLastname();
				$params['shippingCompany'] = $addr->getCompany();
				$params['shippingAddress1'] = $addr->getAddress1();
				$params['shippingAddress2'] = $addr->getAddress2();
				$params['shippingCity'] = $addr->getCity();
				$params['shippingPostcode'] = $addr->getPostal();
				$params['shippingState'] = $addr->getState();
				$params['shippingCountry'] = $addr->getCountryId();
				$params['shippingPhone'] = $addr->getTelephone();
				$params['shippingFax'] = $addr->getTelefax();
			}
		}

		return new \Omnipay\Common\CreditCard( $params );
	}


	/**
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function getConfigPrefix()
	{
		return 'omnipay';
	}


	/**
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Basket object
	 * @param $orderid Unique order ID
	 * @param array $params Request parameter if available
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, $orderid, array $params )
	{
		$urls = $this->getPaymentUrls();
		$desc = $this->getContext()->getI18n()->dt( 'mshop', 'Order %1$s' );
		$card = $this->getCardDetails( $base, $params );

		$data = array(
			'token' => '',
			'card' => $card,
			'transactionId' => $orderid,
			'description' => sprintf( $desc, $orderid ),
			'amount' => $this->getAmount( $base->getPrice() ),
			'currency' => $base->getLocale()->getCurrencyId(),
			'language' => $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT )->getLanguageId(),
			'clientIp' => $this->getValue( 'client.ipaddress' ),
		) + $urls;

		return $data;
	}


	/**
	 * Returns the Omnipay gateway provider object.
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function getProvider()
	{
		if( !isset( $this->provider ) )
		{
			$this->provider = OPay::create( $this->getValue( 'type' ) );
			$this->provider->setTestMode( (bool) $this->getValue( 'testmode', false ) );
			$this->provider->initialize( $this->getServiceItem()->getConfig() );
		}

		return $this->provider;
	}


	/**
	 * Returns the required URLs
	 *
	 * @return array List of the Omnipay URL name as key and the URL string as value
	 */
	protected function getPaymentUrls()
	{
		return array(
			'returnUrl' => $this->getConfigValue( array( 'payment.url-success' ) ),
			'cancelUrl' => $this->getConfigValue( array( 'payment.url-cancel', 'payment.url-success' ) ),
			'notifyUrl' => $this->getConfigValue( array( 'payment.url-update' ) ),
		);
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return mixed Configuration value
	 */
	protected function getValue( $key, $default = null )
	{
		return $this->getConfigValue( array( $this->getConfigPrefix() . '.' . $key ), $default );
	}


	/**
	 * Returns the payment form for entering payment details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params )
	{
		$list = array();
		$feConfig = $this->feConfig;
		$baseItem = $this->getOrderBase( $order->getBaseId(), \Aimeos\MShop\Order\Manager\Base\Base::PARTS_ADDRESS );

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

			if( $this->getValue( 'address' ) )
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
		catch( \Aimeos\MShop\Order\Exception $e ) { ; } // If address isn't available

		$year = date( 'Y' );
		$feConfig['payment.expirymonth']['default'] = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 );
		$feConfig['payment.expiryyear']['default'] = array( $year, $year+1, $year+2, $year+3, $year+4, $year+5, $year+6, $year+7 );

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		$url = $this->getConfigValue( array( 'payment.url-self' ) );
		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $url, 'POST', $list, false );
	}


	/**
	 * Returns the form for redirecting customers to the payment gateway.
	 *
	 * @param \Omnipay\Common\Message\RedirectResponseInterface $response Omnipay response object
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Iface Form helper object
	 */
	protected function getRedirectForm( \Omnipay\Common\Message\RedirectResponseInterface $response )
	{
		$list = array();

		foreach( (array) $response->getRedirectData() as $key => $value )
		{
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( array(
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

		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $url, $method, $list );
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Standard Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	protected function processOrder( \Aimeos\MShop\Order\Item\Iface $order, array $params = array() )
	{
		$parts = \Aimeos\MShop\Order\Manager\Base\Base::PARTS_SERVICE | \Aimeos\MShop\Order\Manager\Base\Base::PARTS_ADDRESS;
		$base = $this->getOrderBase( $order->getBaseId(), $parts );
		$data = $this->getData( $base, $order->getId(), $params );
		$urls = $this->getPaymentUrls();

		try
		{
			$provider = $this->getProvider();

			if( $this->getValue( 'authorize', false ) && $provider->supportsAuthorize() )
			{
				$response = $provider->authorize( $data )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
			}
			else
			{
				$response = $provider->purchase( $data )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
			}

			if( $response->isSuccessful() )
			{
				$this->saveTransationRef( $base, $response->getTransactionReference() );

				$order->setPaymentStatus( $status );
				$this->saveOrder( $order );
			}
			elseif( $response->isRedirect() )
			{
				if( ( $ref = $response->getTransactionReference() ) != null ) {
					$this->saveTransationRef( $base, $ref );
				}

				return $this->getRedirectForm( $response );
			}
			else
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED );
				$this->saveOrder( $order );

				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $urls['returnUrl'], 'POST', array() );
	}


	/**
	 * Addes the transation reference to the order service attributes.
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $baseItem Order base object with service items attached
	 * @param string $ref Transaction reference from the payment gateway
	 */
	protected function saveTransationRef( \Aimeos\MShop\Order\Item\Base\Iface $baseItem, $ref )
	{
		$serviceItem = $baseItem->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT );

		$attr = array( 'TRANSACTIONID' => $ref );
		$this->setAttributes( $serviceItem, $attr, 'payment/omnipay' );
		$this->saveOrderBase( $baseItem );
	}
}
