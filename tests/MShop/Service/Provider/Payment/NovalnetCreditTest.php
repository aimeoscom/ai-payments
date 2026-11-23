<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2016-2026
 */


namespace Aimeos\MShop\Service\Provider\Payment;


#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class NovalnetCreditTest extends \PHPUnit\Framework\TestCase
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
		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'address' => 1 ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->ordServItem = \Aimeos\MShop::create( $this->context, 'order/service' )->create();
		$serviceItem = \Aimeos\MShop::create( $this->context, 'service' )->create();
		$serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( '\\Aimeos\\MShop\\Service\\Provider\\Payment\\NovalnetCredit' )
			->onlyMethods( ['save', 'getProvider', 'getValue', 'saveRepayData'] )
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
		// card details must not be added to the basket
		$this->assertEquals( [], $this->object->checkConfigFE( ['novalnetcredit.number' => '4111111111111111'] ) );
	}


	public function testProcessForm()
	{
		$this->object->expects( $this->never() )->method( 'getProvider' );

		$result = $this->object->process( $this->getOrder() );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
		$this->assertEquals( 'POST', $result->getMethod() );
		$this->assertFalse( $result->getExternal() );
		$this->assertEquals( 'ccnumber', $result->getValues()['novalnetcredit.number']->getInternalCode() );
		$this->assertEquals( 'cccvv', $result->getValues()['novalnetcredit.cvv']->getInternalCode() );
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


		$params = ['ccholder' => 'test user', 'ccnumber' => '4111111111111111', 'ccyear' => date( 'Y' ), 'ccmonth' => '1', 'cccvv' => '123'];
		$result = $this->object->process( $this->getOrder(), $params );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	public function testGetCardDetails()
	{
		$this->object->expects( $this->once() )->method( 'getValue' )
			->willReturn( true );

		$params = ['ccnumber' => '4111111111111111', 'novalnetcredit.cvv' => '123'];
		$result = $this->access( 'getCardDetails' )->invokeArgs( $this->object, [$this->getOrder(), $params] );

		$this->assertInstanceOf( \Omnipay\Common\CreditCard::class, $result );
		$this->assertEquals( '4111111111111111', $result->getNumber() );
		$this->assertEquals( '123', $result->getCvv() );
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( ['order.datepayment' => '2008-02-15 12:34:56'] );

		return $manager->search( $search, ['order', 'order/product', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\NovalnetCredit::class );
		$method = $class->getMethod( $name );

		return $method;
	}
}
