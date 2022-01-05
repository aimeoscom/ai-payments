<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2021
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class StripeTest extends \PHPUnit\Framework\TestCase
{
	private $context;
	private $object;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();
		$config = ['type' => 'Stripe_PaymentIntents', 'testmode' => true];
		$item = \Aimeos\MShop::create( $this->context, 'service' )->create()->setConfig( $config )->setCode( 'unitpaymentcode' );

		$this->object = new Stripe( $this->context, $item );
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->context );
	}


	public function testGetConfigBE()
	{
		$result = $this->object->getConfigBE();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'apiKey', $result );
		$this->assertArrayHasKey( 'publishableKey', $result );
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
			'apiKey' => '123',
			'publishableKey' => 'abc',
			'address' => '0',
			'authorize' => '1',
			'testmode' => '1',
			'type' => 'Stripe',
		);

		$result = $this->object->checkConfigBE( $attributes );

		$this->assertEquals( 8, count( $result ) );
		$this->assertEquals( null, $result['apiKey'] );
		$this->assertEquals( null, $result['publishableKey'] );
		$this->assertEquals( null, $result['address'] );
		$this->assertEquals( null, $result['authorize'] );
		$this->assertEquals( null, $result['createtoken'] );
		$this->assertEquals( null, $result['testmode'] );
		$this->assertEquals( null, $result['onsite'] );
	}


	public function testGetData()
	{
		$basket = $this->getOrderBase();
		$orderId = $this->getOrder()->getId();

		$result = $this->access( 'getData' )->invokeArgs( $this->object, [$basket, $orderId, []] );
		$this->assertArrayNotHasKey( 'token', $result );
	}


	public function testGetDataToken()
	{
		$basket = $this->getOrderBase();
		$orderId = $this->getOrder()->getId();

		$result = $this->access( 'getData' )->invokeArgs( $this->object, [$basket, $orderId, ['paymenttoken' => 'abc']] );
		$this->assertArrayHasKey( 'token', $result );
	}


	public function testGetProvider()
	{
		$result = $this->access( 'getProvider' )->invokeArgs( $this->object, [] );
		$this->assertInstanceOf( \Omnipay\Common\GatewayInterface::class, $result );
	}


	public function testCheckConfigFE()
	{
		$this->assertEquals( [], $this->object->checkConfigFE( [] ) );
	}


	public function testGetConfigFE()
	{
		$basket = \Aimeos\MShop::create( $this->context, 'order/base' )->create();
		$this->assertEquals( [], $this->object->getConfigFE( $basket ) );
	}


	public function testProcess()
	{
		$iface = \Aimeos\MShop\Common\Helper\Form\Iface::class;
		$order = \Aimeos\MShop::create( $this->context, 'order' )->create();

		$this->assertInstanceOf( $iface, $this->object->process( $order ) );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context );

		$search = $manager->filter();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		if( ( $item = $manager->search( $search )->first() ) === null ) {
			throw new \RuntimeException( 'No order found' );
		}

		return $item;
	}


	protected function getOrderBase()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context )->getSubmanager( 'base' );
		return $manager->load( $this->getOrder()->getBaseId(), ['order/base/product', 'order/base/service'] );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\Stripe::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
