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
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function _getConfigPrefix()
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
	protected function _getValue( $key, $default = null )
	{
		switch( $key )
		{
			case 'type': return 'AuthorizeNet_AIM';
			case 'onsite': return true;
		}

		return parent::_getValue( $key, $default );
	}
}
