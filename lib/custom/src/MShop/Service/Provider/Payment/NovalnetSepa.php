<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Novalnet SEPA payment provider
 *
 * @package MShop
 * @subpackage Service
 */
class NovalnetSepa
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $feConfig = array(
		'novalnetsepa.holder' => array(
			'code' => 'novalnetsepa.holder',
			'internalcode'=> 'holder',
			'label'=> 'Bank account holder',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'novalnetsepa.iban' => array(
			'code' => 'novalnetsepa.iban',
			'internalcode'=> 'iban',
			'label'=> 'IBAN number',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
		'novalnetsepa.bic' => array(
			'code' => 'novalnetsepa.bic',
			'internalcode'=> 'bic',
			'label'=> 'BIC code',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true
		),
	);


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
				if( isset( $feconfig[$item->getCode()] ) ) {
					$feconfig[$item->getCode()]['default'] = $item->getValue();
				}
			}
		}
		catch( \Aimeos\MShop\Order\Exception $e ) { ; } // If payment isn't available yet


		try
		{
			$address = $basket->getAddress();

			if( $feconfig['novalnetsepa.holder']['default'] == ''
				&& ( $fn = $address->getFirstname() ) !== '' && ( $ln = $address->getLastname() ) !== ''
			) {
				$feconfig['novalnetsepa.holder']['default'] = $fn . ' ' . $ln;
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
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Basket object
	 * @param $orderid Unique order ID
	 * @param array $params Request parameter if available
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, $orderid, array $params )
	{
		$urls = $this->getPaymentUrls();
		$card = $this->getCardDetails( $base, $params );
		$desc = $this->getContext()->getI18n()->dt( 'mshop', 'Order %1$s' );

		$data = array(
			'token' => '',
			'card' => $card,
			'transactionId' => $orderid,
			'description' => sprintf( $desc, $orderid ),
			'amount' => $this->getAmount( $base->getPrice() ),
			'currency' => $base->getLocale()->getCurrencyId(),
			'language' => $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT )->getLanguageId(),
			'clientIp' => $this->getValue( 'client.ipaddress' ),
			'bic' => ( isset( $params['novalnetsepa.bic'] ) ? $params['novalnetsepa.bic'] : '' ),
			'iban' => ( isset( $params['novalnetsepa.iban'] ) ? $params['novalnetsepa.iban'] : '' ),
			'bankaccount_holder' => ( isset( $params['novalnetsepa.holder'] ) ? $params['novalnetsepa.holder'] : '' ),
		) + $urls;

		return $data;
	}
}
