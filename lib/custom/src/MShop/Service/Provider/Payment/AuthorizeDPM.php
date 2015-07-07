<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 * @package MShop
 * @subpackage Service
 */


/**
 * Payment provider for Authorize.NET DPM.
 *
 * @package MShop
 * @subpackage Service
 */
class MShop_Service_Provider_Payment_AuthorizeDPM
	extends MShop_Service_Provider_Payment_OmniPay
	implements MShop_Service_Provider_Payment_Interface
{
	private $_feConfig = array(
		'omnipay.firstname' => array(
			'code' => 'omnipay.firstname',
			'internalcode'=> 'x_first_name',
			'label'=> 'First name',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false
		),
		'omnipay.lastname' => array(
			'code' => 'omnipay.lastname',
			'internalcode'=> 'x_last_name',
			'label'=> 'Last name',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'omnipay.cardno' => array(
			'code' => 'omnipay.cardno',
			'internalcode'=> 'x_card_num',
			'label'=> 'Credit card number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'omnipay.cvv' => array(
			'code' => 'omnipay.cvv',
			'internalcode'=> 'x_card_code',
			'label'=> 'Verification number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'omnipay.expirymonthyear' => array(
			'code' => 'omnipay.expirymonthyear',
			'internalcode'=> 'x_exp_date',
			'label'=> 'Expiry date',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'billing.company' => array(
			'code' => 'billing.company',
			'internalcode'=> 'x_company',
			'label'=> 'Company',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.address' => array(
			'code' => 'billing.address',
			'internalcode'=> 'x_address',
			'label'=> 'Street',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.city' => array(
			'code' => 'billing.city',
			'internalcode'=> 'x_city',
			'label'=> 'City',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.postal' => array(
			'code' => 'billing.postal',
			'internalcode'=> 'x_zip',
			'label'=> 'Zip code',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.countryid' => array(
			'code' => 'billing.countryid',
			'internalcode'=> 'x_country',
			'label'=> 'Country',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.telephone' => array(
			'code' => 'billing.telephone',
			'internalcode'=> 'x_phone',
			'label'=> 'Telephone',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
		'billing.email' => array(
			'code' => 'billing.email',
			'internalcode'=> 'x_email',
			'label'=> 'E-Mail',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false,
			'public' => false,
		),
	);


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
		$form = $this->_processOffsite( $order );
		$baseItem = $this->_getOrderBase( $order->getBaseId(), MShop_Order_Manager_Base_Abstract::PARTS_ADDRESS );

		try
		{
			$address = $baseItem->getAddress();

			$this->_feConfig['omnipay.firstname']['default'] = $address->getFirstname();
			$this->_feConfig['omnipay.lastname']['default'] = $address->getLastname();

			if( $this->_getValue( 'address' ) )
			{
				$this->_feConfig['billing.address']['default'] = $address->getAddress1() . ' ' . $address->getAddress2();
				$this->_feConfig['billing.city']['default'] = $address->getCity();
				$this->_feConfig['billing.postal']['default'] = $address->getPostal();
				$this->_feConfig['billing.state']['default'] = $address->getState();
				$this->_feConfig['billing.country']['default'] = $address->getCountryId();
				$this->_feConfig['billing.telephone']['default'] = $address->getTelephone();
				$this->_feConfig['billing.company']['default'] = $address->getCompany();
				$this->_feConfig['billing.email']['default'] = $address->getEmail();
			}
		}
		catch( MShop_Order_Exception $e ) { ; } // If address isn't available

		foreach( $this->_feConfig as $key => $values ) {
			$form->setValue( $key, new MW_Common_Criteria_Attribute_Default( $values ) );
		}

		return $form;
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

		return $this->_updateSyncOffsite( $params, $body, $response );
	}


	/**
	 * Returns the Omnipay gateway provider name.
	 *
	 * @return string Gateway provider name
	 */
	protected function _getProviderType()
	{
		return 'AuthorizeNet_DPM';
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
		return $this->_getConfigValue( array( 'authorizenet.' . $key ), $default );
	}
}