<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2026
 */


namespace Aimeos\MShop\Service\Provider\Payment;


#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class OmniPayTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;
	private $serviceItem;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$methods = [
			'data', 'getTransactionReference', 'isImplemented', 'query',
			'save', 'getProvider', 'getOrderData', 'setOrderData', 'setData'
		];

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\OmniPay' )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->onlyMethods( $methods )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testGetConfigBE()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );

		$result = $object->getConfigBE();

		$this->assertIsArray( $result );
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


	public function testCreateClient()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );
		$client = ( new \ReflectionMethod( $object, 'createClient' ) )->invoke( $object );

		$this->expectException( \Omnipay\Common\Http\Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs of public hosts are allowed' );

		$client->request( 'GET', 'https://127.0.0.1/' );
	}


	public function testCreateGatewayConfig()
	{
		$this->serviceItem->setConfig( ['type' => 'Dummy', 'testmode' => '1'] );

		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );
		$gateway = ( new \ReflectionMethod( $object, 'createGateway' ) )->invoke( $object, 'Dummy' );

		$this->assertTrue( (bool) $gateway->getTestMode() );
	}


	public function testGetProviderNoType()
	{
		$this->serviceItem->setConfig( [] );

		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		( new \ReflectionMethod( $object, 'getProvider' ) )->invoke( $object );
	}


	public function testGetProviderCustomEndpoint()
	{
		if( !class_exists( \Omnipay\Payone\ShopServerGateway::class ) ) {
			$this->markTestSkipped( 'Payone gateway not available' );
		}

		$this->serviceItem->setConfig( ['type' => 'Payone_ShopServer', 'endpoint' => 'https://8.8.8.8/post-gateway/'] );

		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );
		$provider = ( new \ReflectionMethod( $object, 'getProvider' ) )->invoke( $object );

		$this->assertEquals( 'https://8.8.8.8/post-gateway/', $provider->getEndpoint() );
	}


	public function testGuard()
	{
		$this->assertEquals( ['timeout' => 10], $this->callGuard( 'https://8.8.8.8:8443/api', '', ['timeout' => 10] ) );
	}


	public static function guardProxyProvider() : array
	{
		return [
			[['proxy' => 'http://proxy.local:3128']],
			[['proxy' => ['https' => 'http://proxy.local:3128']]],
			[['proxy' => ['https' => 'http://proxy.local:3128', 'no' => ['other.example.com']]]],
		];
	}


	#[\PHPUnit\Framework\Attributes\DataProvider( 'guardProxyProvider' )]
	public function testGuardProxy( array $options )
	{
		$this->assertEquals( $options, $this->callGuard( 'https://pay.example.com/api', '', $options ) );
	}


	public static function guardHostProvider() : array
	{
		return [
			['8.8.8.8', 'pay.example.com:443:8.8.8.8'],
			['2001:4860:4860::8888', 'pay.example.com:443:[2001:4860:4860::8888]'],
		];
	}


	#[\PHPUnit\Framework\Attributes\DataProvider( 'guardHostProvider' )]
	public function testGuardHost( string $ip, string $expected )
	{
		$result = $this->callGuard( 'https://pay.example.com/api', $ip );
		$this->assertEquals( [$expected], $result['curl'][CURLOPT_RESOLVE] );
	}


	public static function guardInvalidProvider() : array
	{
		return [
			['gopher://127.0.0.1:6379/_FLUSHALL'],
			['http://8.8.8.8/'],
			['https://10.0.0.1/'],
			['https://100.64.0.1/'],
			['https://[::1]/'],
			['https://[::ffff:127.0.0.1]/'],
			['https://[64:ff9b::a00:1]/'],
			['https://[fd00::1]/'],
			['https://[fe80::1]/'],
			['https://localhost/', '127.0.0.1'],
			['https://pay.example.com/', '10.0.0.1'],
			['https://unknown.invalid/', ''],
			['http://pay.example.com/', '8.8.8.8', ['proxy' => 'http://proxy.local:3128']],
			['https://10.0.0.1/', '', ['proxy' => 'http://proxy.local:3128']],
			['https://pay.example.com/', '10.0.0.1', ['proxy' => ['https' => 'http://proxy.local:3128', 'no' => ['pay.example.com']]]],
			['https://pay.example.com/', '10.0.0.1', ['proxy' => ['http' => 'http://proxy.local:3128']]],
		];
	}


	#[\PHPUnit\Framework\Attributes\DataProvider( 'guardInvalidProvider' )]
	public function testGuardInvalid( string $url, string $ip = '', array $options = [] )
	{
		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->expectExceptionMessage( 'Only HTTPS URLs of public hosts are allowed' );

		$this->callGuard( $url, $ip, $options );
	}


	public function testResolve()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\OmniPay( $this->context, $this->serviceItem );
		$method = new \ReflectionMethod( $object, 'resolve' );

		$this->assertEquals( '127.0.0.1', $method->invoke( $object, 'localhost' ) );
		$this->assertEquals( '', $method->invoke( $object, 'unknown.invalid' ) );
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

		$result = $this->object->process( $this->getOrder(), [] );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOnsiteNoAddress()
	{
		$provider = $this->createMock( \Omnipay\Common\GatewayInterface::class );
		$this->object->expects( $this->any() )->method( 'getProvider' )->willReturn( $provider );

		$conf = array(
				'type' => 'Dummy',
				'onsite' => '1',
		);
		$this->serviceItem->setConfig( $conf );

		$result = $this->object->process( $this->getOrder(), [] );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testProcessOffsitePurchaseSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => '1' ) );

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
			->willReturn( $provider );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );

		$params = array(
			'number' => '4444333322221111',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$this->expectException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->process( $this->getOrder(), $params );
	}


	public function testProcessOffsiteAuthorizeSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1', 'onsite' => 1 ) );

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
			->onlyMethods( array( 'authorize' ) )
			->disableOriginalConstructor()
			->getMock();

		$provider->expects( $this->once() )->method( 'authorize' )
			->will( $this->throwException( new \RuntimeException() ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );

		$this->expectException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->process( $this->getOrder(), [] );
	}


	public function testProcessOffsiteRedirect()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\ResponseRedirectTest' )
			->onlyMethods( array( 'getTransactionReference' ) )
			->disableOriginalConstructor()
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->object->expects( $this->once() )->method( 'setOrderData' );

		$provider->expects( $this->once() )->method( 'purchase' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '1234' );


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
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'authorize' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->object->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '123' );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncNone()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncPurchaseSucessful()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionId', 'isSuccessful', 'getTransactionReference' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '' );


		$result = $this->object->updateSync( $psr7request, $order->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED ) );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED, $result->getStatusPayment() );
	}


	public function testUpdateSyncPurchaseQuery()
	{
		$order = $this->getOrder()->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED );

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionId', 'isSuccessful', 'getTransactionReference' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )->willReturn( $provider );
		$this->object->expects( $this->once() )->method( 'isImplemented' )->willReturn( true );
		$this->object->expects( $this->once() )->method( 'query' )->willReturnArgument( 0 );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )->willReturn( true );
		$provider->expects( $this->once() )->method( 'completePurchase' )->willReturn( $request );
		$request->expects( $this->once() )->method( 'send' )->willReturn( $response );
		$response->expects( $this->once() )->method( 'getTransactionId' )->willReturn( $order->getId() );
		$response->expects( $this->once() )->method( 'isSuccessful' )->willReturn( true );


		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED, $result->getStatusPayment() );
	}


	public function testUpdateSyncTransactionMismatch()
	{
		$order = $this->getOrder()->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED );

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionId', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )->willReturn( true );
		$provider->expects( $this->once() )->method( 'completePurchase' )->willReturn( $request );
		$request->expects( $this->once() )->method( 'send' )->willReturn( $response );
		$response->expects( $this->once() )->method( 'getTransactionId' )->willReturn( '-1' );
		$response->expects( $this->never() )->method( 'isSuccessful' );


		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED, $result->getStatusPayment() );
	}


	public function testUpdatePushQuery()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( OmniPayGatewayWithNotification::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'save'] )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId()] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getProvider' )->willReturn( $provider );
		$this->object->expects( $this->once() )->method( 'isImplemented' )->willReturn( true );
		$this->object->expects( $this->once() )->method( 'query' )
			->willReturnCallback( fn( $item ) => $item->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED ) );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )->willReturn( true );
		$provider->expects( $this->never() )->method( 'acceptNotification' );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'save' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushOtherService()
	{
		$order = $this->getOrder();
		$this->serviceItem->setCode( 'unitdeliverycode' );

		$provider = $this->getMockBuilder( OmniPayGatewayWithNotification::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'save'] )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId()] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 500 ), $this->equalTo( '' ) );


		$this->object->expects( $this->once() )->method( 'getProvider' )->willReturn( $provider );
		$this->object->expects( $this->never() )->method( 'save' );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )->willReturn( true );
		$provider->expects( $this->never() )->method( 'acceptNotification' );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushSuccess()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( OmniPayGatewayWithNotification::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'save'] )
			->getMock();

		$request = $this->getMockBuilder( OmniPayRequestWithStatus::class )
			->onlyMethods( ['getData', 'sendData', 'getMessage', 'getTransactionReference', 'getTransactionStatus'] )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId()] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '123' );

		$request->expects( $this->once() )->method( 'getTransactionStatus' )
			->willReturn( \Omnipay\Common\Message\NotificationInterface::STATUS_COMPLETED );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'save' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushPending()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( OmniPayGatewayWithNotification::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'save'] )
			->getMock();

		$request = $this->getMockBuilder( OmniPayRequestWithStatus::class )
			->onlyMethods( ['getData', 'sendData', 'getMessage', 'getTransactionReference', 'getTransactionStatus'] )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId()] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '123' );

		$request->expects( $this->once() )->method( 'getTransactionStatus' )
			->willReturn( \Omnipay\Common\Message\NotificationInterface::STATUS_PENDING );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_PENDING;
		};

		$this->object->expects( $this->once() )->method( 'save' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdatePushRefused()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( OmniPayGatewayWithNotification::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'save'] )
			->getMock();

		$request = $this->getMockBuilder( OmniPayRequestWithStatus::class )
			->onlyMethods( ['getData', 'sendData', 'getMessage', 'getTransactionReference', 'getTransactionStatus'] )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		$psr7request->expects( $this->once() )->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId()] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 200 ) );


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsAcceptNotification' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'acceptNotification' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'getTransactionStatus' )
			->willReturn( \Omnipay\Common\Message\NotificationInterface::STATUS_FAILED );

		$request->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '123' );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
		};

		$this->object->expects( $this->once() )->method( 'save' )->with( $this->callback( $cmpFcn ) );


		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testUpdateSyncAuthorizeFailed()
	{
		$order = $this->getOrder()->setStatusPayment( -1 );
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompleteAuthorize', 'completeAuthorize' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionId', 'isSuccessful', 'getTransactionReference' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->willReturn( [] );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->object->expects( $this->once() )->method( 'save' );

		$provider->expects( $this->once() )->method( 'supportsCompleteAuthorize' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'completeAuthorize' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( false );


		$this->expectException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->updateSync( $psr7request, $order );
	}


	public function testUpdateSyncRedirect()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\ResponseRedirectTest' )
			->onlyMethods( array( 'getTransactionId', 'getTransactionReference', 'isRedirect' ) )
			->disableOriginalConstructor()
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();


		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->willReturn( [] );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isRedirect' )
			->willReturn( true );


		$this->expectException( '\\Aimeos\\MShop\\Service\\Exception' );
		$this->object->updateSync( $psr7request, $order );
	}


	public function testCancel()
	{
		$orderItem = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsVoid', 'void' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'void' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );


		$this->object->cancel( $orderItem );
	}


	public function testCancelNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsVoid' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->willReturn( false );


		$this->object->cancel( $orderItem );
	}


	public function testCapture()
	{
		$orderItem = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCapture', 'capture' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'capture' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );


		$this->object->capture( $orderItem );
	}


	public function testCaptureNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsCapture' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
		->willReturn( false );


		$this->object->capture( $orderItem );
	}


	public function testRefund()
	{
		$orderItem = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsRefund', 'refund' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->willReturn( true );

		$provider->expects( $this->once() )->method( 'refund' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );


		$this->object->refund( $orderItem );
	}


	public function testRefundNotSupported()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'supportsRefund' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->willReturn( false );


		$this->object->refund( $orderItem );
	}


	public function testRepay()
	{
		$orderItem = $this->getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( ['purchase'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'isSuccessful', 'getTransactionReference' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'isImplemented' )
			->willReturn( true );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$this->object->expects( $this->once() )->method( 'data' )
			->willReturn( ['token' => '123', 'month' => '01', 'year' => '99'] );

		$provider->expects( $this->once() )->method( 'purchase' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '' );

		$this->object->expects( $this->once() )->method( 'setOrderData' );


		$this->object->repay( $orderItem );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order', 'order/address', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}


	protected function callGuard( string $url, string $ip, array $options = [] ) : array
	{
		$object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\OmniPay::class )
			->setConstructorArgs( [$this->context, $this->serviceItem] )
			->onlyMethods( ['resolve'] )
			->getMock();

		$object->method( 'resolve' )->willReturn( $ip );

		return ( new \ReflectionMethod( $object, 'guard' ) )->invoke( $object, new \GuzzleHttp\Psr7\Request( 'GET', $url ), $options );
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

if( class_exists( 'Omnipay\Dummy\Gateway' ) )
{
	abstract class OmniPayGatewayWithNotification extends \Omnipay\Dummy\Gateway
	{
		abstract public function acceptNotification();
		abstract public function save();
	}
}

if( class_exists( 'Omnipay\Common\Message\AbstractRequest' ) )
{
	abstract class OmniPayRequestWithStatus extends \Omnipay\Common\Message\AbstractRequest
		implements \Omnipay\Common\Message\NotificationInterface
	{
		abstract public function getTransactionStatus();
	}
}
