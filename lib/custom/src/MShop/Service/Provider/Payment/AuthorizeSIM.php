<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Service
 */


/**
 * Payment provider for Authorize.NET SIM.
 *
 * @package MShop
 * @subpackage Service
 */
class MShop_Service_Provider_Payment_AuthorizeSIM
	extends MShop_Service_Provider_Payment_OmniPay
	implements MShop_Service_Provider_Payment_Interface
{
	/**
	 * Updates the orders for which status updates were received via direct requests (like HTTP).
	 *
	 * @param array $params Associative list of request parameters
	 * @param string|null $body Information sent within the body of the request
	 * @param string|null &$output Response body for notification requests
	 * @param array &$header Response headers for notification requests
	 * @return MShop_Order_Item_Interface|null Order item if update was successful, null if the given parameters are not valid for this provider
	 */
	public function updateSync( array $params = array(), $body = null, &$output = null, array &$header = array() )
	{
		if( isset( $params['x_MD5_Hash'] ) )
		{
			$result = parent::updateSync( $params, $body, $output, $header );

			if( $result !== null )
			{
				$url = $this->_getConfigValue( array( 'payment.url-success' ) );

				$header[] = $this->_getValue( 'header', 'Location: ' . $url );
				$output = sprintf( $this->_getValue( 'body', 'success' ), $url );
			}

			return $result;
		}

		if( isset( $params['orderid'] ) ) {
			return $this->_getOrder( $params['orderid'] );
		}
	}


	/**
	 * Returns the Omnipay gateway provider name.
	 *
	 * @return string Gateway provider name
	 */
	protected function _getProviderType()
	{
		return 'AuthorizeNet_SIM';
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return mixed Configuration value
	 */
	protected function _getValue( $key, $default = null )
	{
		return $this->_getConfigValue( array( 'authorizenet.' . $key ), $default );
	}
}
