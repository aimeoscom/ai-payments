<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_SagePayTest extends PHPUnit_Framework_TestCase
{
	private $_object;
	private $_context;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$this->_context = TestHelper::getContext();

		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->_context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'sagepay.testmode' => true ) );

		$this->_object = $this->getMockBuilder( 'SagePayPublic' )
			->setMethods( array( '_getOrder', '_getOrderBase', '_saveOrder', '_saveOrderBase', '_getProvider' ) )
			->setConstructorArgs( array( $this->_context, $item ) )
			->getMock();
	}


	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 *
	 * @access protected
	 */
	protected function tearDown()
	{
		unset( $this->_object, $this->_context );
	}


	public function testGetProviderType()
	{
		$this->assertEquals( 'SagePay', $this->_object->getProviderType() );
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


class SagePayPublic extends MShop_Service_Provider_Payment_SagePay
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