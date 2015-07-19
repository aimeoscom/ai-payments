<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_AuthorizeSimTest extends PHPUnit_Framework_TestCase
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
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->_context = TestHelper::getContext();

		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->_context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'authorizenet.testmode' => true ) );

		$this->_object = $this->getMockBuilder( 'AuthorizeSIMPublic' )
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
		$this->assertEquals( 'AuthorizeNet_SIM', $this->_object->getProviderType() );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->_object->getValue( 'testmode' ) );
	}


	public function testUpdateSync()
	{
		$orderItem = $this->_getOrder();

		$this->_object->expects( $this->once() )->method( '_getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$result = $this->_object->updateSync( array( 'orderid' => '1' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncPurchaseSucessful()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$request = $this->getMockBuilder( '\Omnipay\Dummy\Message\AuthorizeRequest' )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$result = $this->_object->updateSync( array( 'orderid' => '1', 'x_MD5_Hash' => 'abc' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncNone()
	{
		$result = $this->_object->updateSync( array() );

		$this->assertEquals( null, $result );
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


class AuthorizeSIMPublic extends MShop_Service_Provider_Payment_AuthorizeSIM
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