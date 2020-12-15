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

use Illuminate\Support\Facades\Auth;


/**
 * Payment provider for payment gateways supported by the PaypalPlus library.
 *
 * @package MShop
 * @subpackage Service
 */
class PaypalPlus
extends    \Aimeos\MShop\Service\Provider\Payment\OmniPay
//	extends \Aimeos\MShop\Service\Provider\Payment\Base
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{

	protected function processOrder( \Aimeos\MShop\Order\Item\Iface $order,
		array $params = [] ) : ?\Aimeos\MShop\Common\Helper\Form\Iface
	{
		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_PRODUCT
			| \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS;

		$base = $this->getOrderBase( $order->getBaseId(), $parts );
		$data = $this->getData( $base, $order->getId(), $params );
		$urls = $this->getPaymentUrls();

		$data['cancelUrl'] = $this->getConfigValue( 'cancelUrl', '' ) ;

		if(empty($data['cancelUrl'])) {
			$data['cancelUrl'] = $_SERVER['HTTP_ORIGIN'] ;
		}
		
		try
		{
			$response = $this->sendRequest( $order, $data );
			$isSuccessful = $response->isSuccessful()  ;

			$approval_url = "" ;
			$testmode = "" ;
			$countryid = "" ;

			if( $isSuccessful )
			{
				$this->setOrderData( $order, ['Transaction' => $response->getTransactionReference()] );
				$this->saveRepayData( $response, $base->getCustomerId() );

				$status = $this->getValue( 'authorize', false ) ? Status::PAY_AUTHORIZED : Status::PAY_RECEIVED;
				$this->saveOrder( $order->setPaymentStatus( $status ) );
				
				if(!empty($response->getData()['links']['1']['href'])){
					$approval_url = $response->getData()['links']['1']['href'] ;
				}
		
				
				$testmode = $this->getConfigValue( 'testmode', '' ) ;
				if(!empty($testmode) && $testmode=="1"){
					$testmode = "sandbox" ;
				}elseif(!empty($testmode) && $testmode=="0"){
					$testmode = "live" ;
				}
	
				$countryid = Auth::user()->countryid;


				if(empty($approval_url)  || empty($testmode)  || empty($countryid) || empty($data['cancelUrl']) ){
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


		
		return new \Aimeos\MShop\Common\Helper\Form\Standard( $urls['returnUrl'] ?? '', 'POST', [], false ,$this->getPayPalPlusJs($approval_url,$testmode ,$countryid ) );

	}

	protected function getPayPalPlusJs($approval_url,$testmode ,$countryid ) 
	{
		echo '
		<script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js" type="text/javascript">
		</script>
		<div id="ppplus">
		</div>
		<script>
		var approval_ur = \'' . $approval_url . '\'; 
		</script> 
		<script type="application/javascript">		  
			var ppp = PAYPAL.apps.PPP({
			"approvalUrl": approval_ur,
			"placeholder": "ppplus",
			"country":"' . $countryid . '" ,
			"mode": "' . $testmode . '",
			});
		</script>
		';
		exit();
	}

}
