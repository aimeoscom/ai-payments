<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2018-2022
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payment provider for Datatrans
 *
 * @package MShop
 * @subpackage Service
 */
class Datatrans
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $beConfig = array(
		'password' => array(
			'code' => 'password',
			'internalcode'=> 'password',
			'label'=> 'Password for server to server connections',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
		),
	);


	/**
	 * Checks the backend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes added by the shop owner in the administraton interface
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 * 	known by the provider but aren't valid resp. null for attributes whose values are OK
	 */
	public function checkConfigBE( array $attributes ) : array
	{
		return array_merge( parent::checkConfigBE( $attributes ), $this->checkConfig( $this->beConfig, $attributes ) );
	}


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigBE() : array
	{
		$list = parent::getConfigBE();

		foreach( $this->beConfig as $key => $config ) {
			$list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}


	/**
	 * Checks what features the payment provider implements.
	 *
	 * @param int $what Constant from abstract class
	 * @return bool True if feature is available in the payment provider, false if not
	 */
	public function isImplemented( int $what ) : bool
	{
		return $what === \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY || parent::isImplemented( $what );
	}


	/**
	 * Queries for status updates for the given order compare with the responseCode
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 */
	public function query( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$response = $this->getProvider()->getTransaction( ['transactionId' => $order->getId()] )->send();

		if( $response->isSuccessful() )
		{
			$this->checkAmount( $order, $response );

			if( in_array( $response->getResponseCode(), [2, 3, 21] ) ) {
				$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED );
			} elseif( $response->getResponseCode() == 1 ) {
				$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED );
			}
		}
		elseif( method_exists($response, 'isPending') && $response->isPending() )
		{
			$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_PENDING );
		}
		elseif( method_exists($response, 'isCancelled') && $response->isCancelled() )
		{
			$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_CANCELED );
		}

		$this->setOrderData( $order, ['TRANSACTIONID' => $response->getTransactionReference()] );
		return $this->saveOrder( $order );
	}


	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$base = $this->getOrderBase( $order->getBaseId() );

		if( ( $cfg = $this->getCustomerData( $base->getCustomerId(), 'repay' ) ) === null )
		{
			$msg = sprintf( 'No reoccurring payment data available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( !isset( $cfg['token'] ) )
		{
			$msg = sprintf( 'No payment token available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$data = array(
			'transactionId' => $order->getId(),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'cardReference' => $cfg['token'],
			'paymentPage' => false,
		);

		if( isset( $cfg['month'] ) && isset( $cfg['year'] ) )
		{
			$data['card'] = new \Omnipay\Common\CreditCard( [
				'expiryMonth' => $cfg['month'],
				'expiryYear' => $cfg['year'],
			] );
		}

		$response = $this->getXmlProvider()->purchase( $data )->send();

		if( $response->isSuccessful() || $response->isPending() )
		{
			$this->setOrderData( $order, ['TRANSACTIONID' => $response->getTransactionReference()] );
			$order = $this->saveOrder( $order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED ) );
		}
		elseif( !$response->getTransactionReference() )
		{
			$msg = $this->context()->translate( 'mshop', 'Token based payment incomplete: %1$s' );
			// response data may contain payment details, so don't log it
			throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, $response->getMessage() ), 1 );
		}
		else
		{
			$str = ( method_exists( $response, 'getMessage' ) ? $response->getMessage() : '' );
			$msg = $this->context()->translate( 'mshop', 'Token based payment failed: %1$s' );
			throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, $str ), -1 );
		}

		return $order;
	}


	/**
	 * Checks if the paid amount and currency match the order
	 *
	 * The amount and currency aren't signed without the HMAC key and can be modified by the customer.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @param \Omnipay\Common\Message\ResponseInterface $response Transaction status response
	 * @throws \Aimeos\MShop\Service\Exception If amount or currency don't match
	 */
	protected function checkAmount( \Aimeos\MShop\Order\Item\Iface $order, \Omnipay\Common\Message\ResponseInterface $response ) : void
	{
		$price = $this->getOrderBase( $order->getBaseId() )->getPrice();
		$digits = ( new \Money\Currencies\ISOCurrencies() )->subunitFor( new \Money\Currency( $price->getCurrencyId() ) );
		$amount = (int) str_replace( '.', '', $this->getAmount( $price, true, true, $digits ) );

		if( $response->getCurrencyCode() !== $price->getCurrencyId() || $response->getAmountMinorUnits() !== $amount )
		{
			$msg = sprintf( 'Paid amount or currency for order "%1$s" doesn\'t match', $order->getId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}
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
		switch( $key ) {
			case 'type': return 'Datatrans';
		}

		return parent::getValue( $key, $default );
	}


	/**
	 * Returns the Datatrans XML payment provider
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function getXmlProvider() : \Omnipay\Common\GatewayInterface
	{
		return $this->createGateway( 'Datatrans\Xml' );
	}
}
