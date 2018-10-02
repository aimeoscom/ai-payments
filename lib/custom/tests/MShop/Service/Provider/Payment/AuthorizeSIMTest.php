<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class AuthorizeSimTest extends \PHPUnit\Framework\TestCase
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
		$this->serviceItem->setConfig( array( 'authorizenet.testmode' => true ) );
		$this->serviceItem->setCode( 'OGONE' );

		$this->object = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\AuthorizeSIMPublic' )
			->setMethods( array( 'getOrder', 'getOrderBase', 'saveOrder', 'saveOrderBase', 'getProvider' ) )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object, $this->context, $this->serviceItem );
	}


	public function testGetConfigBE()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\AuthorizeSIM( $this->context, $this->serviceItem );

		$result = $object->getConfigBE();

		$this->assertInternalType( 'array', $result );
		$this->assertArrayHasKey( 'authorizenet.address', $result );
		$this->assertArrayHasKey( 'authorizenet.authorize', $result );
		$this->assertArrayHasKey( 'authorizenet.testmode', $result );
		$this->assertArrayHasKey( 'payment.url-success', $result );
	}


	public function testCheckConfigBE()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\AuthorizeSIM( $this->context, $this->serviceItem );

		$attributes = array( 'payment.url-success' => 'https://localhost' );

		$result = $object->checkConfigBE( $attributes );

		$this->assertEquals( 4, count( $result ) );
		$this->assertEquals( null, $result['authorizenet.address'] );
		$this->assertEquals( null, $result['authorizenet.authorize'] );
		$this->assertEquals( null, $result['authorizenet.testmode'] );
		$this->assertEquals( null, $result['payment.url-success'] );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'AuthorizeNet_SIM', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}


	public function testUpdatePush()
	{
		$psr7stream = $this->getMockBuilder( '\Psr\Http\Message\StreamInterface' )->getMock();
		$psr7request = $this->getMockBuilder( '\Psr\Http\Message\ServerRequestInterface' )->getMock();
		$psr7response = $this->getMockBuilder( '\Aimeos\MW\View\Helper\Response\Iface' )->getMock();

		$psr7request->expects( $this->once() )->method( 'getAttributes' )
			->will( $this->returnValue( ['x_MD5_Hash' => 1] ) );

		$psr7response->expects( $this->once() )->method( 'withBody' )
			->will( $this->returnValue( $psr7response ) );

		$psr7response->expects( $this->once() )->method( 'withHeader' )
			->will( $this->returnValue( $psr7response ) );

		$psr7response->expects( $this->once() )->method( 'createStreamFromString' )
			->will( $this->returnValue( $psr7stream ) );

		$result = $this->object->updatePush( $psr7request, $psr7response );

		$this->assertInstanceOf( '\Psr\Http\Message\ResponseInterface', $result );
	}
}


class AuthorizeSIMPublic extends \Aimeos\MShop\Service\Provider\Payment\AuthorizeSIM
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}