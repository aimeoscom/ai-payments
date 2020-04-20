<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class MollieTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager( $this->context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'mollie.testmode' => true ) );
		$item->setCode( 'OGONE' );

		$this->object = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\MolliePublic' )
			->setMethods( array( 'getOrder', 'getOrderBase', 'getTransactionReference', 'saveOrder', 'saveOrderBase', 'getProvider' ) )
			->setConstructorArgs( array( $this->context, $item ) )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object, $this->context );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'Mollie', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}
	
	public function testUpdateSync()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );

		$psr7request = $this->getMockBuilder( '\Psr\Http\Message\ServerRequestInterface' )->getMock();

		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'authorize' ) )
			->getMock();

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$this->object->expects( $this->once() )->method( 'getTransactionReference' )
			->will( $this->returnValue( '123' ) );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncNone()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->setMethods( array( 'supportsCompletePurchase', 'completePurchase' ) )
			->getMock();

		$psr7request = $this->getMockBuilder( '\Psr\Http\Message\ServerRequestInterface' )->getMock();

		$this->object->expects( $this->once() )->method( 'getOrderBase' )
			->will( $this->returnValue( $baseItem ) );

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncPurchaseSucessful()
	{
		$baseItem = $this->getOrderBase( \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE );


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

		$psr7request = $this->getMockBuilder( '\Psr\Http\Message\ServerRequestInterface' )->getMock();


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


		$result = $this->object->updateSync( $psr7request, $this->getOrder() );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}

	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context );

		$search = $manager->createSearch();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		$result = $manager->searchItems( $search );

		if( ( $item = reset( $result ) ) === false ) {
			throw new \RuntimeException( 'No order found' );
		}

		return $item;
	}

	protected function getOrderBase( $parts = null )
	{
		if( $parts === null ) {
			$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		}

		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context )->getSubmanager( 'base' );

		return $manager->load( $this->getOrder()->getBaseId(), $parts );
	}
}


class MolliePublic extends \Aimeos\MShop\Service\Provider\Payment\Mollie
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}