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


	public function testGetProviderType()
	{
		$this->assertEquals( 'Stripe', $this->_object->getProviderType() );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->_object->getValue( 'testmode' ) );
	}
}


class StripePublic extends MShop_Service_Provider_Payment_Stripe
{
	public function getProviderType()
	{
		return $this->_getProviderType();
	}

	public function getValue( $name, $default = null )
	{
		return $this->_getValue( $name, $default );
	}
}