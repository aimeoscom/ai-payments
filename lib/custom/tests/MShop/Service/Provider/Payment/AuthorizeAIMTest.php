<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 */


namespace Aimeos\MShop\Service\Provider\Payment;


class AuthorizeAimTest extends \PHPUnit\Framework\TestCase
{
	private $object;


	protected function setUp()
	{
		if( !class_exists( 'Omnipay\Omnipay' ) ) {
			$this->markTestSkipped( 'Omnipay library not available' );
		}

		$context = \TestHelper::getContext();

		$serviceManager = \Aimeos\MShop\Service\Manager\Factory::createManager( $context );
		$item = $serviceManager->createItem();
		$item->setConfig( array( 'authorizenet.testmode' => true ) );
		$item->setCode( 'omnipaytest' );

		$this->object = new AuthorizeAIMPublic( $context, $item );
	}


	protected function tearDown()
	{
		unset( $this->object );
	}


	public function testGetValueType()
	{
		$this->assertEquals( 'AuthorizeNet_AIM', $this->object->getValuePublic( 'type' ) );
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


class AuthorizeAIMPublic extends \Aimeos\MShop\Service\Provider\Payment\AuthorizeAIM
{
	public function getValuePublic( $name, $default = null )
	{
		return $this->getValue( $name, $default );
	}
}