<?php

/**
 * @license	LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright  Aimeos (aimeos.org), 2015-2021
 * @package	MShop
 * @subpackage Service
 */


 namespace Aimeos\MShop\Service\Provider\Payment;

use Omnipay\Omnipay as OPay;
use Aimeos\MShop\Order\Item\Base as Status;


/**
 * Payment provider for payment gateways supported by the PaypalPlus library.
 *
 * @package	MShop
 * @subpackage Service
 */
class PaypalPlus
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $beConfig = array(
		'clientid' => array(
			'code' => 'clientid',
			'internalcode' => 'clientid',
			'label' => 'Client ID for PayPal REST API',
			'type' => 'string',
			'internaltype' => 'string',
			'default' => '0',
			'required' => true,
		),
		'secret' => array(
			'code' => 'secret',
			'internalcode' => 'secret',
			'label' => 'Secret for PayPal REST API',
			'type' => 'string',
			'internaltype' => 'string',
			'default' => '0',
			'required' => true,
		),
		'address' => array(
			'code' => 'address',
			'internalcode' => 'address',
			'label' => 'Send address to payment gateway too',
			'type' => 'boolean',
			'internaltype' => 'boolean',
			'default' => '0',
			'required' => false,
		),
		'testmode' => array(
			'code' => 'testmode',
			'internalcode' => 'testmode',
			'label' => 'Test mode without payments',
			'type' => 'boolean',
			'internaltype' => 'boolean',
			'default' => '0',
			'required' => true,
		),
	);

	private $provider;


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigBE() : array
	{
		$list = [];

		foreach( $this->beConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}


	/**
	 * Returns the Omnipay gateway provider object.
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function getProvider() : \Omnipay\Common\GatewayInterface
	{
		if( !isset( $this->provider ) )
		{
			$this->provider = OPay::create( 'PayPal_Rest' );
			$this->provider->setTestMode( (bool) $this->getValue( 'testmode', false  ) );
			$this->provider->initialize( $this->getServiceItem()->getConfig() );
		}

		return $this->provider;
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
		$list = [];

		$baseItem = $this->getOrderBase( $order->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS );
		$addresses = $baseItem->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS;

		$base = $this->getOrderBase( $order->getBaseId(), $parts );
		$data = $this->getData( $base, $order->getId(), $params );

		$response = $this->sendRequest( $order, $data );

		if( !$response->isSuccessful() )
		{
			$this->saveOrder( $order->setPaymentStatus( Status::PAY_REFUSED ) );
			throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
		}

		$addresses = $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

		$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
		$this->saveRepayData( $response, $base->getCustomerId() );
		$this->saveOrder( $order );

		if( ( $approvalUrl = $response->getData()['links']['1']['href'] ?? null ) == null ) {
			throw new \Aimeos\MShop\Service\Exception( sprintf( 'PayPalPlus approval URL not available' ) );
		}

		if( ( $address = current( $addresses ) ) === false ) {
			throw new \Aimeos\MShop\Service\Exception( sprintf( 'PayPalPlus requires the country ID of the user' ) );
		}

		$html = $this->getPayPalPlusJs( $approvalUrl, $address->getCountryId() );
		return new \Aimeos\MShop\Common\Helper\Form\Standard( '', '', [], true, $html );
	}


	/**
	 * Updates the orders for whose status updates have been received by the confirmation page
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface $request Request object with parameters and request body
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item that should be updated
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item
	 * @throws \Aimeos\MShop\Service\Exception If updating the orders failed
	 */
	public function updateSync( \Psr\Http\Message\ServerRequestInterface $request,
		\Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		try
		{
			$provider = $this->getProvider();
			$base = $this->getOrderBase( $order->getBaseId() );

			$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();
			$params = $this->getData( $base, $order->getId(), $params );
			$params['transactionReference'] = $this->getTransactionReference( $base );

			if( $this->getValue( 'authorize', false ) && $provider->supportsCompleteAuthorize() )
			{
				$response = $provider->completeAuthorize( $params )->send();
				$status = Status::PAY_AUTHORIZED;
			}
			elseif( $provider->supportsCompletePurchase() )
			{
				$response = $provider->completePurchase( $params )->send();
				$status = Status::PAY_RECEIVED;
			}
			else
			{
				return $order;
			}

			// next command that get TransactionID was $response->getTransactionId() but it doesn't work
			if( $response->getRequest()->getTransactionId() != $order->getId() ) {
				return $order;
			}

			if( method_exists( $response, 'isSuccessful' ) && $response->isSuccessful() )
			{
				$order->setPaymentStatus( $status );
			}
			elseif( method_exists( $response, 'isPending' ) && $response->isPending() )
			{
				$order->setPaymentStatus( Status::PAY_PENDING );
			}
			elseif( method_exists( $response, 'isCancelled' ) && $response->isCancelled()
				|| ( $response->getData()['name'] ?? null )  === 'PAYMENT_NOT_APPROVED_FOR_EXECUTION' // should be in isCancelled()
			) {
				$order->setPaymentStatus( Status::PAY_CANCELED );
			}
			elseif( method_exists( $response, 'isRedirect' ) && $response->isRedirect() )
			{
				throw new \Aimeos\MShop\Service\Exception( sprintf( 'Unexpected redirect: %1$s', $response->getRedirectUrl() ) );
			}
			else
			{
				if( $order->getPaymentStatus() === Status::PAY_UNFINISHED ) {
					$this->saveOrder( $order->setPaymentStatus( Status::PAY_REFUSED ) );
				}

				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}

			$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
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
	 * Returns the HTML code for displaying the PayPalPlus form.
	 *
	 * @param string $approvalUrl Approval URL sent by PayPalPlus
	 * @param string $countryid Two letter ISO country code
	 * @return string HTML code
	 */
	protected function getPayPalPlusJs( string $approvalUrl, string $countryid ) : string
	{
		return '
			<div id="ppplus"></div>
			<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
			<script type="application/javascript">
				var ppp = PAYPAL.apps.PPP({
					"approvalUrl": \'' . $approvalUrl . '\',
					"placeholder": "ppplus",
					"country":"' . $countryid . '" ,
					"mode": "' . ( $this->getConfigValue( 'testmode' ) ? 'sandbox' : 'live' ) . '",
					onContinue: function () { ppp.doCheckout() } ,
				});
			</script>';
	}
}
