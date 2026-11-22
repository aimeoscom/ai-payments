<?php

namespace Aimeos\MShop\Service\Provider\Payment;


#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PayoneTest extends \PHPUnit\Framework\TestCase
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

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Payone::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->onlyMethods( ['getProvider', 'save', 'updateSync', 'saveRepayData'] )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testProcessOffsitePurchaseSuccess()
	{
		if( !class_exists( 'Omnipay\Payone\Extend\Item' ) ) {
			$this->markTestSkipped( 'Omnipay Payone library not available' );
		}

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


	public function testUpdatePush()
	{
		$psr7stream = $this->getMockBuilder( \Psr\Http\Message\StreamInterface::class )->getMock();
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Aimeos\Base\View\Helper\Response\Iface::class )->getMock();

		$psr7request->expects( $this->exactly( 2 ) )->method( 'getAttributes' )
			->willReturn( ['reference' => 1] );

		$psr7request->expects( $this->once() )->method( 'withAttribute' )
			->willReturn( $psr7request );

		$psr7response->expects( $this->once() )->method( 'getStatusCode' )
			->willReturn( 200 );

		$psr7response->expects( $this->once() )->method( 'withBody' )
			->willReturn( $psr7response );

		$psr7response->expects( $this->once() )->method( 'createStreamFromString' )
			->willReturn( $psr7stream );

		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	public function testNotification()
	{
		$this->serviceItem->setConfig( ['type' => 'Payone_ShopServer', 'portalKey' => 'secret'] );

		$this->assertInstanceOf( \Omnipay\Common\Message\NotificationInterface::class, $this->notification( md5( 'secret' ) ) );
		$this->assertInstanceOf( \Omnipay\Common\Message\NotificationInterface::class, $this->notification( hash( 'sha384', 'secret' ) ) );
	}


	public function testNotificationInvalidKey()
	{
		$this->serviceItem->setConfig( ['type' => 'Payone_ShopServer', 'portalKey' => 'secret'] );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->notification( md5( 'invalid' ) );
	}


	public function testNotificationNoPortalKey()
	{
		$this->serviceItem->setConfig( ['type' => 'Payone_ShopServer'] );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->notification( md5( '' ) );
	}


	public function testVerified()
	{
		$method = new \ReflectionMethod( $this->object, 'verified' );

		$response = $this->getMockBuilder( \Omnipay\Payone\Message\ShopClientCompleteAuthorizeResponse::class )
			->disableOriginalConstructor()
			->getMock();

		$this->assertFalse( $method->invoke( $this->object, $response ) );

		$response = $this->getMockBuilder( \Omnipay\Payone\Message\ShopServerAuthorizeResponse::class )
			->disableOriginalConstructor()
			->getMock();

		$this->assertTrue( $method->invoke( $this->object, $response ) );
	}


	protected function notification( string $key ) : \Omnipay\Common\Message\NotificationInterface
	{
		$provider = $this->getMockBuilder( \Omnipay\Payone\ShopServerGateway::class )
			->onlyMethods( ['acceptNotification'] )
			->getMock();

		$notification = $this->getMockBuilder( \Omnipay\Payone\Message\ShopTransactionStatusServerRequest::class )
			->disableOriginalConstructor()
			->onlyMethods( ['getKey'] )
			->getMock();

		$provider->method( 'acceptNotification' )->willReturn( $notification );
		$notification->method( 'getKey' )->willReturn( $key );

		return ( new \ReflectionMethod( $this->object, 'notification' ) )->invoke( $this->object, $provider );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order', 'order/address', 'order/product', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}
}
