<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2021
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class NovalnetCreditTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $ordServItem;
	private $serviceItem;
	private $context;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::create( $this->context );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => 1 ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->ordServItem = \Aimeos\MShop::create( $this->context, 'order/base/service' )->create();
		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\NovalnetCredit' )
			->setMethods( ['getOrder', 'getOrderBase', 'saveOrder', 'saveOrderBase', 'getProvider', 'getValue', 'saveRepayData'] )
			->setConstructorArgs( array( $this->context, $serviceItem ) )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
	}


	public function testGetConfigFE()
	{
		$orderManager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context );
		$orderBaseManager = $orderManager->getSubManager( 'base' );
		$search = $orderManager->filter();
		$expr = array(
			$search->compare( '==', 'order.type', \Aimeos\MShop\Order\Item\Base::TYPE_WEB ),
			$search->compare( '==', 'order.statuspayment', \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED )
		);
		$search->setConditions( $search->and( $expr ) );

		if( ( $item = $orderManager->search( $search )->first() ) === null )
		{
			$msg = 'No Order found with statuspayment "%1$s" and type "%2$s"';
			throw new \RuntimeException( sprintf( $msg, \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED, \Aimeos\MShop\Order\Item\Base::TYPE_WEB ) );
		}

		$basket = $orderBaseManager->load( $item->getBaseId() );

		$config = $this->object->getConfigFE( $basket );

		$this->assertEquals( 'Our Unittest', $config['novalnetcredit.holder']->getDefault() );
		$this->assertArrayHasKey( 'novalnetcredit.number', $config );
		$this->assertArrayHasKey( 'novalnetcredit.year', $config );
		$this->assertArrayHasKey( 'novalnetcredit.month', $config );
		$this->assertArrayHasKey( 'novalnetcredit.cvv', $config );
	}


	public function testCheckConfigFE()
	{
		$config = array(
			'novalnetcredit.holder' => 'test user',
			'novalnetcredit.number' => '4111111111111111',
			'novalnetcredit.year' => date( 'Y' ),
			'novalnetcredit.month' => '1',
			'novalnetcredit.cvv' => '123',
		);

		$result = $this->object->checkConfigFE( $config );

		$expected = array(
			'novalnetcredit.holder' => null,
			'novalnetcredit.number' => null,
			'novalnetcredit.year' => null,
			'novalnetcredit.month' => null,
			'novalnetcredit.cvv' => null,
		);

		$this->assertEquals( $expected, $result );
	}


	public function testSetConfigFE()
	{
		$this->object->setConfigFE( $this->ordServItem, array( 'novalnetcredit.number' => '4111111111111111' ) );

		$attrItem = $this->ordServItem->getAttributeItem( 'novalnetcredit.number', 'session' );
		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Base\\Service\\Attribute\\Iface', $attrItem );
		$this->assertEquals( '4111111111111111', $attrItem->getValue() );
	}


	public function testProcess()
	{
		$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		$baseItem = $this->getOrderBase( $parts );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Dummy\Message\AuthorizeRequest::class )
			->setMethods( array( 'send' ) )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->setMethods( array( 'getTransactionReference', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

		$this->object->expects( $this->exactly( 2 ) )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'purchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->will($this->returnValue(''));


		$result = $this->object->process( $this->getOrder() );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testGetCardDetails()
	{
		$this->object->expects( $this->once() )->method( 'getValue' )
			->will( $this->returnValue( true ) );

		$result = $this->access( 'getCardDetails' )->invokeArgs( $this->object, [$this->getOrderBase(), []] );
		$this->assertInstanceOf( \Omnipay\Common\CreditCard::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context );
		$search = $manager->filter()->add( ['order.datepayment' => '2008-02-15 12:34:56'] );

		return $manager->search( $search )->first( new \RuntimeException( 'No order found' ) );
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
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\NovalnetCredit::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
