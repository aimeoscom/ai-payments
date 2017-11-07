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
			->setMethods( array( 'updateSync' ) )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testUpdatePush()
	{
		$psr7stream = $this->getMockBuilder( '\Psr\Http\Message\StreamInterface' )->getMock();
		$psr7request = $this->getMockBuilder( '\Psr\Http\Message\ServerRequestInterface' )->getMock();
		$psr7response = $this->getMockBuilder( '\Aimeos\MW\View\Helper\Response\Iface' )->getMock();

		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->will( $this->returnValue( ['reference' => 1] ) );

		$psr7response->expects( $this->once() )->method( 'withBody' )
			->will( $this->returnValue( $psr7response ) );

		$psr7response->expects( $this->once() )->method( 'createStreamFromString' )
			->will( $this->returnValue( $psr7stream ) );

		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( '\Psr\Http\Message\ResponseInterface', $result );
	}
}
