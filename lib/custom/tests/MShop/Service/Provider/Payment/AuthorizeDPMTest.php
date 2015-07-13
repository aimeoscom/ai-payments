<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_AuthorizeDpmTest extends PHPUnit_Framework_TestCase
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

		$conf = array(
			'authorizenet.address' => '1',
			'authorizenet.onsite' => '1',
			'authorizenet.testmode' => true,
		);

		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->_context );
		$item = $serviceManager->createItem();
		$item->setConfig( $conf );

		$this->_object = $this->getMockBuilder( 'AuthorizeDPMPublic' )
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
		$this->assertEquals( 'AuthorizeNet_DPM', $this->_object->getProviderType() );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->_object->getValue( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->_object->getValue( 'testmode' ) );
	}


	public function testProcessOnsiteAddress()
	{
		$this->_object->expects( $this->any() )->method( '_getOrderBase' )
			->will( $this->returnValue( $this->_getOrderBase() ) );

		$result = $this->_object->process( $this->_getOrder(), array() );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	protected function _getOrder()
	{
		$manager = MShop_Order_Manager_Factory::createManager( $this->_context );

		$search = $manager->createSearch();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		$result = $manager->searchItems( $search );

		if( ( $item = reset( $result ) ) === false ) {
			throw new Exception( 'No order found' );
		}

		return $item;
	}


	protected function _getOrderBase( $parts = null )
	{
		if( $parts === null ) {
			$parts = MShop_Order_Manager_Base_Abstract::PARTS_ADDRESS | MShop_Order_Manager_Base_Abstract::PARTS_SERVICE;
		}

		$manager = MShop_Order_Manager_Factory::createManager( $this->_context )->getSubmanager( 'base' );

		return $manager->load( $this->_getOrder()->getBaseId(), $parts );
	}
}


class AuthorizeDPMPublic extends MShop_Service_Provider_Payment_AuthorizeDPM
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