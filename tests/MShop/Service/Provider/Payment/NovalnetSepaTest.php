<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2025
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class NovalnetSepaTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $ordServItem;
	private $serviceItem;
	private $context;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->ordServItem = \Aimeos\MShop::create( $this->context, 'order/service' )->create();
		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\NovalnetSepa' )
			->onlyMethods( ['save', 'getProvider', 'saveRepayData'] )
			->setConstructorArgs( array( $this->context, $serviceItem ) )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
	}


	public function testGetConfigFE()
	{
		$this->assertEquals( [], $this->object->getConfigFE( $this->getOrder() ) );
	}


	public function testCheckConfigFE()
	{
		// bank account details must not be added to the basket
		$this->assertEquals( [], $this->object->checkConfigFE( ['novalnetsepa.iban' => 'DE00102030405060708090'] ) );
	}


	public function testProcessForm()
	{
		$this->object->expects( $this->never() )->method( 'getProvider' );

		$result = $this->object->process( $this->getOrder() );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
		$this->assertEquals( 'POST', $result->getMethod() );
		$this->assertFalse( $result->getExternal() );
		$this->assertEquals( 'iban', $result->getValues()['novalnetsepa.iban']->getInternalCode() );
	}


	public function testProcess()
	{
		$provider = $this->getMockBuilder( 'Omnipay\Dummy\Gateway' )
			->onlyMethods( array( 'purchase' ) )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( 'Omnipay\Dummy\Message\Response' )
			->onlyMethods( array( 'getTransactionReference', 'isSuccessful' ) )
			->disableOriginalConstructor()
			->getMock();

		$this->object->expects( $this->once() )->method( 'getProvider' )
			->willReturn( $provider );

		$provider->expects( $this->once() )->method( 'purchase' )
			->willReturn( $request );

		$request->expects( $this->once() )->method( 'send' )
			->willReturn( $response );

		$response->expects( $this->once() )->method( 'isSuccessful' )
			->willReturn( true );

		$response->expects( $this->once() )->method( 'getTransactionReference' )
			->willReturn( '' );


		$params = ['holder' => 'test user', 'iban' => 'DE00102030405060708090', 'bic' => 'ABCDEFGHIJK'];
		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order', 'order/product', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}
}
