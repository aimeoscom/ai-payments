<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class SagePayTest extends \PHPUnit\Framework\TestCase
{
	private $object;
	private $context;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$this->context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager( $this->context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'sagepay.testmode' => true ) );

		$this->object = $this->getMockBuilder( 'Aimeos\MShop\Service\Provider\Payment\SagePayPublic' )
			->setMethods( array( 'getOrder', 'getOrderBase', 'saveOrder', 'saveOrderBase', 'getProvider' ) )
			->setConstructorArgs( array( $this->context, $item ) )
			->getMock();
	}


	protected function tearDown()
	{
		unset( $this->object, $this->context );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'SagePay', $this->object->getValuePublic( 'type' ) );
	}


	public function testGetValueOnsite()
	{
		$this->assertTrue( $this->object->getValuePublic( 'onsite' ) );
	}


	public function testGetValueTestmode()
	{
		$this->assertTrue( $this->object->getValuePublic( 'testmode' ) );
	}
}


class SagePayPublic extends \Aimeos\MShop\Service\Provider\Payment\SagePay
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}