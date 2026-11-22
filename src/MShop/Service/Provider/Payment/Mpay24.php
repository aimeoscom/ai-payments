<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2020-2025
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * mpay24 payment provider
 *
 * @package MShop
 * @subpackage Service
 */
class Mpay24
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
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
	 * Queries the payment gateway for the payment status of the given order
	 *
	 * The notifications sent by mPAY24 aren't signed, so the status must be fetched from the gateway.
	 * The paid amount and currency must match the order because the customer can pay a different amount.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 * @throws \Aimeos\MShop\Service\Exception If the payment status can't be retrieved
	 */
	public function query( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$response = $this->getProvider()->fetchTransaction( ['transactionId' => $order->getId()] )->send();

		if( $response->getOperationStatus() !== 'OK' )
		{
			$msg = sprintf( 'Payment status for order "%1$s" not available: %2$s', $order->getId(), $response->getReturnCode() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$status = match( $response->getTransactionState() ) {
			'BILLED' => \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED,
			'RESERVED' => \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED,
			'REVERSED' => \Aimeos\MShop\Order\Item\Base::PAY_CANCELED,
			'CREDITED' => \Aimeos\MShop\Order\Item\Base::PAY_REFUND,
			'ERROR' => \Aimeos\MShop\Order\Item\Base::PAY_REFUSED,
			default => null,
		};

		if( in_array( $status, [\Aimeos\MShop\Order\Item\Base::PAY_RECEIVED, \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED], true ) ) {
			$this->checkAmount( $order, $response );
		}

		if( $status !== null ) {
			$order->setStatusPayment( $status );
		}

		if( !empty( $txId = $response->getTransactionReference() ) ) {
			$this->setOrderData( $order, ['Transaction' => $txId] );
		}

		return $order;
	}


	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order ): \Aimeos\MShop\Order\Item\Iface
	{
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
			'amount' => $this->getAmount( $order->getPrice() ),
			'cardReference' => $cfg['token'],
			'paymentPage' => false,
			'language' => 'en',
		);

		if( $this->getValue( 'address', false ) ) {
			$data['card'] = $this->getCardDetails( $order, [] );
		}

		$response = $this->createGateway( 'Mpay24_Backend' )->purchase( $data )->send();

		if( $response->isSuccessful() || $response->isPending() )
		{
			$this->setOrderData( $order, ['TRANSACTIONID' => $response->getTransactionReference()] );
			$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED );
		}
		elseif( !$response->getTransactionReference() )
		{
			// response data may contain payment details, so don't log it
			$msg = 'Token based payment incomplete: ' . $response->getMessage();
			throw new \Aimeos\MShop\Service\Exception( $msg, 1 );
		}
		else
		{
			$msg = sprintf( 'Token based payment failed with code "%1$s" and message "%2$s"',
				$response->getCode(), $response->getMessage() );
			throw new \Aimeos\MShop\Service\Exception( $msg, -1 );
		}
	}
}
