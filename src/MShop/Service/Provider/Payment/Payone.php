<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2017-2023
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
	 * @param \Aimeos\MShop\Order\Item\Iface $order Basket object
	 * @param string $orderid Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Iface $order, string $orderid, array $params ) : array
	{
		$lines = [];

		foreach( $order->getProducts() as $product )
		{
			$list = $product->toArray();

			$lines[] = new \Omnipay\Payone\Extend\Item([
				'id' => $list['order.product.prodcode'],
				'name' => $product->getName(),
				'itemType' => 'goods', // Available types: goods, shipping etc.
				'quantity' => $product->getQuantity(),
				'price' => $product->getPrice()->getValue(),
				'vat' => (int) $product->getPrice()->getTaxRate(),
			]);
		}

		foreach( $order->getService( 'delivery' ) as $delivery )
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

		$data = parent::getData( $order, $orderid, $params );
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


	/**
	 * Returns the notification sent by the payment gateway
	 *
	 * @param \Omnipay\Common\GatewayInterface $provider Gateway provider object
	 * @return \Omnipay\Common\Message\NotificationInterface Notification object
	 * @throws \Aimeos\MShop\Service\Exception If the notification doesn't contain the hashed portal key
	 */
	protected function notification( \Omnipay\Common\GatewayInterface $provider ) : \Omnipay\Common\Message\NotificationInterface
	{
		$notification = parent::notification( $provider );

		$key = (string) $this->getValue( 'portalKey', '' );
		$hash = (string) $notification->getKey();

		if( $key === '' || !hash_equals( md5( $key ), $hash ) && !hash_equals( hash( 'sha384', $key ), $hash ) ) {
			throw new \Aimeos\MShop\Service\Exception( 'Invalid payment notification' );
		}

		return $notification;
	}


	/**
	 * Checks if the response of the payment gateway can be trusted
	 *
	 * The client API passes the payment result as unsigned parameters in the return URL,
	 * so the final status is set by the signed transaction status notification only.
	 *
	 * @param \Omnipay\Common\Message\ResponseInterface $response Response of the payment gateway
	 * @return bool TRUE if the response has been verified by the gateway, FALSE if not
	 */
	protected function verified( \Omnipay\Common\Message\ResponseInterface $response ) : bool
	{
		return !( $response instanceof \Omnipay\Payone\Message\ShopClientCompleteAuthorizeResponse );
	}
}
