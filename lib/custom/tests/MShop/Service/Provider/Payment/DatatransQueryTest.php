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

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::create($this->context);
		$this->serviceItem = $serviceManager->createItem();
		$this->serviceItem->setConfig([ 'type' => 'Dummy' ]);
		$this->serviceItem->setCode('OGONE');

		$methods = [
			'getCustomerData', 'getOrder', 'getOrderBase', 'getTransactionReference', 'isImplemented',
			'saveOrder', 'saveOrderBase', 'getXmlProvider', 'saveTransationRef', 'setCustomerData',
			'getProvider', 'getTransaction',
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

	public function testDatatransReceivedCode()
	{
		$this->assertEquals(self::getObjectAttribute($this->object, 'datatransReceivedCode'), [2, 3, 21]);
	}
	public function testDatatransAuthorizedCode()
	{
		$this->assertEquals(self::getObjectAttribute($this->object, 'datatransAuthorizedCode'), [1]);
	}

	public function testTransactionId()
	{
		$orderItem = $this->getOrder();
		$data = [
			'transactionId' => $orderItem->getId(),
		];
		$this->assertTrue(is_numeric($data['transactionId']));
	}

	public function testProvider()
	{
		$result = $this->access('getProvider')->invokeArgs($this->object, []);
		$this->assertInstanceOf(\Omnipay\Common\GatewayInterface::class, $result);
	}

	public function testQuery()
	{
		$orderItem = $this->getOrder();
		$baseItem = $this->getOrderBase(\Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE);

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

		$this->object->expects($this->once())->method('saveOrder');

		$this->object->query($this->getOrder());
	}

	protected function getOrder()
	{
		$manager = \Aimeos\MShop\Order\Manager\Factory::create($this->context);

		$search = $manager->createSearch();
		$search->setConditions($search->compare('==', 'order.datepayment', '2008-02-15 12:34:56'));

		$result = $manager->searchItems($search);

		if ( ( $item = reset( $result ) ) === false ) {
			throw new \RuntimeException('No order found');
		}

		return $item;
	}

	protected function getOrderBase( $parts = null )
	{
		if ( $parts === null ) {
			$parts = \Aimeos\MShop\Order\Item\Base\Base::PARTS_ADDRESS | \Aimeos\MShop\Order\Item\Base\Base::PARTS_SERVICE;
		}

		$manager = \Aimeos\MShop\Order\Manager\Factory::create($this->context)->getSubmanager('base');

		return $manager->load($this->getOrder()->getBaseId(), $parts);
	}

	protected function access( $name )
	{
		$class = new \ReflectionClass(\Aimeos\MShop\Service\Provider\Payment\Datatrans::class);
		$method = $class->getMethod($name);
		$method->setAccessible(true);

		return $method;
	}
}
