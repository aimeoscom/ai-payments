<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2023
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class DatatransTest extends \PHPUnit\Framework\TestCase
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
			'data', 'getTransactionReference', 'isImplemented',
			'save', 'getProvider', 'getXmlProvider', 'setOrderData', 'setData'
		];

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->onlyMethods( $methods )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->serviceItem, $this->context );
	}


	public function testGetConfigBE()
	{
		$result = $this->object->getConfigBE();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'password', $result );
		$this->assertArrayHasKey( 'address', $result );
		$this->assertArrayHasKey( 'authorize', $result );
		$this->assertArrayHasKey( 'testmode', $result );
		$this->assertArrayHasKey( 'createtoken', $result );
		$this->assertArrayHasKey( 'onsite', $result );
		$this->assertArrayHasKey( 'type', $result );
	}


	public function testCheckConfigBE()
	{
		$attributes = array(
			'address' => '0',
			'authorize' => '1',
			'testmode' => '1',
			'password' => 'test',
			'type' => 'Datatrans',
		);

		$result = $this->object->checkConfigBE( $attributes );

		$this->assertEquals( 7, count( $result ) );
		$this->assertEquals( null, $result['password'] );
		$this->assertEquals( null, $result['address'] );
		$this->assertEquals( null, $result['authorize'] );
		$this->assertEquals( null, $result['createtoken'] );
		$this->assertEquals( null, $result['testmode'] );
		$this->assertEquals( null, $result['onsite'] );
		$this->assertEquals( null, $result['type'] );
	}


	public function testQuerySuccess()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->addMethods( ['getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( ['isSuccessful', 'getTransactionReference'] )
			->addMethods( ['getResponseCode'] )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'getTransaction' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '' ) );

		$this->object->expects( $this->once() )->method( 'setOrderData' );

		$order = $this->object->query( $this->getOrder() );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED, $order->getStatusPayment() );
	}


	public function testQueryAuthorizeFailure()
	{
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );

		$provider = $this->getMockBuilder( \Omnipay\Dummy\Gateway::class )
			->onlyMethods( ['supportsCompleteAuthorize', 'completeAuthorize'] )
			->addMethods( ['getTransaction'] )
			->disableOriginalConstructor()
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( ['isSuccessful', 'getTransactionReference'] )
			->addMethods( ['getResponseCode'] )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'getTransaction' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( false ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '' ) );

		$this->object->query( $this->getOrder() );
	}


	public function testQueryPending()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->addMethods( ['getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( ['isPending', 'getTransactionReference'] )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'getTransaction' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isPending' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '' ) );

		$order = $this->object->query( $this->getOrder() );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_PENDING, $order->getStatusPayment() );
	}


	public function testQueryCancelled()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->addMethods( ['getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( ['isCancelled', 'getTransactionReference'] )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'getTransaction' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isCancelled' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '' ) );

		$order = $this->object->query( $this->getOrder() );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_CANCELED, $order->getStatusPayment() );
	}


	public function testRepay()
	{
		$orderItem = $this->getOrder();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( ['purchase'] )
			->addMethods( ['getCard'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->onlyMethods( array( 'isSuccessful', 'getTransactionReference' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getXmlProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'data' )
			->will( $this->returnValue( ['token' => '123', 'month' => '01', 'year' => '99'] ) );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '' ) );

		$this->object->expects( $this->once() )->method( 'setOrderData' );


		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingData()
	{
		$this->object->expects( $this->once() )->method( 'data' )
			->will( $this->returnValue( null ) );


		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingToken()
	{
		$this->object->expects( $this->once() )->method( 'data' )
			->will( $this->returnValue( [] ) );


		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->repay( $this->getOrder() );
	}


	public function testGetValue()
	{
		$this->assertEquals( 'Datatrans', $this->access( 'getValue' )->invokeArgs( $this->object, ['type'] ) );
		$this->assertEquals( null, $this->access( 'getValue' )->invokeArgs( $this->object, ['test'] ) );
	}


	public function testGetXmlProvider()
	{
		$result = $this->access( 'getXmlProvider' )->invokeArgs( $this->object, [] );
		$this->assertInstanceOf( \Omnipay\Common\GatewayInterface::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
