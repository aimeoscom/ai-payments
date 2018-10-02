<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2017-2018
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
		$lines = [];
		$delivery = $base->getService('delivery');
		$completePrice = $base->getPrice()->getValue();

		foreach( $base->getProducts() as $product )
		{
			$list = $product->toArray();

			$lines[] = new \Omnipay\Payone\Extend\Item([
				'id' => $list['order.base.product.prodcode'],
				'name' => $product->getName(),
				'itemType' => 'goods', // Available types: goods, shipping etc.
				'quantity' => $product->getQuantity(),
				'price' => $product->getPrice()->getValue(),
				'vat' => (int) $product->getPrice()->getTaxRate(),
			]);
		}

		if( $delivery->getPrice()->getCosts() != '0.00' )
		{
			$lines[] = new \Omnipay\Payone\Extend\Item([
				'id' => $delivery->getId(),
				'name' => $delivery->getName(),
				'itemType' => 'shipment',
				'quantity' => 1,
				'price' => $delivery->getPrice()->getCosts(),
				'vat' => (int) $delivery->getPrice()->getTaxRate(),
			]);

			$completePrice = (string) ( (float) $delivery->getPrice()->getCosts() + (float) $completePrice );
		}

		return array_merge(
			parent::getData( $base, $orderid, $params ),
			array(
				'amount' => $completePrice,
				'accessMethod' => 'classic',
				'items' => new \Omnipay\Common\ItemBag( $lines ),
			)
		);
	}


	/**
	 * Updates the order status sent by payment gateway notifications
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface Request object
	 * @return \Psr\Http\Message\ResponseInterface Response object
	 */
	public function updatePush( \Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response )
	{
		$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

		if( isset( $params['reference'] ) )
		{
			$response = parent::updatePush( $request, $response );
			$response = $response->withBody( $response->createStreamFromString( 'TSOK' ) ); // payment update successful
		}

		return $response;
	}
}
