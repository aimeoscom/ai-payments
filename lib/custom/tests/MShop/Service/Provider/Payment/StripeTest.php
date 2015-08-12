<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_StripeTest extends PHPUnit_Framework_TestCase
{
	private $_object;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$context = TestHelper::getContext();

		$serviceManager = MShop_Service_Manager_Factory::createManager( $context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'stripe.testmode' => true ) );

		$this->_object = new StripePublic( $context, $item );
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		unset( $this->_object );
	}


	public function testGetConfigBE()
	{
		$result = $this->_object->getConfigBE();

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

		$result = $this->_object->checkConfigBE( $attributes );

		$this->assertEquals( 7, count( $result ) );
		$this->assertEquals( null, $result['stripe.address'] );
		$this->assertEquals( null, $result['stripe.authorize'] );
		$this->assertEquals( null, $result['stripe.testmode'] );
		$this->assertArrayNotHasKey( 'stripe.type', $result );
		$this->assertArrayNotHasKey( 'omnipay.type', $result );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'Stripe', $this->_object->getValue( 'type' ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->_object->getValue( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->_object->getValue( 'testmode' ) );
	}
}


class StripePublic extends MShop_Service_Provider_Payment_Stripe
{
	public function getValue( $name, $default = null )
	{
		return $this->_getValue( $name, $default );
	}
}