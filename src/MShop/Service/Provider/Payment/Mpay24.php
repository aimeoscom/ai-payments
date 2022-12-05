<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2020-2022
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

		$provider = \Omnipay\Omnipay::create( 'Mpay24_Backend' );
		$provider->setTestMode( (bool) $this->getValue( 'testmode', false ) );
		$provider->initialize( $this->getServiceItem()->getConfig() );
		$response = $provider->purchase( $data )->send();

		if( $response->isSuccessful() || $response->isPending() )
		{
			$this->setOrderData( $order, ['TRANSACTIONID' => $response->getTransactionReference()] );
			$order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED );
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
	}
}
