<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2018
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


use Omnipay\Omnipay as OPay;


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
	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return void
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order )
	{
		$base = $this->getOrderBase( $order->getBaseId() );

		if( ( $token = $this->getCustomerData( $base->getCustomerId(), 'token' ) ) != null )
		{
			$msg = sprintf( 'No reoccurring payment token available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$data = array(
			'transactionId' => $order->getId(),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'cardReference' => $token,
			'paymentPage' => false,
		);

		$provider = Opay::create('Datatrans\Xml');
		$response = $provider->purchase( $data )->send();

		if( $response->isSuccessful() )
		{
			$this->saveTransationRef( $base, $response->getTransactionReference() );
			$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED );
			$this->saveOrder( $order );
		}
		else
		{
			$msg = ( method_exists( $response, 'getMessage' ) ? $response->getMessage() : '' );
			throw new \Aimeos\MShop\Service\Exception( sprintf( 'Token based payment failed: %1$s', $msg ) );
		}
	}
}
