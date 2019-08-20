<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2019
 */
namespace Aimeos\MShop\Service\Provider\Payment;

class DatatransQueryTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;
	private $serviceItem;

	protected function setUp()
	{
		if ( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager($this->context);
		$this->serviceItem = $serviceManager->createItem();
		$this->serviceItem->setConfig([ 'type' => 'Dummy' ]);
		$this->serviceItem->setCode('OGONE');

		$methods = [
			'getOrder', 'getOrderBase', 'saveTransationRef', 'saveOrder', 'getProvider', 'getTransaction'
		];

		$this->object = $this->getMockBuilder(\Aimeos\MShop\Service\Provider\Payment\Datatrans::class)
			->setConstructorArgs([ $this->context, $this->serviceItem ])
			->setMethods($methods)
			->getMock();
	}

	protected function tearDown()
	{
		unset($this->object, $this->serviceItem, $this->context);
	}


	public function testQuerySuccess()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context )->getSubmanager( 'base' );
		$baseItem = $manager->load( $this->getOrder()->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_NONE );


		$provider = $this->getMockBuilder('Omnipay\Dummy\Gateway')
			->setMethods([ 'getProvider', 'getTransaction' ])
			->getMock();

		$request = $this->getMockBuilder(\Omnipay\Dummy\Message\AuthorizeRequest::class)
			->disableOriginalConstructor()
			->setMethods([ 'send' ])
			->getMock();

		$response = $this->getMockBuilder('Omnipay\Dummy\Message\Response')
			->disableOriginalConstructor()
			->setMethods([ 'isSuccessful', 'getResponseCode' ])
			->getMock();

		$this->object->expects($this->once())->method('getOrderBase')
			->will($this->returnValue($baseItem));

		$this->object->expects($this->once())->method('getProvider')
			->will($this->returnValue($provider));

		$provider->expects($this->once())->method('getTransaction')
			->will($this->returnValue($request));

		$request->expects($this->once())->method('send')
			->will($this->returnValue($response));

		$response->expects($this->once())->method('isSuccessful')
			->will($this->returnValue(true));

		$this->object->expects($this->once())->method('saveTransationRef');

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_RECEIVED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query($this->getOrder());
	}

	public function testQueryAuthorizeFailure()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context )->getSubmanager( 'base' );
		$baseItem = $manager->load( $this->getOrder()->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_NONE );

		$this->serviceItem->setConfig( array( 'type' => 'Dummy', 'authorize' => '1' ) );

		$provider = $this->getMockBuilder( \Omnipay\Dummy\Gateway::class )
			->setMethods( array( 'supportsCompleteAuthorize', 'completeAuthorize','getTransaction' ) )
			->disableOriginalConstructor()
			->getMock();

		$request = $this->getMockBuilder(\Omnipay\Dummy\Message\AuthorizeRequest::class)
			->disableOriginalConstructor()
			->setMethods([ 'send' ])
			->getMock();

		$response = $this->getMockBuilder('Omnipay\Dummy\Message\Response')
			->disableOriginalConstructor()
			->setMethods([ 'isSuccessful', 'getResponseCode' ])
			->getMock();

		$this->object->expects($this->once())->method('getOrderBase')
			->will($this->returnValue($baseItem));

		$this->object->expects($this->once())->method('getProvider')
			->will($this->returnValue($provider));

		$provider->expects($this->once())->method('getTransaction')
			->will($this->returnValue($request));

		$request->expects($this->once())->method('send')
			->will($this->returnValue($response));

		$response->expects($this->once())->method('isSuccessful')
			->will($this->returnValue(false));

		$this->object->query( $this->getOrder());
	}

	public function testQueryPending()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context )->getSubmanager( 'base' );
		$baseItem = $manager->load( $this->getOrder()->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_NONE );


		$provider = $this->getMockBuilder('Omnipay\Dummy\Gateway')
			->setMethods([ 'getProvider', 'getTransaction' ])
			->getMock();

		$request = $this->getMockBuilder(\Omnipay\Dummy\Message\AuthorizeRequest::class)
			->disableOriginalConstructor()
			->setMethods([ 'send' ])
			->getMock();

		$response = $this->getMockBuilder('Omnipay\Dummy\Message\Response')
			->disableOriginalConstructor()
			->setMethods([ 'isPending' ])
			->getMock();

		$this->object->expects($this->once())->method('getOrderBase')
			->will($this->returnValue($baseItem));

		$this->object->expects($this->once())->method('getProvider')
			->will($this->returnValue($provider));

		$provider->expects($this->once())->method('getTransaction')
			->will($this->returnValue($request));

		$request->expects($this->once())->method('send')
			->will($this->returnValue($response));

		$response->expects($this->once())->method('isPending')
			->will($this->returnValue(true));

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_PENDING;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query($this->getOrder());
	}

	public function testQueryCancelled()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager( $this->context )->getSubmanager( 'base' );
		$baseItem = $manager->load( $this->getOrder()->getBaseId(), \Aimeos\MShop\Order\Item\Base\Base::PARTS_NONE );


		$provider = $this->getMockBuilder('Omnipay\Dummy\Gateway')
			->setMethods([ 'getProvider', 'getTransaction' ])
			->getMock();

		$request = $this->getMockBuilder(\Omnipay\Dummy\Message\AuthorizeRequest::class)
			->disableOriginalConstructor()
			->setMethods([ 'send' ])
			->getMock();

		$response = $this->getMockBuilder('Omnipay\Dummy\Message\Response')
			->disableOriginalConstructor()
			->setMethods([ 'isCancelled' ])
			->getMock();

		$this->object->expects($this->once())->method('getOrderBase')
			->will($this->returnValue($baseItem));

		$this->object->expects($this->once())->method('getProvider')
			->will($this->returnValue($provider));

		$provider->expects($this->once())->method('getTransaction')
			->will($this->returnValue($request));

		$request->expects($this->once())->method('send')
			->will($this->returnValue($response));

		$response->expects($this->once())->method('isCancelled')
			->will($this->returnValue(true));

		$cmpFcn = function( $subject ) {
			return $subject->getPaymentStatus() === \Aimeos\MShop\Order\Item\Base::PAY_CANCELED;
		};

		$this->object->expects( $this->once() )->method( 'saveOrder' )->with( $this->callback( $cmpFcn ) );

		$this->object->query($this->getOrder());
	}

	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::createManager($this->context);

		$search = $manager->createSearch();
		$search->setConditions($search->compare('==', 'order.datepayment', '2008-02-15 12:34:56'));

		$result = $manager->searchItems($search);

		if ( ( $item = reset( $result ) ) === false ) {
			throw new \RuntimeException('No order found');
		}

		return $item;
	}

	protected function access( $name )
	{
		$class = new \ReflectionClass(\Aimeos\MShop\Service\Provider\Payment\Datatrans::class);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method;
	}
}
