<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class DatatransTest extends \PHPUnit\Framework\TestCase
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
			'saveOrder', 'saveOrderBase', 'getXmlProvider', 'saveTransationRef', 'setCustomerData'
		];

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->setMethods( $methods )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object, $this->serviceItem, $this->context );
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

		$this->object->expects( $this->once() )->method( 'saveTransationRef' );

		$this->object->expects( $this->once() )->method( 'saveOrder' );


		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingData()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
			->will( $this->returnValue( null ) );


		$this->setExpectedException( \Aimeos\MShop\Service\Exception::class );
		$this->object->repay( $this->getOrder() );
	}


	public function testRepayMissingToken()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getCustomerData' )
			->will( $this->returnValue( [] ) );


		$this->setExpectedException( \Aimeos\MShop\Service\Exception::class );
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


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\Datatrans::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
