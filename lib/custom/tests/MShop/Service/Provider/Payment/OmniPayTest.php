<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class OmniPayTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;
	private $serviceItem;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::create( $this->context );
		$this->serviceItem = $serviceManager->createItem();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'OGONE' );

		$methods = [
			'getCustomerData', 'getOrder', 'getOrderBase', 'getTransactionReference', 'isImplemented',
			'saveOrder', 'saveOrderBase', 'getProvider', 'saveTransationRef', 'setCustomerData'
		];

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\OmniPay' )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->setMethods( $methods )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testGetConfigBE()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );

		$result = $object->getConfigBE();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'type', $result );
		$this->assertArrayHasKey( 'address', $result );
		$this->assertArrayHasKey( 'authorize', $result );
		$this->assertArrayHasKey( 'onsite', $result );
		$this->assertArrayHasKey( 'createtoken', $result );
		$this->assertArrayHasKey( 'testmode', $result );
	}


	public function testCheckConfigBE()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );

		$attributes = array(
			'type' => 'manual',
			'address' => '0',
			'authorize' => '1',
			'onsite' => '0',
			'createtoken' => '1',
			'testmode' => '1',
		);

		$result = $object->checkConfigBE( $attributes );

		$this->assertEquals( 6, count( $result ) );
		$this->assertEquals( null, $result['type'] );
		$this->assertEquals( null, $result['address'] );
		$this->assertEquals( null, $result['authorize'] );
		$this->assertEquals( null, $result['onsite'] );
		$this->assertEquals( null, $result['createtoken'] );
		$this->assertEquals( null, $result['testmode'] );
	}


	public function testIsImplemented()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );

		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_CANCEL ) );
		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_CAPTURE ) );
		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REFUND ) );
		$this->assertFalse( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY ) );
		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REPAY ) );
	}


	public function testProcessOnsiteAddress()
	{
		$conf = array(
			'type' => 'Dummy',
			'onsite' => '1',
			'address' => '1',
		);
		$this->serviceItem->setConfig( $conf );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $this->getOrderBase() ) );

		$result = $this->object->process( $this->getOrder(), [] );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOnsiteNoAddress()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Gateway\Manual' )->setMethods( null )->getMock();
		$this->object->expects( $this->any() )->method( 'getProvider' )->will( $this->returnValue( $provider ) );

		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_NONE );

		$conf = array(
				'type' => 'Dummy',
				'onsite' => '1',
		);
		$this->serviceItem->setConfig( $conf );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$result = $this->object->process( $this->getOrder(), [] );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOffsitePurchaseSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => '1' ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);
		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOffsitePurchaseFailure()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
		->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4444333322221111',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$this->setExpectedException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->process( $this->getOrder(), $params );
	}


	public function testProcessOffsiteAuthorizeSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1', 'onsite' => 1 ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
			'cvv' => '123',
		);

		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOffsiteAuthorizeFailure()
	{
		$provider = $this->getMockBuilder( \Omnipay\Dummy\Gateway::class )
			->setMethods( array( 'authorize' ) )
			->disableOriginalConstructor()
			->getMock();

		$provider->expects( $this->once() )->method( 'authorize' )
			->will( $this->throwException( new \RuntimeException() ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->setExpectedException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->process( $this->getOrder(), [] );
	}


	public function testProcessOffsiteRedirect()
	{
		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\ResponseRedirectTest' )
			->setMethods( array( 'getTransactionReference' ) )
			->disableOriginalConstructor()
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'saveTransationRef' );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '1234' ) );


		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
		$this->assertEquals( 'url', $result->getUrl() );
	}


	public function testUpdateSync()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'authorize' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '123' ) );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncNone()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncPurchaseSucessful()
	{
		$order = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'getTransactionId', 'isSuccessful' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'getTransactionId' )
			->will( $this->returnValue( $order->getId() ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdatePushSuccess()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsAcceptNotification', 'acceptNotification', 'saveOrder' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'getTransactionStatus', 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->will( $this->returnValue( ['orderid' => '1'] ) );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->will( $this->returnValue( $psr7response ) )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$request->expects( $this->once() )->method( 'getTransactionStatus' )
			->will( $this->returnValue( \Omnipay\Common\Message\NotificationInterface::STATUS_COMPLETED ) );

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushPending()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsAcceptNotification', 'acceptNotification', 'saveOrder' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'getTransactionStatus', 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isPending' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->will( $this->returnValue( ['orderid' => '1'] ) );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->will( $this->returnValue( $psr7response ) )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isPending' )
			->will( $this->returnValue( true ) );

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_PENDING;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushCancelled()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsAcceptNotification', 'acceptNotification' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'getTransactionStatus', 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isCancelled' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->will( $this->returnValue( ['orderid' => '1'] ) );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->will( $this->returnValue( $psr7response ) )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isCancelled' )
			->will( $this->returnValue( true ) );

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_CANCELED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushRefused()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsAcceptNotification', 'acceptNotification' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'getTransactionStatus', 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->will( $this->returnValue( ['orderid' => '1'] ) );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->will( $this->returnValue( $psr7response ) )
			->with( $this->equalTo( 500 ) );


		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdateSyncAuthorizeFailed()
	{
		$order = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompleteAuthorize', 'completeAuthorize' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->setMethods( array( 'send' ) )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->setMethods( array( 'getTransactionId', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->will( $this->returnValue( [] ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCompleteAuthorize' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completeAuthorize' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'getTransactionId' )
			->will( $this->returnValue( $order->getId() ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( false ) );


		$this->setExpectedException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->updateSync( $psr7request, $order );
	}


	public function testUpdateSyncRedirect()
	{
		$order = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->setMethods( array( 'send' ) )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\ResponseRedirectTest' )
			->setMethods( array( 'getTransactionId', 'getTransactionReference', 'isRedirect' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->will( $this->returnValue( [] ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'getTransactionId' )
			->will( $this->returnValue( $order->getId() ) );

		$response->expects( $this->once() )->method( 'isRedirect' )
			->will( $this->returnValue( true ) );


		$this->setExpectedException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->updateSync( $psr7request, $order );
	}


	public function testCancel()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsVoid', 'void' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'void' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->object->cancel( $orderItem );
	}


	public function testCancelNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsVoid' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->will( $this->returnValue( false ) );


		$this->object->cancel( $orderItem );
	}


	public function testCapture()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCapture', 'capture' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'capture' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->object->capture( $orderItem );
	}


	public function testCaptureNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCapture' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
		->will( $this->returnValue( false ) );


		$this->object->capture( $orderItem );
	}


	public function testRefund()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsRefund', 'refund' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'refund' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->object->refund( $orderItem );
	}


	public function testRefundNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsRefund' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->will( $this->returnValue( false ) );


		$this->object->refund( $orderItem );
	}


	public function testRepay()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'getCard', 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'isImplemented' )
			->will( $this->returnValue( true ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
			->will( $this->returnValue( ['token' => '123', 'month' => '01', 'year' => '99'] ) );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$this->object->expects( $this->once() )->method( 'saveTransationRef' );

		$this->object->expects( $this->once() )->method( 'saveOrder' );


		$this->object->repay( $orderItem );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context );

		$search = $manager->createSearch();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		$result = $manager->searchItems( $search );

		if( ( $item = reset( $result ) ) === false ) {
			throw new \RuntimeException( 'No order found' );
		}

		return $item;
	}


	protected function getOrderBase( $parts = null )
	{
		if( $parts === null ) {
			$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		}

		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context )->getSubmanager( 'base' );

		return $manager->load( $this->getOrder()->getBaseId(), $parts );
	}
}


if( class_exists( 'Omnipay\Dummy\Message\Response' )
	&& interface_exists( 'Omnipay\Common\Message\RedirectResponseInterface' ) )
{
	class ResponseRedirectTest
		extends \Omnipay\Dummy\Message\Response
		implements \Omnipay\Common\Message\RedirectResponseInterface
	{
		public function isRedirect()
		{
			return true;
		}

		public function getRedirectUrl()
		{
			return 'url';
		}

		public function getRedirectMethod()
		{
			return 'POST';
		}

		public function getRedirectData()
		{
			return array( 'key' => 'value' );
		}

		public function getTransactionReference()
		{
			return 123;
		}
	}
}
