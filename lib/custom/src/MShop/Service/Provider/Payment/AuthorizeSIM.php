<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2016
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
	/**
	 * Updates the orders for which status updates were received via direct requests (like HTTP).
	 *
	 * @param array $params Associative list of request parameters
	 * @param string|null $body Information sent within the body of the request
	 * @param string|null &$output Response body for notification requests
	 * @param array &$header Response headers for notification requests
	 * @return \Aimeos\MShop\Order\Item\Iface|null Order item if update was successful, null if the given parameters are not valid for this provider
	 */
	public function updateSync( array $params = array(), $body = null, &$output = null, array &$header = array() )
	{
		if( isset( $params['x_MD5_Hash'] ) )
		{
			$result = parent::updateSync( $params, $body, $output, $header );

			if( $result !== null )
			{
				$url = $this->getConfigValue( array( 'payment.url-success' ) );

				$header[] = $this->getValue( 'header', 'Location: ' . $url );
				$output = sprintf( $this->getValue( 'body', 'success' ), $url );
			}

			return $result;
		}

		if( isset( $params['orderid'] ) ) {
			return $this->getOrder( $params['orderid'] );
		}
	}


	/**
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function getConfigPrefix()
	{
		return 'authorizenet';
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return mixed Configuration value
	 */
	protected function getValue( $key, $default = null )
	{
		switch( $key ) {
			case 'type': return 'AuthorizeNet_SIM';
		}

		return parent::getValue( $key, $default );
	}
}
