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
		'authorize' => array(
			'code' => 'authorize',
			'internalcode'=> 'authorize',
			'label'=> 'Authorize payments and capture later',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
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
			$this->provider->setTestMode( (bool) $this->getValue( 'testmode', false ) );
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

		$approvalUrl = '';
		$addresses = $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

		$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
		$this->saveRepayData( $response, $base->getCustomerId() );
		$this->saveOrder( $order );

		foreach( $response->getData()['links'] ?? [] as $entry )
		{
			if( $entry['rel'] === 'approval_url' ) {
				$approvalUrl = $entry['href'];
			}
		}

		if( empty( $approvalUrl ) )
		{
			$msg = $this->getContext()->i18n()->dt( 'mshop', 'PayPalPlus approval URL not available' );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( ( $address = current( $addresses ) ) === false )
		{
			$msg = $this->getContext()->i18n()->dt( 'mshop', 'PayPalPlus requires the country ID of the user' );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$langid = $address->getLanguageId() ?: $this->getContext()->getLocale()->getLanguageId();

		$html = $this->getPayPalPlusJs( $approvalUrl, (string) $address->getCountryId(), (string) $langid );
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
		if( empty( $request->getQueryParams()['PayerID'] ) ) {
			return $this->saveOrder( $order->setPaymentStatus( Status::PAY_CANCELED ) );
		}

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
			elseif( method_exists( $response, 'isCancelled' ) && $response->isCancelled() )
			{
				$order->setPaymentStatus( Status::PAY_CANCELED );
			}
			elseif( method_exists( $response, 'isRedirect' ) && $response->isRedirect() )
			{
				$msg = $this->getContext()->i18n()->dt( 'mshop', 'Unexpected redirect: %1$s' );
				throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, $response->getRedirectUrl() ) );
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
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Basket object
	 * @param string $orderid string Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, string $orderid, array $params ) : array
	{
		return ['PayerID' => $params['PayerID'] ?? null] + parent::getData( $base, $orderid, $params );
	}


	/**
	 * Returns the HTML code for displaying the PayPalPlus form.
	 *
	 * @param string $approvalUrl Approval URL sent by PayPalPlus
	 * @param string $countryid Two letter ISO country code
	 * @param string $languageid Two letter ISO language code
	 * @return string HTML code
	 */
	protected function getPayPalPlusJs( string $approvalUrl, string $countryid, string $languageid ) : string
	{
		return '
			<div id="ppplus"></div>
			<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript"></script>
			<script type="application/javascript">
				var ppp = PAYPAL.apps.PPP({
					"preselection": "none",
					"placeholder": "ppplus",
					"showLoadingIndicator": true,
					"country": "' . $countryid . '",
					"language": "' . $languageid . '",
					"approvalUrl": "'. $approvalUrl . '",
					"mode": "' . ( $this->getConfigValue( 'testmode' ) ? 'sandbox' : 'live' ) . '",
					onContinue: function () { ppp.doCheckout() } ,
				});
			</script>';
	}


	/**
	 * Sends the given data for the order to the payment gateway
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order item which should be paid
	 * @param array $data Associative list of key/value pairs sent to the payment gateway
	 * @return \Omnipay\Common\Message\ResponseInterface Omnipay response from the payment gateway
	 */
	protected function sendRequest( \Aimeos\MShop\Order\Item\Iface $order, array $data ) : \Omnipay\Common\Message\ResponseInterface
	{
		$provider = $this->getProvider();

		if( $this->getValue( 'authorize', false ) && $provider->supportsAuthorize() ) {
			$response = $provider->authorize( $data )->send();
		} else {
			$response = $provider->purchase( $data )->send();
		}

		return $response;
	}
}
