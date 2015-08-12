<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_AuthorizeAimTest extends PHPUnit_Framework_TestCase
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
		$item->setConfig( array( 'authorizenet.testmode' => true ) );

		$this->_object = new AuthorizeAIMPublic( $context, $item );
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


	public function testGetValueType()
	{
		$this->assertEquals( 'AuthorizeNet_AIM', $this->_object->getValue( 'type' ) );
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


class AuthorizeAIMPublic extends MShop_Service_Provider_Payment_AuthorizeAIM
{
	public function getValue( $name, $default = null )
	{
		return $this->_getValue( $name, $default );
	}
}