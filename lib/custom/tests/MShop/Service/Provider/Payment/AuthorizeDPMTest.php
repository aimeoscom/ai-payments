<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2021
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class AuthorizeDPMTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;


	protected function setUp() : void
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::context();

		$conf = array(
			'address' => '1',
			'onsite' => '1',
			'testmode' => true,
		);

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::create( $this->context );
		$item = $serviceManager->create();
		$item->setCode( 'omnipaytest' );
		$item->setConfig( $conf );

		$this->object = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\AuthorizeDPM' )
			->setMethods( array( 'getOrder', 'getOrderBase', 'saveOrder', 'saveOrderBase', 'getProvider' ) )
			->setConstructorArgs( array( $this->context, $item ) )
			->getMock();
	}


	protected function tearDown() : void
	{
		unset( $this->object, $this->context );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'AuthorizeNet_DPM', $this->access( 'getValue' )->invokeArgs( $this->object, ['type'] ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->access( 'getValue' )->invokeArgs( $this->object, ['onsite'] ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->access( 'getValue' )->invokeArgs( $this->object, ['testmode'] ) );
	}


	public function testProcessOnsiteAddress()
	{
		$this->object->expects( $this->any() )->method( 'getOrderBase' )
			->will( $this->returnValue( $this->getOrderBase() ) );

		$result = $this->object->process( $this->getOrder(), [] );

		$this->assertInstanceOf( \Aimeos\MShop\Common\Helper\Form\Iface::class, $result );
	}


	protected function access( $name )
	{
		$class = new \ReflectionClass( \Aimeos\MShop\Service\Provider\Payment\AuthorizeDPM::class );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
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
		return $manager->load( $this->getOrder()->getBaseId(), ['order/base/address', 'order/base/service'] );
	}
}
