<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2017
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payone payment provider
 *
 * @package MShop
 * @subpackage Service
 */
class Payone
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
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

		// transfer information about product items, too
		$lines = [];
		$i = 0;

		foreach( $base->getProducts() as $product )  {
			$lines[$i] = new \Omnipay\Payone\Extend\Item([
				'id' => (string) $product->toArray()['order.base.product.prodcode'],
				'name' => $product->getName(),
				'itemType' => 'goods', // Available types: goods, shipping etc.
				'quantity' => $product->getQuantity(),
				'price' => $product->getPrice()->getValue(),
				'vat' => (int) $product->getPrice()->getTaxRate(), // Optional
			]);
			$i++;

		}

		// if delivery costs are greater than zero, transfer the delivery service item, too
		if ($base->getService('delivery')->getPrice()->getCosts() != '0.00') {
			$deliveryObject = $base->getService('delivery');
			$lines[$i] = new \Omnipay\Payone\Extend\Item([
				//'id' => (string) $deliveryObject->getId(),
				'id' => '-',
				// $base->getService('delivery')->getName()
				'name' => 'Standardversand',
				'itemType' => 'shipment',
				'quantity' => 1,
				'price' => $deliveryObject->getPrice()->getCosts(),
				'vat' => (int) $deliveryObject->getPrice()->getTaxRate(), // Optional
			]);
		}

		// calculate the complete price
		if ($base->getService('delivery')->getPrice()->getCosts() != '0.00') {
			$completePrice = (string) ( (float) $deliveryObject->getPrice()->getCosts() + (float) $base->getPrice()->getValue() );
		} else {
			$completePrice = $base->getPrice()->getValue();
		}

		$items = new \Omnipay\Common\ItemBag($lines);

		$data = array(
				'token' => '',
				'card' => $card,
				'transactionId' => $orderid,
				'description' => sprintf( $desc, $orderid ),
				'amount' => $completePrice,
				'accessMethod' => 'classic',
				'items' => $items,
				'currency' => $base->getLocale()->getCurrencyId(),
				'language' => $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT )->getLanguageId(),
				'clientIp' => $this->getValue( 'client.ipaddress' ),
			) + $urls;

		return $data;
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
	protected function processOrder( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] )
	{
		$parts = \Aimeos\MShop\Order\Manager\Base\Base::PARTS_SERVICE | \Aimeos\MShop\Order\Manager\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Manager\Base\Base::PARTS_PRODUCT;
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

		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $urls['returnUrl'], 'POST', [] );
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
	public function updateSync( array $params = [], $body = null, &$output = null, array &$header = [] )
	{
		if( isset( $params['reference'] ) )
		{
			$result = $this->updateSyncOrder( $params['reference'], $params, $body, $output, $header );
			$output = 'TSOK'; // payment update successful

			return $result;
		}
	}


	/**
	 * Returns the order item for the given ID without checking the service code
	 *
	 * @param string $id Unique order ID
	 * @return \Aimeos\MShop\Order\Item\Iface $item Order object
	 */
	protected function getOrder( $id )
	{
		return \Aimeos\MShop\Factory::createManager( $this->getContext(), 'order' )->getItem( $id );
	}
}
