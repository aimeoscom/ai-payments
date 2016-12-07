<?php

namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2016
 */
class StripeTest extends \PHPUnit_Framework_TestCase
{
	private $object;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager( $context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'stripe.testmode' => true ) );

		$this->object = new StripePublic( $context, $item );
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		unset( $this->object );
	}


	public function testGetConfigBE()
	{
		$result = $this->object->getConfigBE();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'stripe.address', $result );
		$this->assertArrayHasKey( 'stripe.authorize', $result );
		$this->assertArrayHasKey( 'stripe.testmode', $result );
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

		$this->assertEquals( 7, count( $result ) );
		$this->assertEquals( null, $result['stripe.address'] );
		$this->assertEquals( null, $result['stripe.authorize'] );
		$this->assertEquals( null, $result['stripe.testmode'] );
		$this->assertArrayNotHasKey( 'stripe.type', $result );
		$this->assertArrayNotHasKey( 'omnipay.type', $result );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'Stripe', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->object->getValuePublic( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}
}


class StripePublic extends \Aimeos\MShop\Service\Provider\Payment\Stripe
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}