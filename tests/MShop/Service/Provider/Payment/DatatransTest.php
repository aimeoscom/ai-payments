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


	public function testQuerySuccess()
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
			->setMethods( ['isSuccessful', 'getResponseCode', 'getTransactionReference'] )
			->getMock();

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

		$this->object->expects( $this->once() )->method( 'setOrderData' );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query( $this->getOrder() );
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

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( ['isSuccessful', 'getResponseCode', 'getTransactionReference'] )
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
