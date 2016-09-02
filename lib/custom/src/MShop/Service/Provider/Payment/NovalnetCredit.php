<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Novalnet credit card payment provider
 *
 * @package MShop
 * @subpackage Service
 */
class NovalnetCredit
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $feConfig = array(
		'novalnetcredit.holder' => array(
			'code' => 'novalnetcredit.holder',
			'internalcode'=> 'ccholder',
			'label'=> 'Credit card owner',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'novalnetcredit.number' => array(
			'code' => 'novalnetcredit.number',
			'internalcode'=> 'ccnumber',
			'label'=> 'Credit card number',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'novalnetcredit.year' => array(
			'code' => 'novalnetcredit.year',
			'internalcode'=> 'ccyear',
			'label'=> 'Expiry year',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'novalnetcredit.month' => array(
			'code' => 'novalnetcredit.month',
			'internalcode'=> 'ccmonth',
			'label'=> 'Expiry month',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12 ),
			'required'=> true
		),
		'novalnetcredit.cvv' => array(
			'code' => 'novalnetcredit.cvv',
			'internalcode'=> 'cccvv',
			'label'=> 'CVC code',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
	);


	/**
	 * Initializes the service provider object.
	 *
	 * @param \Aimeos\MShop\Context\Item\Iface $context Context object with required objects
	 * @param \Aimeos\MShop\Service\Item\Iface $serviceItem Service item with configuration for the provider
	 */
	public function __construct( \Aimeos\MShop\Context\Item\Iface $context, \Aimeos\MShop\Service\Item\Iface $serviceItem )
	{
		parent::__construct( $context, $serviceItem );

		$year = date( 'Y' );
		$this->feConfig['novalnetcredit.year']['default'] = array( $year, $year+1, $year+2, $year+3, $year+4, $year+5, $year+6, $year+7 );
	}


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the frontend.
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $basket Basket object
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigFE( \Aimeos\MShop\Order\Item\Base\Iface $basket )
	{
		$list = array();
		$feconfig = $this->feConfig;

		try
		{
			$attrs = $basket->getService( \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT )->getAttributes();

			foreach( $attrs as $item )
			{
				if( isset( $feconfig[$item->getCode()] ) )
				{
					if( is_array( $feconfig[$item->getCode()]['default'] ) ) {
						$feconfig[$item->getCode()]['default'] = array_merge( array( $item->getValue() ), $feconfig[$item->getCode()]['default'] );
					} else {
						$feconfig[$item->getCode()]['default'] = $item->getValue();
					}
				}
			}
		}
		catch( \Aimeos\MShop\Order\Exception $e ) { ; } // If payment isn't available yet


		try
		{
			$address = $basket->getAddress();

			if( $feconfig['novalnetcredit.holder']['default'] == ''
				&& ( $fn = $address->getFirstname() ) !== '' && ( $ln = $address->getLastname() ) !== ''
			) {
				$feconfig['novalnetcredit.holder']['default'] = $fn . ' ' . $ln;
			}
		}
		catch( \Aimeos\MShop\Order\Exception $e ) { ; } // If address isn't available


		foreach( $feconfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}


	/**
	 * Checks the frontend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes entered by the customer during the checkout process
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 * 	known by the provider but aren't valid resp. null for attributes whose values are OK
	 */
	public function checkConfigFE( array $attributes )
	{
		return $this->checkConfig( $this->feConfig, $attributes );
	}


	/**
	 * Sets the payment attributes in the given service.
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Service\Iface $orderServiceItem Order service item that will be added to the basket
	 * @param array $attributes Attribute key/value pairs entered by the customer during the checkout process
	 */
	public function setConfigFE( \Aimeos\MShop\Order\Item\Base\Service\Iface $orderServiceItem, array $attributes )
	{
		$this->setAttributes( $orderServiceItem, $attributes, 'session' );
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Standard Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = array() )
	{
		return $this->processOrder( $order, $params );
	}


	/**
	 * Returns an Omnipay credit card object
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Order base object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return \Omnipay\Common\CreditCard Credit card object
	 */
	protected function getCardDetails( \Aimeos\MShop\Order\Item\Base\Iface $base, array $params )
	{
		if( $this->getValue( 'address' ) )
		{
			$addr = $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

			$params['billingName'] = $addr->getFirstname() . ' ' . $addr->getLastname();
			$params['billingFirstName'] = $addr->getFirstname();
			$params['billingLastName'] = $addr->getLastname();
			$params['billingCompany'] = $addr->getCompany();
			$params['billingAddress1'] = $addr->getAddress1();
			$params['billingAddress2'] = $addr->getAddress2();
			$params['billingCity'] = $addr->getCity();
			$params['billingPostcode'] = $addr->getPostal();
			$params['billingState'] = $addr->getState();
			$params['billingCountry'] = $addr->getCountryId();
			$params['billingPhone'] = $addr->getTelephone();
			$params['billingFax'] = $addr->getTelefax();
			$params['email'] = $addr->getEmail();
		}

		$params['holder'] = ( isset( $params['novalnetcredit.holder'] ) ? $params['novalnetcredit.holder'] : '' );
		$params['number'] = ( isset( $params['novalnetcredit.number'] ) ? $params['novalnetcredit.number'] : '' );
		$params['expiryYear'] = ( isset( $params['novalnetcredit.year'] ) ? $params['novalnetcredit.year'] : '' );
		$params['expiryMonth'] = ( isset( $params['novalnetcredit.month'] ) ? $params['novalnetcredit.month'] : '' );
		$params['cvv'] = ( isset( $params['novalnetcredit.cvv'] ) ? $params['novalnetcredit.cvv'] : '' );

		return new \Omnipay\Common\CreditCard( $params );
	}
}
