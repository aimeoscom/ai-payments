<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015
 */


class MShop_Service_Provider_Payment_OmniPayTest extends PHPUnit_Framework_TestCase
{
	private $_object;
	private $_context;
	private $_serviceItem;


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
		$this->_serviceItem = $serviceManager->createItem();
		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy' ) );

		$this->_object = $this->getMockBuilder( 'MShop_Service_Provider_Payment_OmniPay' )
			->setMethods( array( '_getOrder', '_getOrderBase', '_saveOrder', '_saveOrderBase', '_getProvider' ) )
			->setConstructorArgs( array( $this->_context, $this->_serviceItem ) )
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
		unset( $this->_object );
		unset( $this->_context );
		unset( $this->_serviceItem );
	}


	public function testGetConfigBE()
	{
		$object = new MShop_Service_Provider_Payment_OmniPay( $this->_context, $this->_serviceItem );

		$result = $object->getConfigBE();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'omnipay.type', $result );
		$this->assertArrayHasKey( 'omnipay.address', $result );
		$this->assertArrayHasKey( 'omnipay.authorize', $result );
		$this->assertArrayHasKey( 'omnipay.onsite', $result );
		$this->assertArrayHasKey( 'omnipay.testmode', $result );
	}


	public function testCheckConfigBE()
	{
		$object = new MShop_Service_Provider_Payment_OmniPay( $this->_context, $this->_serviceItem );

		$attributes = array(
			'omnipay.type' => 'manual',
			'omnipay.address' => '0',
			'omnipay.authorize' => '1',
			'omnipay.onsite' => '0',
			'omnipay.testmode' => '1',
			'payment.url-cancel' => 'http://cancelUrl',
			'payment.url-success' => 'http://returnUrl'
		);

		$result = $object->checkConfigBE( $attributes );

		$this->assertEquals( 9, count( $result ) );
		$this->assertEquals( null, $result['omnipay.type'] );
		$this->assertEquals( null, $result['omnipay.address'] );
		$this->assertEquals( null, $result['omnipay.authorize'] );
		$this->assertEquals( null, $result['omnipay.onsite'] );
		$this->assertEquals( null, $result['omnipay.testmode'] );
		$this->assertEquals( null, $result['payment.url-cancel'] );
		$this->assertEquals( null, $result['payment.url-success'] );
	}


	public function testIsImplemented()
	{
		$object = new MShop_Service_Provider_Payment_OmniPay( $this->_context, $this->_serviceItem );

		$this->assertFalse( $object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_CANCEL ) );
		$this->assertFalse( $object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_CAPTURE ) );
		$this->assertFalse( $object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_QUERY ) );
		$this->assertFalse( $object->isImplemented( MShop_Service_Provider_Payment_Abstract::FEAT_REFUND ) );
	}


	public function testProcessOnsiteAddress()
	{
		$conf = array(
			'omnipay.type' => 'Dummy',
			'omnipay.onsite' => '1',
			'omnipay.address' => '1',
		);
		$this->_serviceItem->setConfig( $conf );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $this->_getOrderBase() ) );

		$result = $this->_object->process( $this->_getOrder(), array() );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	public function testProcessOnsiteNoAddress()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Gateway\Manual' )->setMethods( null )->getMock();
		$this->_object->expects( $this->any() )->method( '_getProvider' )->will( $this->returnValue( $provider ) );

		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_NONE );

		$conf = array(
				'omnipay.type' => 'Dummy',
				'omnipay.onsite' => '1',
		);
		$this->_serviceItem->setConfig( $conf );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$result = $this->_object->process( $this->_getOrder(), array() );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	public function testProcessOffsitePurchaseSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy' ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);
		$result = $this->_object->process( $this->_getOrder(), $params );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	public function testProcessOffsitePurchaseFailure()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy' ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
		->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4444333322221111',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$this->setExpectedException( 'MShop_Service_Exception' );
		$this->_object->process( $this->_getOrder(), $params );
	}


	public function testProcessOffsiteAuthorizeSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy', 'omnipay.authorize' => '1' ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$result = $this->_object->process( $this->_getOrder(), $params );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
	}


	public function testProcessOffsiteAuthorizeFailure()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy', 'omnipay.authorize' => '1' ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->setExpectedException( 'MShop_Service_Exception' );
		$this->_object->process( $this->_getOrder(), array() );
	}


	public function testProcessOffsiteRedirect()
	{
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'authorize' ) )
			->getMock();

		$request = $this->getMockBuilder( '\Omnipay\Dummy\Message\AuthorizeRequest' )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'ResponseRedirectTest' )
			->disableOriginalConstructor()
			->setMethods( null )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'authorize' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );


		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);

		$result = $this->_object->process( $this->_getOrder(), $params );

		$this->assertInstanceOf( 'MShop_Common_Item_Helper_Form_Interface', $result );
		$this->assertEquals( 'url', $result->getUrl() );
	}


	public function testUpdateSync()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'authorize' ) )
			->getMock();

		$this->_object->expects( $this->once() )->method( '_getOrder' )
			->will( $this->returnValue( $orderItem ) );

		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );


		$result = $this->_object->updateSync( array( 'orderid' => '1' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncNone()
	{
		$result = $this->_object->updateSync( array() );

		$this->assertEquals( null, $result );
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


		$result = $this->_object->updateSync( array( 'orderid' => '1' ) );

		$this->assertInstanceOf( 'MShop_Order_Item_Interface', $result );
	}


	public function testUpdateSyncAuthorizeFailed()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );

		$this->_serviceItem->setConfig( array( 'omnipay.type' => 'Dummy', 'omnipay.authorize' => '1' ) );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompleteAuthorize', 'completeAuthorize' ) )
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

		$provider->expects( $this->once() )->method( 'supportsCompleteAuthorize' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'completeAuthorize' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( false ) );


		$this->setExpectedException( 'MShop_Service_Exception' );
		$this->_object->updateSync( array( 'orderid' => '1' ) );
	}


	public function testUpdateSyncRedirect()
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

		$response = $this->getMockBuilder( 'ResponseRedirectTest' )
			->disableOriginalConstructor()
			->setMethods( array( 'isRedirect' ) )
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

		$response->expects( $this->once() )->method( 'isRedirect' )
			->will( $this->returnValue( true ) );


		$this->setExpectedException( 'MShop_Service_Exception' );
		$this->_object->updateSync( array( 'orderid' => '1' ) );
	}


	public function testCancel()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsVoid', 'void' ) )
			->getMock();

		$request = $this->getMockBuilder( '\Omnipay\Dummy\Message\AuthorizeRequest' )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'void' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->_object->cancel( $orderItem );
	}


	public function testCancelNotSupported()
	{
		$orderItem = $this->_getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsVoid' ) )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsVoid' )
			->will( $this->returnValue( false ) );


		$this->_object->cancel( $orderItem );
	}


	public function testCapture()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCapture', 'capture' ) )
			->getMock();

		$request = $this->getMockBuilder( '\Omnipay\Dummy\Message\AuthorizeRequest' )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'capture' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->_object->capture( $orderItem );
	}


	public function testCaptureNotSupported()
	{
		$orderItem = $this->_getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCapture' ) )
			->getMock();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsCapture' )
		->will( $this->returnValue( false ) );


		$this->_object->capture( $orderItem );
	}


	public function testRefund()
	{
		$orderItem = $this->_getOrder();
		$baseItem = $this->_getOrderBase( MShop_Order_Manager_Base_Abstract::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsRefund', 'refund' ) )
			->getMock();

		$request = $this->getMockBuilder( '\Omnipay\Dummy\Message\AuthorizeRequest' )
			->disableOriginalConstructor()
			->setMethods( array( 'send' ) )
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->disableOriginalConstructor()
			->setMethods( array( 'isSuccessful' ) )
			->getMock();


		$this->_object->expects( $this->once() )->method( '_getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->will( $this->returnValue( true ) );

		$provider->expects( $this->once() )->method( 'refund' )
			->will( $this->returnValue( $request ) );

		$request->expects( $this->once() )->method( 'send' )
			->will( $this->returnValue( $response ) );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->will( $this->returnValue( true ) );


		$this->_object->refund( $orderItem );
	}


	public function testRefundNotSupported()
	{
		$orderItem = $this->_getOrder();


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsRefund' ) )
			->getMock();

		$this->_object->expects( $this->once() )->method( '_getProvider' )
			->will( $this->returnValue( $provider ) );

		$provider->expects( $this->once() )->method( 'supportsRefund' )
			->will( $this->returnValue( false ) );


		$this->_object->refund( $orderItem );
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


if( class_exists( 'Omnipay\Dummy\Message\Response' )
	&& interface_exists( 'Omnipay\Common\Message\RedirectResponseInterface' ) )
{
	class ResponseRedirectTest
		extends \Omnipay\Dummy\Message\Response
		implements \Omnipay\Common\Message\RedirectResponseInterface
	{
		public function isRedirect()
		{
			return true;
		}

		public function getRedirectUrl()
		{
			return 'url';
		}

		public function getRedirectMethod()
		{
			return 'POST';
		}

		public function getRedirectData()
		{
			return array( 'key' => 'value' );
		}
	}
}
