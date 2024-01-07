<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2024
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

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => 1 ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->ordServItem = \Aimeos\MShop::create( $this->context, 'order/service' )->create();
		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\NovalnetCredit' )
			->onlyMethods( ['save', 'getProvider', 'getValue', 'saveRepayData'] )
			->setConstructorArgs( array( $this->context, $serviceItem ) )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
	}


	public function testGetConfigFE()
	{
		$status = \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED;
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( [
			'order.channel' => 'web',
			'order.statuspayment' => $status
		] );

		$item = $manager->search( $search, ['order', 'order/address'] )
			->first( new \RuntimeException( sprintf( 'No order found with status "%1$s" and channel "%2$s"', $status, 'web' ) ) );

		$config = $this->object->getConfigFE( $item );

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
		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Service\\Attribute\\Iface', $attrItem );
		$this->assertEquals( '4111111111111111', $attrItem->getValue() );
	}


	public function testProcess()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionReference', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

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

		$result = $this->access( 'getCardDetails' )->invokeArgs( $this->object, [$this->getOrder(), []] );
		$this->assertInstanceOf( \Omnipay\Common\CreditCard::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( ['order.datepayment' => '2008-02-15 12:34:56'] );

		return $manager->search( $search, ['order', 'order/product', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\NovalnetCredit::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
