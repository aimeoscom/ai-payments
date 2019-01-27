<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class StripeTest extends \PHPUnit\Framework\TestCase
{
	private $context;
	private $object;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();
		$item = \Aimeos\MShop::create( $this->context, 'service' )->createItem()->setConfig( ['stripe.testmode' => true] );

		$this->object = new Stripe( $this->context, $item );
	}


	protected function tearDown()
	{
		unset( $this->object, $this->context );
	}


	public function testGetConfigBE()
	{
		$result = $this->object->getConfigBE();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'stripe.address', $result );
		$this->assertArrayHasKey( 'stripe.authorize', $result );
		$this->assertArrayHasKey( 'stripe.testmode', $result );
		$this->assertArrayHasKey( 'stripe.createtoken', $result );
		$this->assertArrayNotHasKey( 'stripe.type', $result );
		$this->assertArrayNotHasKey( 'omnipay.type', $result );
	}


	public function testCheckConfigBE()
	{
		$attributes = array(
			'stripe.address' => '0',
			'stripe.authorize' => '1',
			'stripe.testmode' => '1',
		);

		$result = $this->object->checkConfigBE( $attributes );

		$this->assertEquals( 4, count( $result ) );
		$this->assertEquals( null, $result['stripe.address'] );
		$this->assertEquals( null, $result['stripe.authorize'] );
		$this->assertEquals( null, $result['stripe.createtoken'] );
		$this->assertEquals( null, $result['stripe.testmode'] );
		$this->assertArrayNotHasKey( 'stripe.type', $result );
		$this->assertArrayNotHasKey( 'omnipay.type', $result );
	}


	public function testGetProvider()
	{
		$result = $this->access( 'getProvider' )->invokeArgs( $this->object, [] );
		$this->assertInstanceOf( \Omnipay\Common\GatewayInterface::class, $result );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->access( 'getValue' )->invokeArgs( $this->object, ['testmode'] ) );
	}


	public function testCheckConfigFE()
	{
		$this->assertEquals( [], $this->object->checkConfigFE( [] ) );
	}


	public function testGetConfigFE()
	{
		$basket = \Aimeos\MShop::create( $this->context, 'order/base' )->createItem();
		$this->assertEquals( [], $this->object->getConfigFE( $basket ) );
	}


	public function testProcess()
	{
		$iface = \Aimeos\MShop\Common\Helper\Form\Iface::class;
		$order = \Aimeos\MShop::create( $this->context, 'order' )->createItem();

		$this->assertInstanceOf( $iface, $this->object->process( $order ) );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\Stripe::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}
}
