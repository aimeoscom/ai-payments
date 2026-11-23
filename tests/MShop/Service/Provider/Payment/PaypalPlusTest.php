<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2026
 */


namespace Aimeos\MShop\Service\Provider\Payment;


#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PaypalPlusTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setConfig( ['type' => 'Dummy'] )->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\PaypalPlus::class )
			->setConstructorArgs( [$this->context, $serviceItem] )
			->onlyMethods( ['getProvider', 'save'] )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->context );
	}


	public function testUpdateSyncCancel()
	{
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7request->method( 'getQueryParams' )->willReturn( [] );

		$this->object->expects( $this->never() )->method( 'getProvider' );

		$order = \Aimeos\MShop::create( $this->context, 'order' )->create()
			->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED );

		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_CANCELED, $result->getStatusPayment() );
	}


	public function testUpdateSyncKeepStatus()
	{
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7request->method( 'getQueryParams' )->willReturn( ['PayerID' => '123'] );

		$this->object->expects( $this->never() )->method( 'getProvider' );

		$order = \Aimeos\MShop::create( $this->context, 'order' )->create()
			->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED );

		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED, $result->getStatusPayment() );
	}


	public function testUpdateSyncKeepStatusNoPayer()
	{
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7request->method( 'getQueryParams' )->willReturn( [] );

		$order = \Aimeos\MShop::create( $this->context, 'order' )->create()
			->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_PENDING );

		$result = $this->object->updateSync( $psr7request, $order );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_PENDING, $result->getStatusPayment() );
	}
}
