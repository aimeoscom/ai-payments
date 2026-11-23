<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2023
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
	private array $feConfig = array(
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
	 * @param \Aimeos\MShop\ContextIface $context Context object with required objects
	 * @param \Aimeos\MShop\Service\Item\Iface $serviceItem Service item with configuration for the provider
	 */
	public function __construct( \Aimeos\MShop\ContextIface $context, \Aimeos\MShop\Service\Item\Iface $serviceItem )
	{
		parent::__construct( $context, $serviceItem );

		$year = date( 'Y' );
		$this->feConfig['novalnetcredit.year']['default'] = array( $year, $year + 1, $year + 2, $year + 3, $year + 4, $year + 5, $year + 6, $year + 7 );
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface|null Form object with URL, action and parameters to redirect to
	 * 	(e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
	{
		// card details are only passed in the request and never stored in the basket
		if( $this->fieldValue( $params, 'novalnetcredit.number' ) === '' || $this->fieldValue( $params, 'novalnetcredit.cvv' ) === '' ) {
			return $this->getPaymentForm( $order, $params );
		}

		return $this->processOrder( $order, $params );
	}


	/**
	 * Returns an Omnipay credit card object
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return \Omnipay\Common\CreditCard Credit card object
	 */
	protected function getCardDetails( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Omnipay\Common\CreditCard
	{
		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( $this->getValue( 'address' ) && ( $addr = current( $addresses ) ) !== false )
		{
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

		$params['holder'] = $this->fieldValue( $params, 'novalnetcredit.holder' );
		$params['number'] = $this->fieldValue( $params, 'novalnetcredit.number' );
		$params['expiryYear'] = $this->fieldValue( $params, 'novalnetcredit.year' );
		$params['expiryMonth'] = $this->fieldValue( $params, 'novalnetcredit.month' );
		$params['cvv'] = $this->fieldValue( $params, 'novalnetcredit.cvv' );

		return new \Omnipay\Common\CreditCard( $params );
	}


	/**
	 * Returns the payment form for entering the credit card details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];
		$feconfig = $this->feConfig;
		$feconfig['novalnetcredit.holder']['default'] = $this->fieldValue( $params, 'novalnetcredit.holder' );

		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) !== false )
		{
			if( $feconfig['novalnetcredit.holder']['default'] == ''
				&& ( $fn = $address->getFirstname() ) !== '' && ( $ln = $address->getLastname() ) !== ''
			) {
				$feconfig['novalnetcredit.holder']['default'] = $fn . ' ' . $ln;
			}
		}

		foreach( $feconfig as $key => $config ) {
			$list[$key] = new \Aimeos\Base\Criteria\Attribute\Standard( $config );
		}

		$url = $this->getConfigValue( 'payment.url-self', '' );
		return new \Aimeos\MShop\Common\Helper\Form\Standard( $url, 'POST', $list, false );
	}


	/**
	 * Returns the value of the payment form field passed in the request
	 *
	 * @param array $params Request parameter
	 * @param string $key Code of the payment form field
	 * @return string Value of the field or empty string if not available
	 */
	protected function fieldValue( array $params, string $key ) : string
	{
		return (string) ( $params[$this->feConfig[$key]['internalcode']] ?? $params[$key] ?? '' );
	}
}
