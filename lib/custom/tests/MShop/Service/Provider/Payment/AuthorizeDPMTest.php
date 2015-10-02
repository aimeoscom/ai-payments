<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_AuthorizeDpmTest extends PHPUnit_Framework_TestCase
{
	private $object;
	private $context;


	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 *
	 * @access protected
	 */
	protected function setUp()
	{
		$this->context = TestHelper::getContext();

		$conf = array(
			'authorizenet.address' => '1',
			'authorizenet.onsite' => '1',
			'authorizenet.testmode' => true,
		);

		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->context );
		$item = $serviceManager->createItem();
		$item->setConfig( $conf );

		$this->object = $this->getMockBuilder( 'AuthorizeDPMPublic' )
			->setMethods( array( '_getOrder', '_getOrderBase', '_saveOrder', '_saveOrderBase', '_getProvider' ) )
			->setConstructorArgs( array( $this->context, $item ) )
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
		unset( $this->object, $this->context );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'AuthorizeNet_DPM', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->object->getValuePublic( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}


	public function testProcessOnsiteAddress()
	{
		$this->object->expects( $this->any() )->method( '_getOrderBase' )
			->will( $this->returnValue( $this->getOrderBase() ) );

		$result = $this->object->process( $this->getOrder(), array() );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	protected function getOrder()
	{
		$manager = MShop_Order_Manager_Factory::createManager( $this->context );

		$search = $manager->createSearch();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		$result = $manager->searchItems( $search );

		if( ( $item = reset( $result ) ) === false ) {
			throw new Exception( 'No order found' );
		}

		return $item;
	}


	protected function getOrderBase( $parts = null )
	{
		if( $parts === null ) {
			$parts = MShop_Order_Manager_Base_Abstract::PARTS_ADDRESS | MShop_Order_Manager_Base_Abstract::PARTS_SERVICE;
		}

		$manager = MShop_Order_Manager_Factory::createManager( $this->context )->getSubmanager( 'base' );

		return $manager->load( $this->getOrder()->getBaseId(), $parts );
	}
}


class AuthorizeDPMPublic extends MShop_Service_Provider_Payment_AuthorizeDPM
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}