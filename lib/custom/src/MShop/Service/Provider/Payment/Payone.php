<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2017-2022
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
	 * @param string $orderid Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, string $orderid, array $params ) : array
	{
		$lines = [];

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

		foreach( $base->getService( 'delivery' ) as $delivery )
		{
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
			}
		}

		$data = parent::getData( $base, $orderid, $params );
		$data['items'] = new \Omnipay\Common\ItemBag( $lines );
		$data['accessMethod'] = 'classic';

		return $data;
	}


	/**
	 * Updates the order status sent by payment gateway notifications
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface Request object
	 * @return \Psr\Http\Message\ResponseInterface Response object
	 */
	public function updatePush( \Psr\Http\Message\ServerRequestInterface $request,
		\Psr\Http\Message\ResponseInterface $response ) : \Psr\Http\Message\ResponseInterface
	{
		$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

		if( isset( $params['reference'] ) )
		{
			$response = parent::updatePush( $request->withAttribute( 'orderid', $params['reference'] ), $response );

			if( $response->getStatusCode() === 200 ) {
				$response = $response->withBody( $response->createStreamFromString( 'TSOK' ) ); // payment update successful
			}
		}

		return $response;
	}
}
