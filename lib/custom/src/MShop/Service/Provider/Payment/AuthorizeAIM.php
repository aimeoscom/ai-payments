<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2016
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payment provider for Authorize.NET AIM.
 *
 * @package MShop
 * @subpackage Service
 */
class AuthorizeAIM
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
{
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
		switch( $key )
		{
			case 'type': return 'AuthorizeNet_AIM';
			case 'onsite': return true;
		}

		return parent::getValue( $key, $default );
	}
}
