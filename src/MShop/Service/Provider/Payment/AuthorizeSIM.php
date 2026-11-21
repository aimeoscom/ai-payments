<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2024
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payment provider for Authorize.NET SIM.
 *
 * @package MShop
 * @subpackage Service
 */
class AuthorizeSIM
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private array $beConfig = array(
		'payment.url-success' => array(
			'code' => 'payment.url-success',
			'internalcode'=> 'payment.url-success',
			'label'=> 'URL to confirm page',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
	);


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\Base\Critera\Attribute\Iface
	 */
	public function getConfigBE() : array
	{
		$list = parent::getConfigBE();

		foreach( $this->beConfig as $key => $config )
		{
			$config['code'] = $config['code'];
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
	 * Updates the order status sent by payment gateway notifications
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface Request object
	 * @return \Psr\Http\Message\ResponseInterface Response object
	 */
	public function updatePush( \Psr\Http\Message\ServerRequestInterface $request,
		\Psr\Http\Message\ResponseInterface $response ) : \Psr\Http\Message\ResponseInterface
	{
		$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

		if( isset( $params['x_SHA2_Hash'] ) || isset( $params['x_MD5_Hash'] ) )
		{
			$url = $this->getConfigValue( array( 'payment.url-success' ) );
			$output = sprintf( $this->getValue( 'body', 'success' ), $url );

			$response = parent::updatePush( $request, $response );
			$response = $response->withBody( $response->createStreamFromString( $output ) );
			$response = $response->withHeader( 'Location', $url );
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
		// the MD5 hash doesn't cover the response code and the order, only the SHA-512 signature does
		if( empty( $this->getValue( 'signatureKey' ) ) )
		{
			$msg = sprintf( 'Payment response for order "%1$s" can\'t be verified without "signatureKey"', $order->getId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

		// the signed invoice number binds the response to the order, the transaction ID isn't signed
		if( (string) ( $params['x_invoice_num'] ?? '' ) !== (string) $order->getId() )
		{
			$msg = sprintf( 'Payment response doesn\'t belong to order "%1$s"', $order->getId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		return parent::updateSync( $request, $order );
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
		// returned as signed x_invoice_num to verify the order in the response
		return ['invoiceNumber' => $orderid] + parent::getData( $order, $orderid, $params );
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
			case 'type': return 'AuthorizeNet_SIM';
		}

		return parent::getValue( $key, $default );
	}
}
