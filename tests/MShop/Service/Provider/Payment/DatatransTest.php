<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2022
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
			'getCustomerData', 'getOrder', 'getOrderBase', 'getTransactionReference', 'isImplemented',
			'saveOrder', 'saveOrderBase', 'getProvider', 'getXmlProvider', 'setOrderData', 'setCustomerData'
		];

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->setMethods( $methods )
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


	public function testIsImplemented()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\Datatrans( $this->context, $this->serviceItem );

		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY ) );
		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REPAY ) );
	}


	public function testQuerySuccess()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( ['getProvider', 'getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( ['send'] )
			->getMock();

		$response = $this->getMockBuilder( DatatransResponseWithCode::class )
			->disableOriginalConstructor()
			->onlyMethods( ['isSuccessful', 'getTransactionReference', 'getResponseCode', 'getCurrencyCode', 'getAmountMinorUnits'] )
			->getMock();

		$order = $this->getOrder();
		$base = $this->getOrderBase();

		$this->object->expects( $this->once() )->method( 'getOrderBase' )->willReturn( $base );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will($this->returnValue($provider));

		$provider->expects( $this->once() )->method( 'getTransaction' )
			->will($this->returnValue($request));

		$request->expects( $this->once() )->method( 'send' )
			->will($this->returnValue($response));

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will($this->returnValue(true));

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will($this->returnValue(''));

		$response->method( 'getCurrencyCode' )->willReturn( $base->getPrice()->getCurrencyId() );
		$response->method( 'getAmountMinorUnits' )->willReturn( $this->amount( $base ) );
		$response->method( 'getResponseCode' )->willReturn( 2 );

		$this->object->expects( $this->once() )->method( 'setOrderData' );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query( $order );
	}


	public function testQueryAmountMismatch()
	{
		$order = $this->getOrder();
		$base = $this->getOrderBase();
		$this->mismatch( $base, $base->getPrice()->getCurrencyId(), $this->amount( $base ) - 1 );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->query( $order );
	}


	public function testQueryCurrencyMismatch()
	{
		$order = $this->getOrder();
		$base = $this->getOrderBase();
		$this->mismatch( $base, 'XXX', $this->amount( $base ) );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->query( $order );
	}


	public function testQueryAuthorizeFailure()
	{
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );

		$provider = $this->getMockBuilder( \Omnipay\Dummy\Gateway::class )
			->setMethods( array( 'supportsCompleteAuthorize', 'completeAuthorize','getTransaction' ) )
			->disableOriginalConstructor()
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( ['send'] )
			->getMock();

		$response = $this->getMockBuilder( DatatransResponseWithCode::class )
			->disableOriginalConstructor()
			->onlyMethods( ['isSuccessful', 'getTransactionReference', 'getResponseCode', 'getCurrencyCode', 'getAmountMinorUnits'] )
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
			->will($this->returnValue(''));

		$this->object->query( $this->getOrder());
	}


	public function testQueryPending()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( ['getProvider', 'getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( ['send'] )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( ['isPending', 'getTransactionReference'] )
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
			->will($this->returnValue(''));

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_PENDING;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query($this->getOrder());
	}


	public function testQueryCancelled()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( ['getProvider', 'getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( ['send'] )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( ['isCancelled', 'getTransactionReference'] )
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
			->will($this->returnValue(''));

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_CANCELED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query($this->getOrder());
	}


	public function testRepay()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'getCard', 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful', 'getTransactionReference' ) )
			->getMock();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getXmlProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
			->will( $this->returnValue( ['token' => '123', 'month' => '01', 'year' => '99'] ) );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will($this->returnValue(''));

		$this->object->expects( $this->once() )->method( 'setOrderData' );

		$this->object->expects( $this->once() )->method( 'saveOrder' );


		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingData()
	{
		$baseItem = $this->getOrderBase();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
			->will( $this->returnValue( null ) );


		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingToken()
	{
		$baseItem = $this->getOrderBase();


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
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


	protected function amount( \Aimeos\MShop\Order\Item\Base\Iface $base ) : int
	{
		$amount = $this->access( 'getAmount' )->invoke( $this->object, $base->getPrice(), true, true, 2 );
		return (int) str_replace( '.', '', $amount );
	}


	protected function mismatch( \Aimeos\MShop\Order\Item\Base\Iface $base, string $currency, int $amount )
	{
		$provider = $this->getMockBuilder( DatatransGatewayWithTransaction::class )
			->onlyMethods( ['getTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( DatatransResponseWithCode::class )
			->disableOriginalConstructor()
			->onlyMethods( ['isSuccessful', 'getTransactionReference', 'getResponseCode', 'getCurrencyCode', 'getAmountMinorUnits'] )
			->getMock();

		$this->object->method( 'getOrderBase' )->willReturn( $base );
		$this->object->method( 'getProvider' )->willReturn( $provider );
		$this->object->expects( $this->never() )->method( 'setOrderData' );

		$provider->method( 'getTransaction' )->willReturn( $request );
		$request->method( 'send' )->willReturn( $response );

		$response->method( 'isSuccessful' )->willReturn( true );
		$response->method( 'getResponseCode' )->willReturn( 2 );
		$response->method( 'getCurrencyCode' )->willReturn( $currency );
		$response->method( 'getAmountMinorUnits' )->willReturn( $amount );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );

		$search = $manager->filter();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		if( ( $item = $manager->search( $search )->first() ) === null ) {
			throw new \RuntimeException( 'No order found' );
		}

		return $item;
	}


	protected function getOrderBase()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order/base' );
		return $manager->load( $this->getOrder()->getBaseId(), ['order/base/service'] );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}


if( class_exists( 'Omnipay\Dummy\Gateway' ) )
{
	abstract class DatatransGatewayWithTransaction extends \Omnipay\Dummy\Gateway
	{
		abstract public function getTransaction( $options = [] );
	}

	abstract class DatatransGatewayWithCard extends \Omnipay\Dummy\Gateway
	{
		abstract public function getCard( $options = [] );
	}
}

if( class_exists( 'Omnipay\Dummy\Message\Response' ) )
{
	abstract class DatatransResponseWithCode extends \Omnipay\Dummy\Message\Response
	{
		abstract public function getResponseCode();
		abstract public function getCurrencyCode();
		abstract public function getAmountMinorUnits();
	}
}
