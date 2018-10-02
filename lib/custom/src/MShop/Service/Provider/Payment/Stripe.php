<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;

use Omnipay\Omnipay as OPay;

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
		'address' => array(
			'code' => 'address',
			'internalcode'=> 'address',
			'label'=> 'Send address to payment gateway too',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'authorize' => array(
			'code' => 'authorize',
			'internalcode'=> 'authorize',
			'label'=> 'Authorize payments and capture later',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
		),
		'testmode' => array(
			'code' => 'testmode',
			'internalcode'=> 'testmode',
			'label'=> 'Test mode without payments',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> false,
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
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> false
		),
		'payment.expiry' => array(
			'code' => 'payment.expiry',
			'internalcode'=> 'expiry',
			'label'=> 'Expiry',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> false
		),
		'payment.cvv' => array(
			'code' => 'payment.cvv',
			'internalcode'=> 'cvv',
			'label'=> 'Verification number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> false
		),
	);

	private $provider;


	/**
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function getConfigPrefix()
	{
		return 'stripe';
	}


	/**
	 * Returns the Omnipay gateway provider object.
	 *
	 * @return \Omnipay\Common\GatewayInterface Gateway provider object
	 */
	protected function getProvider()
	{
		$config = $this->getServiceItem()->getConfig();
		$config['apiKey'] = $config['stripe.apiKey'];

		if( !isset( $this->provider ) )
		{
			$this->provider = OPay::create( 'Stripe' );
			$this->provider->setTestMode( (bool) $this->getValue( 'testmode', false ) );
			$this->provider->initialize( $config );
		}

		return $this->provider;
	}



	/**
	 * Tries to get an authorization or captures the money immediately for the given order if capturing the money
	 * separately isn't supported or not configured by the shop owner.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order invoice object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Standard Form object with URL, action and parameters to redirect to
	 *    (e.g. to an external server of the payment provider or to a local success page)
	 */
	public function process(\Aimeos\MShop\Order\Item\Iface $order, array $params = [])
	{
		if( !isset( $params['paymenttoken'] ) ) {
			return $this->getPaymentForm( $order, $params );
		}
		return $this->processOrder($order, $params);
	}


	/**
	 * Returns the payment form for entering payment details at the shop site.
	 *
	 * @param \Aimeos\MShop\Order\Item\Iface $order Order object
	 * @param array $params Request parameter if available
	 * @return \Aimeos\MShop\Common\Item\Helper\Form\Iface Form helper object
	 */
	protected function getPaymentForm(\Aimeos\MShop\Order\Item\Iface $order, array $params)
	{
		$list = [];
		$feConfig = $this->feConfig;

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		$url = $this->getConfigValue( 'payment.url-self' );
		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard( $url, 'POST', $list, false, $this->getHtmlForm() );
	}




	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the frontend.
	 *
	 * @param \Aimeos\MShop\Order\Item\Base\Iface $basket Basket object
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigFE(\Aimeos\MShop\Order\Item\Base\Iface $basket)
	{
		return [];
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
		$data = parent::getData( $base, $orderid, $params );

		if( isset( $params['paymenttoken'] ) ) {
			$data['token'] = $params['paymenttoken'];
		}

		return $data;
	}


	/**
	 * Checks the frontend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes entered by the customer during the checkout process
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 *    known by the provider but aren't valid resp. null for attributes whose values are OK
	 */
	public function checkConfigFE(array $attributes)
	{
		return [];
	}


	public function getHtmlForm()
	{
		return '
<script src="https://js.stripe.com/v3/"></script>
<script type="text/javascript">

StripeProvider = {
	stripe: "",
	elements: "",
	token_element: "",
	token_selector: "input[name=paymenttoken]",
	errors_selector: "card-errors",
	form_selector: ".checkout-standard form",
	payment_button_id: "payment-button",

	init: function(publishableKey,elements_array){
		StripeProvider.stripe = Stripe(publishableKey);
		StripeProvider.elements = StripeProvider.stripe.elements();
		StripeProvider.createElements(elements_array);

		var button = document.getElementById(StripeProvider.payment_button_id);
		button.addEventListener("click", function (event) {
			event.preventDefault();
			StripeProvider.stripe.createToken(StripeProvider.token_element).then(function (result) {
				if (result.error) {
					document.querySelectorAll( StripeProvider.errors_selector ).value = result.error.message;
				} else {
					StripeProvider.tokenHandler(result.token);
				}
			});
		});

	},

	handleEvent: function(event){
		var displayError = document.getElementById(StripeProvider.errors_selector);
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
		var input = document.querySelectorAll( StripeProvider.token_selector);
		input[0].value= token.id;
		this.submitPurchaseForm();
	},

	submitPurchaseForm: function () {
		var form = document.querySelectorAll(StripeProvider.form_selector);
		form[0].submit();
	}
};

document.addEventListener("DOMContentLoaded", function() {
	StripeProvider.init("' . $this->getConfigValue( 'stripe.publishableKey', '' ) . '",
		[
			{"element": "cardNumber", "selector": ".payment-cardno"},
			{"element": "cardExpiry", "selector": ".payment-expiry"},
			{"element": "cardCvc", "selector": ".payment-cvv"}
		]
	);
});

</script>

<!-- Used to display Element errors -->
<div id="card-errors" role="alert"></div>';
	}
}
