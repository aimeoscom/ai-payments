<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2024
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
	private array $feConfig = array(
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
		// bank account details are only passed in the request and never stored in the basket
		if( $this->fieldValue( $params, 'novalnetsepa.iban' ) === '' ) {
			return $this->getPaymentForm( $order, $params );
		}

		return $this->processOrder( $order, $params );
	}


	/**
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Basket object
	 * @param string $orderid Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Iface $order, string $orderid, array $params ) : array
	{
		$urls = $this->getPaymentUrls();
		$card = $this->getCardDetails( $order, $params );
		$desc = $this->context()->translate( 'mshop', 'Order %1$s' );
		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) !== false ) {
			$langid = $address->getLanguageId();
		} else {
			$langid = $this->context()->locale()->getLanguageId();
		}

		$data = array(
			'token' => '',
			'card' => $card,
			'language' => $langid,
			'transactionId' => $orderid,
			'description' => sprintf( $desc, $orderid ),
			'amount' => $this->getAmount( $order->getPrice() ),
			'currency' => $order->locale()->getCurrencyId(),
			'clientIp' => $this->getValue( 'client.ipaddress' ),
			'bic' => $this->fieldValue( $params, 'novalnetsepa.bic' ),
			'iban' => $this->fieldValue( $params, 'novalnetsepa.iban' ),
			'bankaccount_holder' => $this->fieldValue( $params, 'novalnetsepa.holder' ),
		) + $urls;

		return $data;
	}


	/**
	 * Returns the payment form for entering the bank account details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];
		$feconfig = $this->feConfig;
		$feconfig['novalnetsepa.holder']['default'] = $this->fieldValue( $params, 'novalnetsepa.holder' );
		$feconfig['novalnetsepa.bic']['default'] = $this->fieldValue( $params, 'novalnetsepa.bic' );

		$addresses = $order->getAddress( \Aimeos\MShop\Order\Item\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) !== false )
		{
			if( $feconfig['novalnetsepa.holder']['default'] == ''
				&& ( $fn = $address->getFirstname() ) !== '' && ( $ln = $address->getLastname() ) !== ''
			) {
				$feconfig['novalnetsepa.holder']['default'] = $fn . ' ' . $ln;
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
