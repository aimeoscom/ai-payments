<?php

namespace Aimeos\MShop\Service\Provider\Payment;


class PayoneTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;
	private $serviceItem;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::create( $this->context );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Payone::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->setMethods( ['getOrderBase', 'getProvider', 'saveOrder', 'updateSync', 'saveRepayData'] )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testProcessOffsitePurchaseSuccess()
	{
		$provider = new \Omnipay\Dummy\Gateway();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->will( $this->returnValue( $provider ) );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => '1' ) );

		$this->object->expects( $this->exactly( 2 ) )->method( 'getOrderBase' )
			->will( $this->returnValue( $this->getOrderBase() ) );

		$params = array(
			'number' => '4929000000006',
			'expiryMonth' => '1',
			'expiryYear' => '2099',
		);
		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testUpdatePush()
	{
		$psr7stream = $this->getMockBuilder( \Psr\Http\Message\StreamInterface::class )->getMock();
		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Aimeos\Base\View\Helper\Response\Iface::class )->getMock();

		$psr7request->expects( $this->exactly( 2 ) )->method( 'getAttributes' )
			->will( $this->returnValue( ['reference' => 1] ) );

		$psr7request->expects( $this->once() )->method( 'withAttribute' )
			->will( $this->returnValue( $psr7request ) );

		$psr7response->expects( $this->once() )->method( 'getStatusCode' )
			->will( $this->returnValue( 200 ) );

		$psr7response->expects( $this->once() )->method( 'withBody' )
			->will( $this->returnValue( $psr7response ) );

		$psr7response->expects( $this->once() )->method( 'createStreamFromString' )
			->will( $this->returnValue( $psr7stream ) );

		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( \Psr\Http\Message\ResponseInterface::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context );

		$search = $manager->filter();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		if( ( $item = $manager->search( $search )->first() ) === null ) {
			throw new \RuntimeException( 'No order found' );
		}

		return $item;
	}


	protected function getOrderBase()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create( $this->context )->getSubmanager( 'base' );
		return $manager->load( $this->getOrder()->getBaseId(), ['order/base/address', 'order/base/product', 'order/base/service'] );
	}
}
