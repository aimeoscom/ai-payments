<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2017
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
			'label'=> 'publishable key',
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
			'required'=> true
		),
		'payment.cvv' => array(
			'code' => 'payment.cvv',
			'internalcode'=> 'cvv',
			'label'=> 'Verification number',
			'type'=> 'number',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
		),
		'payment.expiryyear' => array(
			'code' => 'payment.expiryyear',
			'internalcode'=> 'expiryYear',
			'label'=> 'Expiry year',
			'type'=> 'select',
			'internaltype'=> 'integer',
			'default'=> '',
			'required'=> true
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

		foreach ($feConfig as $key => $config) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard($config);
		}
		$url = $this->getConfigValue(array('payment.url-self'));
		return new \Aimeos\MShop\Common\Item\Helper\Form\Standard($url, 'POST', $list, false, $this->getHtmlForm());
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

		$list = [];
		$feconfig = $this->feConfig;

		try {
			$code = $this->getServiceItem()->getCode();
			$service = $basket->getService(\Aimeos\MShop\Order\Item\Base\Service\Base::TYPE_PAYMENT, $code);

			foreach ($service->getAttributes() as $item) {
				if (isset($feconfig[$item->getCode()])) {
					if (is_array($feconfig[$item->getCode()]['default'])) {
						$feconfig[$item->getCode()]['default'] = array_merge(array($item->getValue()), $feconfig[$item->getCode()]['default']);
					} else {
						$feconfig[$item->getCode()]['default'] = $item->getValue();
					}
				}
			}
		} catch (\Aimeos\MShop\Order\Exception $e) {
			;
		} // If payment isn't available yet


		/*try
		{
			$address = $basket->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

			if( $feconfig['novalnetcredit.holder']['default'] == ''
				&& ( $fn = $address->getFirstname() ) !== '' && ( $ln = $address->getLastname() ) !== ''
			) {
				$feconfig['novalnetcredit.holder']['default'] = $fn . ' ' . $ln;
			}
		}
		catch( \Aimeos\MShop\Order\Exception $e ) { ; } // If address isn't available*/


		foreach ($feconfig as $key => $config) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard($config);
		}

		return $list;

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
		$data = parent::getData($base,$orderid,$params);
		if( isset($params['paymenttoken']) ){
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
		//return $this->checkConfig($this->feConfig, $attributes);
	}

	public function getHtmlForm()
	{
		return '<script src="https://js.stripe.com/v3/"></script>
		<script type="text/javascript">
    	$(document).ready(function () {
        var stripe = Stripe("'.$this->getConfigValue( array( $this->getConfigPrefix() . '.publishableKey' ), '' ).'");
        var elements = stripe.elements();


        // Custom styling can be passed to options when creating an Element.
        var classes = {
            base: "form-item-value"
        };

        // Create an instance of the card Element
        var cardNumber = elements.create("cardNumber", {classes: classes});
        // Add an instance of the card Element into the `card-element` <div>
        cardNumber.mount("#payment-number");
        cardNumber.addEventListener("change", function (event) {
            var displayError = document.getElementById("card-errors");
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = "";
            }
        });
        var cardExpiry = elements.create("cardExpiry", {classes: classes});
        // Add an instance of the card Element into the `card-element` <div>
        cardExpiry.mount("#payment-expiryYear");
        cardExpiry.addEventListener("change", function (event) {
            var displayError = document.getElementById("card-errors");
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = "";
            }
        });
        var cardCvc = elements.create("cardCvc", {classes: classes});
        // Add an instance of the card Element into the `card-element` <div>
        cardCvc.mount("#payment-cvv");
        cardCvc.addEventListener("change", function (event) {
            var displayError = document.getElementById("card-errors");
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = "";
            }
        });

        // Custom JS purchase handler for PaymentProvider defined by $id
        // It"s not necessary to creating. Just if current Payment Provider need so
        AimeosPurchaseHandler.AimeosProviders.beforePurchaseStripe = function () {
            // Do something specific for Stripe before submit the form
            console.log("specific for Stripe");
            stripe.createToken(cardNumber).then(function (result) {
                console.log("result");
                console.log(result);
                if (result.error) {
                    $("#card-errors").val(result.error.message);
                } else {
                    // Send the token to your server
                    stripeTokenHandler(result.token);
                }
            });
        };


        function stripeTokenHandler(token) {
            $("#payment-paymenttoken").val(token.id);			
            $("input[name=paymenttoken]").val(token.id);
            AimeosPurchaseHandler.submitPurchaseForm();
        }


    });

</script>

	<!-- Used to display Element errors -->
	<div id="card-errors" role="alert"></div>
	<input type="hidden" id="CurrentPaymentMethod" value="Stripe" />';

	}


}
