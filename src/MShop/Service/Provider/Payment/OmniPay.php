<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2023
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


use Omnipay\Omnipay as OPay;
use Aimeos\MShop\Order\Item\Base as Status;


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
	private array $beConfig = array(
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
			'type' => 'bool',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'address' => array(
			'code' => 'address',
			'internalcode'=> 'address',
			'label'=> 'Send address to payment gateway too',
			'type' => 'bool',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'authorize' => array(
			'code' => 'authorize',
			'internalcode'=> 'authorize',
			'label'=> 'Authorize payments and capture later',
			'type' => 'bool',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'createtoken' => array(
			'code' => 'createtoken',
			'internalcode'=> 'createtoken',
			'label'=> 'Request token for recurring payments',
			'type' => 'bool',
			'internaltype'=> 'boolean',
			'default'=> '1',
			'required'=> false,
		),
		'testmode' => array(
			'code' => 'testmode',
			'internalcode'=> 'testmode',
			'label'=> 'Test mode without payments',
			'type' => 'bool',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
	);

	private array $feConfig = array(
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
	 * @return array List of attribute definitions implementing \Aimeos\Base\Critera\Attribute\Iface
	 */
	public function getConfigBE() : array
	{
		$list = [];

		foreach( $this->beConfig as $key => $config ) {
			$list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard( $config );
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
	public function checkConfigBE( array $attributes ) : array
	{
		return array_merge( parent::checkConfigBE( $attributes ), $this->checkConfig( $this->beConfig, $attributes ) );
	}


	/**
	 * Cancels the authorization for the given order if supported.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item object
	 */
	public function cancel( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$provider = $this->getProvider();

		if( !$provider->supportsVoid() ) {
			return $order;
		}

		$data = array(
			'transactionReference' => $this->getTransactionReference( $order ),
			'currency' => $order->getPrice()->getCurrencyId(),
			'amount' => $this->call( 'cancelAmount', $order ),
			'transactionId' => $order->getId(),
		);

		$response = $provider->void( $data )->send();

		if( $response->isSuccessful() ) {
			$order = $order->setStatusPayment( Status::PAY_CANCELED );
		}

		return $order;
	}


	/**
	 * Captures the money later on request for the given order if supported.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item object
	 */
	public function capture( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$provider = $this->getProvider();

		if( !$provider->supportsCapture() ) {
			return $order;
		}

		$data = $this->captureData( $order );
		$response = $provider->capture( $data )->send();

		if( $response->isSuccessful() ) {
			$this->call( 'captureStatus', $order );
		}

		return $order;
	}


	/**
	 * Checks what features the payment provider implements.
	 *
	 * @param int $what Constant from abstract class
	 * @return bool True if feature is available in the payment provider, false if not
	 */
	public function isImplemented( int $what ) : bool
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
			case \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REPAY:
				return method_exists( $provider, 'createCard' );
		}

		return false;
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface|null Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
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
	 * @param \Aimeos\MShop\Price\Item\Iface|null $price Price item with the amount to refund or NULL for whole order
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item object
	 */
	public function refund( \Aimeos\MShop\Order\Item\Iface $order, ?\Aimeos\MShop\Price\Item\Iface $price = null
		) : \Aimeos\MShop\Order\Item\Iface
	{
		$provider = $this->getProvider();

		if( !$provider->supportsRefund() ) {
			return $order;
		}

		$price = $price ?: $this->call( 'refundAmount', $order );
		$amount = $price->getValue() + $price->getCosts();

		$type = \Aimeos\MShop\Order\Item\Service\Base::TYPE_PAYMENT;
		$service = $this->getBasketService( $order, $type, $this->getServiceItem()->getCode() );

		$data = array(
			'transactionReference' => $this->getTransactionReference( $order ),
			'currency' => $price->getCurrencyId(),
			'transactionId' => $order->getId(),
			'amount' => $amount,
		);

		$response = $provider->refund( $data )->send();

		if( $response->isSuccessful() )
		{
			$tx = \Aimeos\MShop::create( $this->context(), 'order/service/transaction' )->create()
				->setPrice( $price )->setType( 'refund' )->setStatus( Status::PAY_REFUND )
				->setConfigValue( 'REFUNDID', $response->getTransactionReference() );

			$service->addTransaction( $tx );

			if( $amount == $order->getPrice()->getValue() + $order->getPrice()->getCosts() ) {
				$order->setStatusPayment( Status::PAY_REFUND );
			}
		}

		return $order;
	}


	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item object
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		if( !$this->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REPAY ) )
		{
			$msg = $this->context()->translate( 'mshop', 'Method "%1$s" for provider not available' );
			throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, 'repay' ) );
		}

		if( ( $cfg = $this->data( $order->getCustomerId(), 'repay' ) ) === null )
		{
			$msg = sprintf( 'No reoccurring payment data available for customer ID "%1$s"', $order->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( !isset( $cfg['token'] ) )
		{
			$msg = sprintf( 'No payment token available for customer ID "%1$s"', $order->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$data = array(
			'transactionId' => $order->getId(),
			'currency' => $order->getPrice()->getCurrencyId(),
			'amount' => $this->call( 'repayAmount', $order ),
			'cardReference' => $cfg['token'],
			'paymentPage' => false,
			'language' => 'en',
		);

		if( isset( $cfg['month'] ) && isset( $cfg['year'] ) )
		{
			$data['card'] = new \Omnipay\Common\CreditCard( [
				'expiryMonth' => $cfg['month'],
				'expiryYear' => $cfg['year'],
			] );
		}

		$response = $this->getProvider()->purchase( $data )->send();

		if( $response->isSuccessful() || $response->isPending() )
		{
			$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
			$order->setStatusPayment( Status::PAY_RECEIVED );
		}
		elseif( !$response->getTransactionReference() )
		{
			$msg = 'Token based payment incomplete: ' . print_r( $response->getData(), true );
			throw new \Aimeos\MShop\Service\Exception( $msg, 1 );
		}
		else
		{
			$msg = sprintf( 'Token based payment failed with code "%1$s" and message "%2$s": %3$s',
				$response->getCode(), $response->getMessage(), print_r( $response->getData(), true ) );
			throw new \Aimeos\MShop\Service\Exception( $msg, -1 );
		}

		return $order;
	}


	/**
	 * Updates the order status sent by payment gateway notifications
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request Request object
	 * @param \Psr\Http\Message\ResponseInterface $response Response object
	 * @return \Psr\Http\Message\ResponseInterface Response object
	 */
	public function updatePush( \Psr\Http\Message\ServerRequestInterface $request,
		\Psr\Http\Message\ResponseInterface $response ) : \Psr\Http\Message\ResponseInterface
	{
		try
		{
			$provider = $this->getProvider();
			$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

			if( !isset( $params['orderid'] ) ) {
				throw new \Aimeos\MShop\Service\Exception( 'No order ID available' );
			}

			if( !method_exists( $provider, 'supportsAcceptNotification' ) || !$provider->supportsAcceptNotification() ) {
				return $response;
			}

			$manager = \Aimeos\MShop::create( $this->context(), 'order' );
			$order = $manager->get( $params['orderid'], ['order', 'order/service'] );

			$omniRequest = $provider->acceptNotification();

			$order->setStatusPayment( $this->translateStatus( $omniRequest->getTransactionStatus() ) );
			$this->setOrderData( $order, ['Transaction' => $omniRequest->getTransactionReference()] );

			$this->save( $order );
			$response->withStatus( 200 );
		}
		catch( \Exception $e )
		{
			$response->withStatus( 500, $e->getMessage() );
		}

		return $response;
	}


	/**
	 * Updates the orders for whose status updates have been received by the confirmation page
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request Request object with parameters and request body
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item that should be updated
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 * @throws \Aimeos\MShop\Service\Exception If updating the orders failed
	 */
	public function updateSync( \Psr\Http\Message\ServerRequestInterface $request,
		\Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		try
		{
			$provider = $this->getProvider();

			$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();
			$params = $this->getData( $order, $order->getId(), $params );
			$params['transactionReference'] = $this->getTransactionReference( $order );

			if( $this->getValue( 'authorize', false ) && $provider->supportsCompleteAuthorize() )
			{
				$response = $provider->completeAuthorize( $params )->send();
				$status = Status::PAY_AUTHORIZED;
			}
			elseif( $provider->supportsCompletePurchase() )
			{
				$response = $provider->completePurchase( $params )->send();
				$status = Status::PAY_RECEIVED;
			}
			else
			{
				return $order;
			}

			if( $response->getRequest()->getTransactionId() != $order->getId() ) {
				return $order;
			}

			if( method_exists( $response, 'isSuccessful' ) && $response->isSuccessful() )
			{
				$order->setStatusPayment( $status );
			}
			elseif( method_exists( $response, 'isPending' ) && $response->isPending() )
			{
				$order->setStatusPayment( Status::PAY_PENDING );
			}
			elseif( method_exists( $response, 'isCancelled' ) && $response->isCancelled() )
			{
				$order->setStatusPayment( Status::PAY_CANCELED );
			}
			elseif( method_exists( $response, 'isRedirect' ) && $response->isRedirect() )
			{
				$msg = $this->context()->translate( 'mshop', 'Unexpected redirect: %1$s' );
				throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, $response->getRedirectUrl() ) );
			}
			elseif( $order->getStatusPayment() === Status::PAY_UNFINISHED
				&& (
					!$this->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY )
					|| (
						$this->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY )
						&& in_array( $this->query( $order )->getStatusPayment(), [Status::PAY_UNFINISHED, Status::PAY_REFUSED] )
					)
				)
			) {
				$this->save( $order->setStatusPayment( Status::PAY_REFUSED ) );
				throw new \Aimeos\MShop\Service\Exception( (string) $response->getMessage() );
			}

			if( !empty( $txId = $response->getTransactionReference() ) ) {
				$this->setOrderData( $order, ['Transaction' => $txId] );
			}

			$this->saveRepayData( $response, $order->getCustomerId() );
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return $order;
	}


	/**
	 * Returns the amount when cancelling an order
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return string Amount for cancellation, e.g. 100.00, 0.01 or 0.00
	 */
	protected function cancelAmount( \Aimeos\MShop\Order\Item\Iface $order ) : string
	{
		return $this->getAmount( $order->getPrice() );
	}


	/**
	 * Returns the amount when capturing an order
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return string Amount to capture, e.g. 100.00, 0.01 or 0.00
	 */
	protected function captureAmount( \Aimeos\MShop\Order\Item\Iface $order ) : string
	{
		return $this->getAmount( $order->getPrice() );
	}


	/**
	 * Returns the data sent to the payment gateway for capturing
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return array Associative list of key/value pairs
	 */
	protected function captureData( \Aimeos\MShop\Order\Item\Iface $order ) : array
	{
		return [
			'transactionReference' => $this->getTransactionReference( $order ),
			'currency' => $order->getPrice()->getCurrencyId(),
			'amount' => $this->call( 'captureAmount', $order ),
			'transactionId' => $order->getId(),
		];
	}


	/**
	 * Sets the payment status of of the captured order and products
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 */
	protected function captureStatus( \Aimeos\MShop\Order\Item\Iface $order )
	{
		return $order->setStatusPayment( Status::PAY_RECEIVED );
	}


	/**
	 * Returns an Omnipay credit card object
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return \Omnipay\Common\CreditCard Credit card object
	 */
	protected function getCardDetails( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Omnipay\Common\CreditCard
	{
		if( $this->getValue( 'address' ) )
		{
			$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

			if( ( $addr = current( $addresses ) ) !== false )
			{
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

				$type = \Aimeos\MShop\Order\Item\Address\Base::TYPE_DELIVERY;
				$addr = current( $order->getAddress( $type ) ) ?: $addr;

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
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Basket object
	 * @param string $orderid string Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Iface $order, string $orderid, array $params ) : array
	{
		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) === false ) {
			$langid = $this->context()->locale()->getLanguageId();
		} else {
			$langid = $address->getLanguageId();
		}

		$data = array(
			'language' => $langid,
			'transactionId' => $orderid,
			'amount' => $this->getAmount( $order->getPrice() ),
			'currency' => $order->locale()->getCurrencyId(),
			'description' => sprintf( $this->context()->translate( 'mshop', 'Order %1$s' ), $orderid ),
			'clientIp' => $this->getValue( 'client.ipaddress' ),
		);

		if( $this->getValue( 'createtoken', false ) ) {
			$data['createCard'] = true;
		}

		if( $this->getValue( 'onsite', false ) || $this->getValue( 'address', false ) ) {
			$data['card'] = $this->getCardDetails( $order, $params );
		}

		return $data + $this->getPaymentUrls();
	}


	/**
	 * Returns the Omnipay gateway provider object.
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function getProvider() : \Omnipay\Common\GatewayInterface
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
	protected function getPaymentUrls() : array
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
	protected function getValue( string $key, $default = null )
	{
		return $this->getConfigValue( $key, $default );
	}


	/**
	 * Returns the payment form for entering payment details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];
		$feConfig = $this->feConfig;
		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) !== false )
		{
			if( !isset( $params[$feConfig['payment.firstname']['internalcode']] )
				|| $params[$feConfig['payment.firstname']['internalcode']] == ''
			) {
				$feConfig['payment.firstname']['default'] = $address->getFirstname();
			}

			if( !isset( $params[$feConfig['payment.lastname']['internalcode']] )
				|| $params[$feConfig['payment.lastname']['internalcode']] == ''
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

		$year = date( 'Y' );
		$feConfig['payment.expirymonth']['default'] = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 );
		$feConfig['payment.expiryyear']['default'] = array( $year, $year + 1, $year + 2, $year + 3, $year + 4, $year + 5, $year + 6, $year + 7 );

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard( $config );
		}

		$url = $this->getConfigValue( 'payment.url-self', '' );
		return new \Aimeos\MShop\Common\Helper\Form\Standard( $url, 'POST', $list, false );
	}


	/**
	 * Returns the form for redirecting customers to the payment gateway.
	 *
	 * @param \Omnipay\Common\Message\RedirectResponseInterface $response Omnipay response object
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form helper object
	 */
	protected function getRedirectForm( \Omnipay\Common\Message\RedirectResponseInterface $response ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];

		foreach( (array) $response->getRedirectData() as $key => $value )
		{
			$list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard( array(
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

		return new \Aimeos\MShop\Common\Helper\Form\Standard( $url, $method, $list );
	}


	/**
	 * Returns the payment transaction ID stored in the basket
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Basket including (payment) service items
	 * @return string|null Payment transaction ID or null if not available
	 */
	protected function getTransactionReference( \Aimeos\MShop\Order\Item\Iface $order ) : ?string
	{
		$type = \Aimeos\MShop\Order\Item\Service\Base::TYPE_PAYMENT;
		$service = $this->getBasketService( $order, $type, $this->getServiceItem()->getCode() );

		return $service->getAttribute( 'Transaction', 'payment/omnipay' );
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	protected function processOrder( \Aimeos\MShop\Order\Item\Iface $order,
		array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
	{
		$data = $this->getData( $order, $order->getId(), $params );
		$urls = $this->getPaymentUrls();

		try
		{
			$response = $this->sendRequest( $order, $data );

			if( $response->isSuccessful() )
			{
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
				$this->saveRepayData( $response, $order->getCustomerId() );

				$status = $this->getValue( 'authorize', false ) ? Status::PAY_AUTHORIZED : Status::PAY_RECEIVED;
				$order->setStatusPayment( $status );
			}
			elseif( $response->isRedirect() )
			{
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
				return $this->getRedirectForm( $response );
			}
			else
			{
				\Aimeos\MShop::create( $this->context(), 'order' )->save( $order->setStatusPayment( Status::PAY_REFUSED ) );
				throw new \Aimeos\MShop\Service\Exception( (string) $response->getMessage() );
			}
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return new \Aimeos\MShop\Common\Helper\Form\Standard( $urls['returnUrl'] ?? '', 'POST', [] );
	}


	/**
	 * Returns the amount when refunding an order
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return string Amount to refund, e.g. 100.00, 0.01 or 0.00
	 */
	protected function refundAmount( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Price\Item\Iface
	{
		return $order->getPrice();
	}


	/**
	 * Returns the amount when repaying an order
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return string Amount for subscription, e.g. 100.00, 0.01 or 0.00
	 */
	protected function repayAmount( \Aimeos\MShop\Order\Item\Iface $order ) : string
	{
		return $this->getAmount( $order->getPrice() );
	}


	/**
	 * Saves the required data for recurring payments in the customer profile
	 *
	 * @param \Omnipay\Common\Message\ResponseInterface $response Omnipay response object
	 * @param string $customerId Unique customer ID
	 * @return \Aimeos\MShop\Service\Provider\Payment\Iface Same object for fluent interface
	 */
	protected function saveRepayData( \Omnipay\Common\Message\ResponseInterface $response,
		string $customerId ) : \Aimeos\MShop\Service\Provider\Payment\Iface
	{
		$data = [];

		if( method_exists( $response, 'getCardReference' ) ) {
			$data['token'] = $response->getCardReference();
		}

		if( method_exists( $response, 'getExpiryMonth' ) ) {
			$data['month'] = $response->getExpiryMonth();
		}

		if( method_exists( $response, 'getExpiryYear' ) ) {
			$data['year'] = $response->getExpiryYear();
		}

		if( !empty( $data ) ) {
			$this->setData( $customerId, 'repay', $data );
		}

		return $this;
	}


	/**
	 * Returns the value or values from the given order and passed code
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order including basket content
	 * @param string $code Attribute code to retrieve the value for
	 * @return array|string|null Value or list of values from the order service attribute
	 */
	protected function getOrderData( \Aimeos\MShop\Order\Item\Iface $order, string $code )
	{
		$type = \Aimeos\MShop\Order\Item\Service\Base::TYPE_PAYMENT;

		return $this->getBasketService( $order, $type, $this->getServiceItem()->getCode() )
			->getAttribute( $code, 'payment/omnipay' );
	}


	/**
	 * Adds or overwrites the attribute pairs in the given order
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order including basket content
	 * @param array $data Associative list of key/value pairs to store
	 * @return \Aimeos\MShop\Service\Provider\Payment\Iface Same object for fluid interface
	 */
	protected function setOrderData( \Aimeos\MShop\Order\Item\Iface $order, array $data ) : Iface
	{
		$type = \Aimeos\MShop\Order\Item\Service\Base::TYPE_PAYMENT;

		$this->getBasketService( $order, $type, $this->getServiceItem()->getCode() )
			->addAttributeItems( $this->attributes( $data, 'payment/omnipay' ) );

		return $this;
	}


	/**
	 * Sends the given data for the order to the payment gateway
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item which should be paid
	 * @param array $data Associative list of key/value pairs sent to the payment gateway
	 * @return \Omnipay\Common\Message\ResponseInterface Omnipay response from the payment gateway
	 */
	protected function sendRequest( \Aimeos\MShop\Order\Item\Iface $order, array $data ) : \Omnipay\Common\Message\ResponseInterface
	{
		$provider = $this->getProvider();

		if( $this->getValue( 'authorize', false ) && $provider->supportsAuthorize() ) {
			$response = $provider->authorize( $data )->send();
		} else {
			$response = $provider->purchase( $data )->send();
		}

		return $response;
	}


	/**
	 * Translates the Omnipay status into the Aimeos payment status value
	 *
	 * @param string $status Omnipay payment status
	 * @return int|null Aimeos payment status value or null for no new status
	 */
	protected function translateStatus( string $status ) : ?int
	{
		if( !interface_exists( '\Omnipay\Common\Message\NotificationInterface' ) ) {
			return Status::PAY_REFUSED;
		}

		switch( $status )
		{
			case \Omnipay\Common\Message\NotificationInterface::STATUS_COMPLETED:
				return Status::PAY_RECEIVED;
			case \Omnipay\Common\Message\NotificationInterface::STATUS_PENDING:
				return Status::PAY_PENDING;
			case \Omnipay\Common\Message\NotificationInterface::STATUS_FAILED:
				return Status::PAY_REFUSED;
		}

		return null;
	}
}
