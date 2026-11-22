<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2026
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class Mpay24Test extends \PHPUnit\Framework\TestCase
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

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$this->serviceItem = $serviceManager->create();
		$this->serviceItem->setConfig( array( 'type' => 'Dummy' ) );
		$this->serviceItem->setCode( 'unitpaymentcode' );

		$this->object = $this->getMockBuilder( \Aimeos\MShop\Service\Provider\Payment\Mpay24::class )
			->setConstructorArgs( array( $this->context, $this->serviceItem ) )
			->onlyMethods( ['getOrder', 'getOrderBase', 'getProvider', 'saveOrder', 'setOrderData'] )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object );
		unset( $this->context );
		unset( $this->serviceItem );
	}


	public function testIsImplemented()
	{
		$object = new \Aimeos\MShop\Service\Provider\Payment\Mpay24( $this->context, $this->serviceItem );

		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_QUERY ) );
		$this->assertTrue( $object->isImplemented( \Aimeos\MShop\Service\Provider\Payment\Base::FEAT_REPAY ) );
	}


	public function testQueryBilled()
	{
		$this->object->expects( $this->once() )->method( 'setOrderData' )
			->with( $this->anything(), $this->equalTo( ['Transaction' => '123'] ) );

		$order = $this->object->query( $this->query( 'OK', 'BILLED', '123' ) );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED, $order->getStatusPayment() );
	}


	public function testQueryReserved()
	{
		$order = $this->object->query( $this->query( 'OK', 'RESERVED' ) );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_AUTHORIZED, $order->getStatusPayment() );
	}


	public function testQueryError()
	{
		$order = $this->object->query( $this->query( 'OK', 'ERROR' ) );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_REFUSED, $order->getStatusPayment() );
	}


	public function testQueryUnknown()
	{
		$this->object->expects( $this->never() )->method( 'setOrderData' );

		$order = $this->object->query( $this->query( 'OK', 'SUSPENDED' ) );

		$this->assertEquals( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED, $order->getStatusPayment() );
	}


	public function testQueryAmountMismatch()
	{
		$this->object->expects( $this->never() )->method( 'setOrderData' );

		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->query( $this->query( 'OK', 'BILLED', '123', 1 ) );
	}


	public function testQueryCurrencyMismatch()
	{
		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->query( $this->query( 'OK', 'RESERVED', '123', null, 'XXX' ) );
	}


	public function testQueryFailed()
	{
		$this->expectException( \Aimeos\MShop\Service\Exception::class );
		$this->object->query( $this->query( 'ERROR', null ) );
	}


	public function testUpdatePushQuery()
	{
		$order = $this->getOrder();

		$provider = $this->getMockBuilder( Mpay24GatewayWithTransaction::class )
			->onlyMethods( ['supportsAcceptNotification', 'acceptNotification', 'fetchTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( Mpay24TransactionResponse::class )
			->disableOriginalConstructor()
			->onlyMethods( ['getOperationStatus', 'getTransactionState', 'getTransactionReference', 'getReturnCode', 'getAmountMinorUnits', 'getCurrencyCode'] )
			->getMock();

		$psr7request = $this->getMockBuilder( \Psr\Http\Message\ServerRequestInterface::class )->getMock();
		$psr7response = $this->getMockBuilder( \Psr\Http\Message\ResponseInterface::class )->getMock();

		// forged notification claiming the order is paid
		$psr7request->method( 'getQueryParams' )->willReturn( ['orderid' => $order->getId(), 'STATUS' => 'BILLED'] );
		$psr7response->expects( $this->once() )->method( 'withStatus' )
			->willReturn( $psr7response )
			->with( $this->equalTo( 200 ) );

		$this->object->method( 'getOrder' )->willReturn( $order );
		$this->object->method( 'getProvider' )->willReturn( $provider );

		$provider->method( 'supportsAcceptNotification' )->willReturn( true );
		$provider->expects( $this->never() )->method( 'acceptNotification' );
		$provider->expects( $this->once() )->method( 'fetchTransaction' )
			->with( $this->equalTo( ['transactionId' => $order->getId()] ) )
			->willReturn( $request );

		$request->method( 'send' )->willReturn( $response );
		$response->method( 'getOperationStatus' )->willReturn( 'OK' );
		$response->method( 'getTransactionState' )->willReturn( 'ERROR' );

		$cmpFcn = function( $subject ) {
			return $subject->getStatusPayment() === \Aimeos\MShop\Order\Item\Base::PAY_REFUSED;
		};

		$this->object->expects( $this->atLeastOnce() )->method( 'saveOrder' )
			->with( $this->callback( $cmpFcn ) )->willReturnArgument( 0 );


		$this->object->updatePush( $psr7request, $psr7response );
	}


	protected function query( string $opStatus, ?string $state, ?string $txId = null, ?int $amount = null, ?string $currency = null )
	{
		$order = $this->getOrder()->setStatusPayment( \Aimeos\MShop\Order\Item\Base::PAY_UNFINISHED );
		$base = \Aimeos\MShop::create( $this->context, 'order/base' )->get( $order->getBaseId() );
		$price = $base->getPrice();

		$this->object->method( 'getOrderBase' )->willReturn( $base );

		$method = new \ReflectionMethod( $this->object, 'getAmount' );
		$method->setAccessible( true );
		$amount = $amount ?? (int) str_replace( '.', '', $method->invoke( $this->object, $price, true, true, 2 ) );

		$provider = $this->getMockBuilder( Mpay24GatewayWithTransaction::class )
			->onlyMethods( ['acceptNotification', 'fetchTransaction'] )
			->getMock();

		$request = $this->getMockBuilder( \Omnipay\Common\Message\AbstractRequest::class )
			->disableOriginalConstructor()
			->getMock();

		$response = $this->getMockBuilder( Mpay24TransactionResponse::class )
			->disableOriginalConstructor()
			->onlyMethods( ['getOperationStatus', 'getTransactionState', 'getTransactionReference', 'getReturnCode', 'getAmountMinorUnits', 'getCurrencyCode'] )
			->getMock();

		$this->object->method( 'getProvider' )->willReturn( $provider );
		$this->object->method( 'saveOrder' )->willReturnArgument( 0 );

		$provider->expects( $this->once() )->method( 'fetchTransaction' )
			->with( $this->equalTo( ['transactionId' => $order->getId()] ) )
			->willReturn( $request );

		$request->method( 'send' )->willReturn( $response );
		$response->method( 'getOperationStatus' )->willReturn( $opStatus );
		$response->method( 'getTransactionState' )->willReturn( $state );
		$response->method( 'getTransactionReference' )->willReturn( $txId );
		$response->method( 'getReturnCode' )->willReturn( 'NOT_FOUND' );
		$response->method( 'getAmountMinorUnits' )->willReturn( $amount );
		$response->method( 'getCurrencyCode' )->willReturn( $currency ?? $price->getCurrencyId() );

		return $order;
	}


	protected function getOrder()
	{
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter();
		$search->setConditions( $search->compare( '==', 'order.datepayment', '2008-02-15 12:34:56' ) );

		return $manager->search( $search )->first( new \RuntimeException( 'No order found' ) );
	}
}


if( class_exists( 'Omnipay\Dummy\Gateway' ) )
{
	abstract class Mpay24GatewayWithTransaction extends \Omnipay\Dummy\Gateway
	{
		abstract public function acceptNotification( array $options = [] );
		abstract public function fetchTransaction( array $options = [] );
	}
}

if( class_exists( 'Omnipay\Dummy\Message\Response' ) )
{
	abstract class Mpay24TransactionResponse extends \Omnipay\Dummy\Message\Response
	{
		abstract public function getOperationStatus();
		abstract public function getTransactionState();
		abstract public function getReturnCode();
		abstract public function getAmountMinorUnits();
		abstract public function getCurrencyCode();
	}
}
