<?php

namespace Aimeos\MShop\Service\Provider\Payment;


class PayoneTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;
	private $serviceItem;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager( $this->context );
		$this->serviceItem = $serviceManager->createItem();
		$this->serviceItem->setConfig( array( 'omnipay.type' => 'Dummy' ) );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\Payone' )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->setMethods( array( 'updateSyncOrder' ) )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testUpdateSync()
	{
		$this->object->expects( $this->once() )->method( 'updateSyncOrder' )
			->will( $this->returnValue( $this->getOrder() ) );

		$result = $this->object->updateSync( array( 'reference' => '1' ) );

		$this->assertInstanceOf( '\\Aimeos\\MShop\\Order\\Item\\Iface', $result );
	}


	public function testUpdateSyncNone()
	{
		$result = $this->object->updateSync( [] );

		$this->assertEquals( null, $result );
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
}
