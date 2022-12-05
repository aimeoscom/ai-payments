<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2022
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

		$serviceManager = \Aimeos\MShop::create( $this->context, 'service' );
		$item = $serviceManager->create();
		$item->setCode( 'omnipaytest' );
		$item->setConfig( $conf );

		$this->object = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\AuthorizeDPM' )
			->setMethods( array( 'save', 'getProvider' ) )
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
		$manager = \Aimeos\MShop::create( $this->context, 'order' );
		$search = $manager->filter()->add( 'order.datepayment', '==', '2008-02-15 12:34:56' );

		return $manager->search( $search, ['order', 'order/address', 'order/service'] )
			->first( new \RuntimeException( 'No order found' ) );
	}
}
