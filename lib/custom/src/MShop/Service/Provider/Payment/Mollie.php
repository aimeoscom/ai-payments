<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payment provider for Mollie.
 *
 * @package MShop
 * @subpackage Service
 */
class Mollie
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	/**
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function getConfigPrefix()
	{
		return 'mollie';
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
		switch( $key ) {
			case 'type': return 'Mollie';
		}

		return parent::getValue( $key, $default );
	}
	
	/**
	 * Returns Omnipay items array
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Order base object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return Array of items
	 */
	protected function getItems( \Aimeos\MShop\Order\Item\Base\Iface $base, array $params )
	{
		$items = array();
		foreach( $base->getProducts() as $product ) {
			$price = $product->getPrice();
			$items[] = array(
				'sku' => $product->getProductCode(),
				'name' => $product->getName(),
				'quantity' => $product->getQuantity(),
				'vatRate' => $price->getTaxRate(),
				'unitPrice' => round( $price->getValue(), 2),
				'totalAmount' => round( $price->getValue() * $product->getQuantity(), 2),
				'vatAmount' => round( $price->getTaxValue() * $product->getQuantity(), 2),
			);
		}
		return $items;
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
		$data = array(
			'orderNumber' => $orderid
		);

		return $data + parent::getData( $base, $orderid, $params );
	}
	
	/**
	 * Updates the orders for whose status updates have been received by the confirmation page
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request Request object with parameters and request body
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item that should be updated
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 * @throws \Aimeos\MShop\Service\Exception If updating the orders failed
	 */
	public function updateSync( \Psr\Http\Message\ServerRequestInterface $request, \Aimeos\MShop\Order\Item\Iface $order )
	{
		try
		{
			$provider = $this->getProvider();
			$base = $this->getOrderBase( $order->getBaseId() );

			$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();
			$params['transactionId'] = $order->getId();
			$params['transactionReference'] = $this->getTransactionReference( $base );
			$params['amount'] = $this->getAmount( $base->getPrice() );
			$params['currency'] = $base->getLocale()->getCurrencyId();
			$params['createCard'] = true;

			if( $this->getValue( 'authorize', false ) && $provider->supportsCompleteAuthorize() )
			{
				$response = $provider->completeAuthorize( $params )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
			}
			elseif( $this->getValue('apiType', 'payment') == 'order' )
			{
				$response = $provider->completeOrder( $params )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
			}
			elseif( $provider->supportsCompletePurchase() )
			{
				$response = $provider->completePurchase( $params )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
			}
			else
			{
				return $order;
			}

			if( method_exists( $response, 'isSuccessful' ) && $response->isSuccessful() )
			{
				$order->setPaymentStatus( $status );
			}
			elseif( method_exists( $response, 'isAuthorized' ) && $response->isAuthorized() )
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED );
			}
			elseif( method_exists( $response, 'isPending' ) && $response->isPending() )
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_PENDING );
			}
			elseif( method_exists( $response, 'isOpen' ) && $response->isOpen() )
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED );
			}
			elseif( method_exists( $response, 'isCancelled' ) && $response->isCancelled() )
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_CANCELED );
			}
			elseif( method_exists( $response, 'isRedirect' ) && $response->isRedirect() )
			{
				$url = $response->getRedirectUrl();
				throw new \Aimeos\MShop\Service\Exception( sprintf( 'Unexpected redirect: %1$s', $url ) );
			}
			else
			{
				$this->saveOrder( $order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED ) );
				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}

			$this->saveTransationRef( $base, $response->getTransactionReference() );
			$this->saveRepayData( $response, $base->getCustomerId() );
			$this->saveOrder( $order );
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return $order;
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
	protected function processOrder( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] )
	{
		error_log('## AIPAY:MOLLIE customProcessOrder ##');
		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS;

		$base = $this->getOrderBase( $order->getBaseId(), $parts );
		$data = $this->getData( $base, $order->getId(), $params );
		$urls = $this->getPaymentUrls();

		try
		{
			$provider = $this->getProvider();

			if( $this->getValue( 'authorize', false ) && $provider->supportsAuthorize() )
			{
				$response = $provider->authorize( $data )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
			}
			elseif( $this->getValue( 'apiType', '' ) === 'order' )
			{
				$response = $provider->createOrder( $data )->setItems( $this->getItems( $base, $params ) )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
			}
			else
			{
				$response = $provider->purchase( $data )->send();
				$status = \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
			}

			if( $response->isSuccessful() )
			{
				$this->saveTransationRef( $base, $response->getTransactionReference() );

				$order->setPaymentStatus( $status );
				$this->saveOrder( $order );
			}
			elseif( $response->isRedirect() )
			{
				if( ( $ref = $response->getTransactionReference() ) != null ) {
					$this->saveTransationRef( $base, $ref );
				}

				return $this->getRedirectForm( $response );
			}
			else
			{
				$order->setPaymentStatus( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED );
				$this->saveOrder( $order );

				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		}

		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $urls['returnUrl'], 'POST', [] );
	}
	
	/**
	 * Returns an Omnipay credit card object, except for phone numbers as Mollie requests a strict number
	 * format which we can not provice in all cases.
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Order base object with addresses and services
	 * @param array $params POST parameters passed to the provider
	 * @return \Omnipay\Common\CreditCard Credit card object
	 */
	protected function getCardDetails( \Aimeos\MShop\Order\Item\Base\Iface $base, array $params )
	{
		if( $this->getValue( 'address' ) )
		{
			$addresses = $base->getAddresses();

			if( isset( $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT ] ) )
			{
				$addr = $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT];

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
				$params['email'] = $addr->getEmail();

				if( isset( $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_DELIVERY ] ) ) {
					$addr = $addresses[\Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_DELIVERY];
				}

				$params['shippingName'] = $addr->getFirstname() . ' ' . $addr->getLastname();
				$params['shippingFirstName'] = $addr->getFirstname();
				$params['shippingLastName'] = $addr->getLastname();
				$params['shippingCompany'] = $addr->getCompany();
				$params['shippingAddress1'] = $addr->getAddress1();
				$params['shippingAddress2'] = $addr->getAddress2();
				$params['shippingCity'] = $addr->getCity();
				$params['shippingPostcode'] = $addr->getPostal();
				$params['shippingState'] = $addr->getState();
				$params['shippingCountry'] = $addr->getCountryId();
			}
		}

		return new \Omnipay\Common\CreditCard( $params );
	}
}
