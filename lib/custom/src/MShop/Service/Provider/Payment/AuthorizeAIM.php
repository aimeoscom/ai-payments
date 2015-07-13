<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Service
 */


/**
 * Payment provider for Authorize.NET AIM.
 *
 * @package MShop
 * @subpackage Service
 */
class MShop_Service_Provider_Payment_AuthorizeAIM
	extends MShop_Service_Provider_Payment_OmniPay
{
	/**
	 * Returns the Omnipay gateway provider name.
	 *
	 * @return string Gateway provider name
	 */
	protected function _getProviderType()
	{
		return 'AuthorizeNet_AIM';
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return string Configuration value
	 */
	protected function _getValue( $key, $default = null )
	{
		if( $key === 'onsite' ) {
			return true;
		}

		return $this->_getConfigValue( array( 'authorizenet.' . $key ), $default );
	}
}
