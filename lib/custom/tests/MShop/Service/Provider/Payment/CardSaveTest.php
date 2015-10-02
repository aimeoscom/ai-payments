<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_CardSaveTest extends PHPUnit_Framework_TestCase
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
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = TestHelper::getContext();

		$serviceManager = MShop_Service_Manager_Factory::createManager( $this->context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'cardsave.testmode' => true ) );

		$this->object = $this->getMockBuilder( 'CardSavePublic' )
			->setMethods( array( 'getOrder', 'getOrderBase', 'saveOrder', 'saveOrderBase', 'getProvider' ) )
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
		$this->assertEquals( 'CardSave', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->object->getValuePublic( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}


	public function testUpdateSync()
	{
		$orderItem = $this->getOrder();

		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$result = $this->object->updateSync( array( 'orderid' => '1' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncPurchaseSucessful()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


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


		$this->object->expects( $this->once() )->method( 'getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCompletePurchase' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completePurchase' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$result = $this->object->updateSync( array( 'orderid' => '1', 'PaRes' => 'abc', 'MD' => '123' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncNone()
	{
		$result = $this->object->updateSync( array() );

		$this->assertEquals( null, $result );
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


class CardSavePublic extends MShop_Service_Provider_Payment_CardSave
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}