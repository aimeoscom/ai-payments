<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Service
 */


/**
 * Payment provider for CardSave.
 *
 * @package MShop
 * @subpackage Service
 */
class MShop_Service_Provider_Payment_CardSave
	extends MShop_Service_Provider_Payment_OmniPay
	implements MShop_Service_Provider_Payment_Interface
{
	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param MShop_Order_Item_Interface $order Order invoice object
	 * @return MShop_Common_Item_Helper_Form_Default Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( MShop_Order_Item_Interface $order )
	{
		return $this->_processOnsite( $order );
	}


	/**
	 * Updates the orders for which status updates were received via direct requests (like HTTP).
	 *
	 * @param array $params Associative list of request parameters
	 * @param string|null $body Information sent within the body of the request
	 * @param string|null &$response Response body for notification requests
	 * @return MShop_Order_Item_Interface|null Order item if update was successful, null if the given parameters are not valid for this provider
	 */
	public function updateSync( array $params = array(), $body = null, &$response = null )
	{
		if( !isset( $params['orderid'] ) ) {
			return null;
		}

		return $this->_updateSyncOnsite( $params, $body, $response );
	}


	/**
	 * Returns the Omnipay gateway provider name.
	 *
	 * @return string Gateway provider name
	 */
	protected function _getProviderType()
	{
		return 'Authorize_AIM';
	}
}