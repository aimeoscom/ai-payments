<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2020
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


use Omnipay\Omnipay as OPay;
use Aimeos\MShop\Order\Item\Base as Status;

/**
 * Payment provider for payment gateways supported by the PaypalPlus library.
 *
 * @package MShop
 * @subpackage Service
 */
class PaypalPlus
extends    \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $beConfig = array(
		'clientid' => array(
			'code' => 'clientid',
			'internalcode'=> 'clientid',
			'label'=> 'client id from paypal',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '0',
			'required'=> true,
		),
		'secret' => array(
			'code' => 'secret',
			'internalcode'=> 'secret',
			'label'=> 'secret string from paypal',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '0',
			'required'=> true,
		),
		'onsite' => array(
			'code' => 'onsite',
			'internalcode'=> 'onsite',
			'label'=> 'Collect data locally',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> true,
		),
		'cancelUrl' => array(
			'code' => 'cancelurl',
			'internalcode'=> 'cancelurl',
			'label'=> 'cancelurl for paypal',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> 'www.example.com',
			'required'=> false,
		),
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
			'default'=> '1',
			'required'=> true,
		),
		'createtoken' => array(
			'code' => 'createtoken',
			'internalcode'=> 'createtoken',
			'label'=> 'Request token for recurring payments',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '1',
			'required'=> true,
		),
		'testmode' => array(
			'code' => 'testmode',
			'internalcode'=> 'testmode',
			'label'=> 'Test mode without payments',
			'type'=> 'boolean',
			'internaltype'=> 'boolean',
			'default'=> '0',
			'required'=> true,
		),
	);
	private $provider;

	private $feConfig = array(
		'payment.firstname' => array(
			'code' => 'payment.firstname',
			'internalcode'=> 'firstName',
			'label'=> 'First name',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
		//	'public' => false ,
 			'required'=> false
		),
	);

	public function getConfigBE() : array
	{
		$list = [];

		foreach( $this->beConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}

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


	protected function sendRequest( \Aimeos\MShop\Order\Item\Iface $order, array $data ) : \Omnipay\Common\Message\ResponseInterface
	{
		$provider = $this->getProvider();

		if( $this->getValue( 'authorize', false ) && $provider->supportsAuthorize() )
		{
			$response = $provider->authorize( $data )->send();
			$order->setPaymentStatus( Status::PAY_AUTHORIZED );
		}
		else
		{
			$response = $provider->purchase( $data )->send();
			$order->setPaymentStatus( Status::PAY_RECEIVED );
		}

		return $response;
	}

	protected function getPaymentForm( \Aimeos\MShop\Order\Item\Iface $order, array $params ) : \Aimeos\MShop\Common\Helper\Form\Iface
	{
		$list = [];
		$feConfig = $this->feConfig;
		$baseItem = $this->getOrderBase( $order->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS );
		$addresses = $baseItem->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

		$year = date( 'Y' );

		foreach( $feConfig as $key => $config ) {
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		// /* section of preparation to call paypal plus form
		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE
		| \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT
		| \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS;

		$base = $this->getOrderBase( $order->getBaseId(), $parts );
		$data = $this->getData( $base, $order->getId(), $params );
		$urls = $this->getPaymentUrls();

		/* 
		$this->getPaymentUrls() returns cancelUrl same as in returnUrl 
		which is wrong ! so we added this line and comment it till we get sure about it !
		// $data['cancelUrl'] = $this->getConfigValue( 'cancelUrl', '' ) ;

		if(empty($data['cancelUrl'])) {
			$data['cancelUrl'] = $_SERVER['HTTP_ORIGIN'] ;
		}
		*/

		try
		{
			$response = $this->sendRequest( $order, $data );
			$approvalUrl = "" ;
			$testmode = "" ;
			$countryid = "" ;

			if( $response->isSuccessful() )
			{
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
				$this->saveRepayData( $response, $base->getCustomerId() );

				$status = $this->getValue( 'authorize', false ) ? Status::PAY_AUTHORIZED : Status::PAY_RECEIVED;
				$this->saveOrder( $order->setPaymentStatus( $status ) );
				
				if(!empty($response->getData()['links']['1']['href'])){
					$approvalUrl = $response->getData()['links']['1']['href'] ;
				}

				$addresses = $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

				if( ( $address = current( $addresses ) ) !== false ) {
					$countryid = $address->getCountryId();
				}
				
				if(empty($approvalUrl) || empty($countryid) || empty($data['cancelUrl']) ){
					throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
				}

			}
			elseif( $response->isRedirect() )
			{
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
				return $this->getRedirectForm( $response );
			}
			else
			{
				$this->saveOrder( $order->setPaymentStatus( Status::PAY_REFUSED ) );
				throw new \Aimeos\MShop\Service\Exception( $response->getMessage() );
			}
		}
		catch( \Exception $e )
		{
			throw new \Aimeos\MShop\Service\Exception( $e->getMessage() );
		} 
		// end of preparation section to call paypal plus */

		return new \Aimeos\MShop\Common\Helper\Form\Standard('','',[], true ,$this->getPayPalPlusJs($approvalUrl ,$countryid ) );
	}

	protected function getPayPalPlusJs($approvalUrl ,$countryid )  
	{
		$testmode = $this->getConfigValue( 'testmode' ) ? 'sandbox' : 'live';
		return '
		<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript">
		</script>
		<div id="ppplus">
		</div>
		<script type="application/javascript">		  
		var approvalUrl = \'' . $approvalUrl . '\'; 
 

			var ppp = PAYPAL.apps.PPP({
			"approvalUrl": approvalUrl,
			"placeholder": "ppplus",
			"country":"' . $countryid . '" ,
			"mode": "' . $testmode . '",
			});
		</script>
		';
	}

	
	public function process( \Aimeos\MShop\Order\Item\Iface $order, array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
	{
		return $this->getPaymentForm( $order, $params );
	}


	protected function getData( \Aimeos\MShop\Order\Item\Base\Iface $base, string $orderid, array $params ) : array
	{	
		$addresses = $base->getAddress( \Aimeos\MShop\Order\Item\Base\Address\Base::TYPE_PAYMENT );

		if( ( $address = current( $addresses ) ) === false ) {
			$langid = $this->getContext()->getLocale()->getLanguageId();
		} else {
			$langid = $address->getLanguageId();
		}

		$context = $this->getContext();
		$payerid = "payerid-" .  $context->getUserId() ?: $orderid;
		
		$data = array(
			'language' => $langid,
			'transactionId' => $orderid,
			'payerid' => $payerid,
			'amount' => $this->getAmount( $base->getPrice() ),
			'currency' => $base->getLocale()->getCurrencyId(),
			'description' => sprintf( $this->getContext()->getI18n()->dt( 'mshop', 'Order %1$s' ), $orderid ),
			'clientIp' => $this->getValue( 'client.ipaddress' ),
		);

		if( $this->getValue( 'createtoken', false ) ) {
			$data['createCard'] = true;
		}

		if( $this->getValue( 'onsite', false ) || $this->getValue( 'address', false ) ) {
			$data['card'] = $this->getCardDetails( $base, $params );
		}

		return $data + $this->getPaymentUrls();
	}

}
