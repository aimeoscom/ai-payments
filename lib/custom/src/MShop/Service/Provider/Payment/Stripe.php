<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2021
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;

use Aimeos\MShop\Order\Item\Base as Status;


/**
 * Payment provider for Stripe.
 *
 * @package MShop
 * @subpackage Service
 */
class Stripe
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{

	private $beConfig = array(
		'type' => array(
			'code' => 'type',
			'internalcode'=> 'type',
			'label'=> 'Payment provider type',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> 'Stripe_PaymentIntents',
			'required'=> true,
		),
		'apiKey' => array(
			'code' => 'apiKey',
			'internalcode'=> 'apiKey',
			'label'=> 'API key',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
		'publishableKey' => array(
			'code' => 'publishableKey',
			'internalcode'=> 'publishableKey',
			'label'=> 'Publishable key',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
	);

	protected $feConfig = array(
		'paymenttoken' => array(
			'code' => 'paymenttoken',
			'internalcode' => 'paymenttoken',
			'label' => 'Authentication token',
			'type' => 'string',
			'internaltype' => 'integer',
			'default' => '',
			'required' => true,
			'public' => false,
		),
		'payment.cardno' => array(
			'code' => 'payment.cardno',
			'internalcode'=> 'number',
			'label'=> 'Credit card number',
			'type'=> 'container',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> false
		),
		'payment.expiry' => array(
			'code' => 'payment.expiry',
			'internalcode'=> 'expiry',
			'label'=> 'Expiry',
			'type'=> 'container',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> false
		),
		'payment.cvv' => array(
			'code' => 'payment.cvv',
			'internalcode'=> 'cvv',
			'label'=> 'Verification number',
			'type'=> 'container',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> false
		),
	);


	/**
	 * Checks the backend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes added by the shop owner in the administraton interface
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 * 	known by the provider but aren't valid resp. null for attributes whose values are OK
	 */
	public function checkConfigBE( array $attributes ) : array
	{
		return array_merge( parent::checkConfigBE( $attributes ), $this->checkConfig( $this->beConfig, $attributes ) );
	}


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigBE() : array
	{
		$list = parent::getConfigBE();

		foreach( $this->beConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}


	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface|null Form object with URL, action and parameters to redirect to
	 *    (e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
	{
		if( !isset( $params['paymenttoken'] ) ) {
			return $this->getPaymentForm( $order, $params );
		}

		if( ( $userid = $this->getContext()->getUserId() ) !== null
			&& $this->getCustomerData( $userid, 'customer' ) === null
			&& $this->getConfigValue( 'createtoken' )
		) {
			$data = [];
			$base = $this->getOrderBase( $order->getBaseId() );

			if( $addr = current( $base->getAddress( 'payment' ) ) )
			{
				$data['description'] = $addr->getFirstName() . ' ' . $addr->getLastName();
				$data['email'] = $addr->getEmail();
			}

			$response = $this->getProvider()->createCustomer( $data )->send();

			if( $response->isSuccessful() ) {
				$this->setCustomerData( $userid, 'customer', $response->getCustomerReference() );
			}
		}

		return $this->processOrder( $order, $params );
	}


	/**
	 * Executes the payment again for the given order if supported.
	 * This requires support of the payment gateway and token based payment
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @return \Aimeos\MShop\Order\Item\Iface Updated order item object
	 */
	public function repay( \Aimeos\MShop\Order\Item\Iface $order ) : \Aimeos\MShop\Order\Item\Iface
	{
		$base = $this->getOrderBase( $order->getBaseId() );

		if( ( $custid = $this->getCustomerData( $base->getCustomerId(), 'customer' ) ) === null )
		{
			$msg = sprintf( 'No Stripe customer data available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( ( $cfg = $this->getCustomerData( $base->getCustomerId(), 'repay' ) ) === null )
		{
			$msg = sprintf( 'No Stripe payment method available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		if( !isset( $cfg['token'] ) )
		{
			$msg = sprintf( 'No payment token available for customer ID "%1$s"', $base->getCustomerId() );
			throw new \Aimeos\MShop\Service\Exception( $msg );
		}

		$response = $this->getProvider()->purchase( [
			'transactionId' => $order->getId(),
			'currency' => $base->getPrice()->getCurrencyId(),
			'amount' => $this->getAmount( $base->getPrice() ),
			'cardReference' => $cfg['token'],
			'customerReference' => $custid,
			'off_session' => true,
			'confirm' => true,
		] )->send();

		if( $response->isSuccessful() || $response->isPending() )
		{
			$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
			$order = $this->saveOrder( $order->setPaymentStatus( Status::PAY_RECEIVED ) );
		}
		elseif( !$response->getTransactionReference() )
		{
			$msg = $this->getContext()->i18n()->dt( 'mshop', 'Token based payment incomplete: %1$s' );
			throw new \Aimeos\MShop\Service\Exception( print_r( $response->getData(), true ), 1 );
		}
		else
		{
			$str = ( method_exists( $response, 'getMessage' ) ? $response->getMessage() : '' );
			$msg = $this->getContext()->i18n()->dt( 'mshop', 'Token based payment failed: %1$s' );
			throw new \Aimeos\MShop\Service\Exception( sprintf( $msg, $str ), -1 );
		}

		return $order;
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
		if( $order->getPaymentStatus() === Status::PAY_UNFINISHED )
		{
			$response = $this->getProvider()->confirm( [
				'paymentIntentReference' => $this->getOrderData( $order, 'Reference' )
			] )->send();

			if( $response->isSuccessful() )
			{
				$status = $this->getValue( 'authorize', false ) ? Status::PAY_AUTHORIZED : Status::PAY_RECEIVED;
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );

				if( $paymethod = $response->getCardReference() ) {
					$this->setCustomerData( $this->getContext()->getUserId(), 'repay', ['token' => $paymethod] );
				}
			}
			else
			{
				$status = Status::PAY_REFUSED;
			}

			$this->saveOrder( $order->setPaymentStatus( $status ) );
		}

		return $order;
	}


	/**
	 * Returns the data passed to the Omnipay library
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $base Basket object
	 * @param string $orderid Unique order ID
	 * @param array $params Request parameter if available
	 * @return array Associative list of key/value pairs
	 */
	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, string $orderid, array $params ) : array
	{
		$session = $this->getContext()->getSession();
		$data = parent::getData( $base, $orderid, $params );

		if( isset( $params['paymenttoken'] ) ) {
			$session->set( 'aimeos/stripe_token', $params['paymenttoken'] );
		}

		if( ( $token = $session->get( 'aimeos/stripe_token' ) ) !== null ) {
			$data['token'] = $token;
		}

		if( $this->getContext()->getUserId() && $this->getConfigValue( 'createtoken' )
			&& $custid = $this->getCustomerData( $this->getContext()->getUserId(), 'customer' )
		) {
			$data['customerReference'] = $custid;
		}

		$type = \Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT;
		$serviceItem = $this->getBasketService( $base, $type, $this->getServiceItem()->getCode() );

		if( $stripeIntentsRef = $serviceItem->getAttribute( 'Reference', 'payment/omnipay' ) ) {
			$data['paymentIntentReference'] = $stripeIntentsRef;
		}

		$data['confirm'] = true;

		return $data;
	}


	/**
	 * Returns the payment form for entering payment details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];
		$feConfig = $this->feConfig;

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		$url = $this->getConfigValue( 'payment.url-self', '' );
		return new \Aimeos\MShop\Common\Helper\Form\Standard( $url, 'POST', $list, false, $this->getStripeJs() );
	}


	/**
	 * Returns the required Javascript code for Stripe payment form
	 *
	 * @return string Stripe JS code
	 */
	protected function getStripeJs() : string
	{
		return '
<script src="https://js.stripe.com/v3/"></script>
<script type="text/javascript">

StripeProvider = {
	stripe: "",
	elements: "",
	token_element: "",
	token_selector: "#process-paymenttoken",
	form_selector: ".checkout-standard form",
	payment_button_id: "payment-button",
	errors_selector_id: "card-errors",

	init: function(publishableKey,elements_array){
		StripeProvider.stripe = Stripe(publishableKey);
		StripeProvider.elements = StripeProvider.stripe.elements();
		StripeProvider.createElements(elements_array);

		var button = document.getElementById( StripeProvider.payment_button_id );
		button.addEventListener("click", function (event) {
			button.disabled = true;
			event.preventDefault();
			StripeProvider.stripe.createToken(StripeProvider.token_element).then(function (result) {
				if (result.error) {
					document.getElementById( StripeProvider.errors_selector_id ).textContent = result.error.message;
					button.disabled = false;
				} else {
					StripeProvider.tokenHandler( result.token );
				}
			});
		});
	},

	handleEvent: function(event){
		var displayError = document.getElementById( StripeProvider.errors_selector_id );
		if (event.error) {
			displayError.textContent = event.error.message;
		} else {
			displayError.textContent = "";
		}
	},

	// Creating Stripe Elements from an array
	createElements: function (elements_array) {
		var classes = {
			base: "form-item-value"
		};
		for(var x=0; x < elements_array.length; x++){
			var element = elements_array[x].element;
			element = StripeProvider.elements.create(elements_array[x].element, {classes: classes});
			element.mount(elements_array[x].selector);
			element.addEventListener("change", function (event) {
				StripeProvider.handleEvent(event);
			});
			if(elements_array[x].element === "cardNumber") StripeProvider.token_element = element;
		}
	},

	// Actions with recieved token
	tokenHandler: function (token) {
		var input = document.querySelectorAll( StripeProvider.token_selector );
		input[0].value= token.id;
		this.submitPurchaseForm();
	},

	submitPurchaseForm: function () {
		var form = document.querySelectorAll( StripeProvider.form_selector );
		form[0].submit();
	}
};

document.addEventListener("DOMContentLoaded", function() {
	StripeProvider.init("' . $this->getConfigValue( 'publishableKey', '' ) . '",
		[
			{"element": "cardNumber", "selector": "div[id=\"process-payment.cardno\"]"},
			{"element": "cardExpiry", "selector": "div[id=\"process-payment.expiry\"]"},
			{"element": "cardCvc", "selector": "div[id=\"process-payment.cvv\"]"}
		]
	);
});

</script>

<!-- Used to display Element errors -->
<div id="card-errors" role="alert"></div>';
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
		if( $this->getConfigValue( 'createtoken' ) ) {
			$data['setup_future_usage'] = 'off_session';
		}

		$response = parent::sendRequest( $order, $data );

		if( method_exists( $response, 'getPaymentIntentReference' ) ) {
			$this->setOrderData( $order, ['Reference' => $response->getPaymentIntentReference()] );
		}

		return $response;
	}
}
