<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2018
 * @package MShop
 * @subpackage Service
 */


namespace Aimeos\MShop\Service\Provider\Payment;


/**
 * Payment provider for Authorize.NET SIM.
 *
 * @package MShop
 * @subpackage Service
 */
class AuthorizeSIM
	extends \Aimeos\MShop\Service\Provider\Payment\OmniPay
	implements \Aimeos\MShop\Service\Provider\Payment\Iface
{
	private $beConfig = array(
		'payment.url-success' => array(
			'code' => 'payment.url-success',
			'internalcode'=> 'payment.url-success',
			'label'=> 'URL to confirm page',
			'type'=> 'string',
			'internaltype'=> 'string',
			'default'=> '',
			'required'=> true,
		),
	);


	/**
	 * Returns the configuration attribute definitions of the provider to generate a list of available fields and
	 * rules for the value of each field in the administration interface.
	 *
	 * @return array List of attribute definitions implementing \Aimeos\MW\Common\Critera\Attribute\Iface
	 */
	public function getConfigBE()
	{
		$list = parent::getConfigBE();

		foreach( $this->beConfig as $key => $config )
		{
			$config['code'] = $config['code'];
			$list[$key] = new \Aimeos\MW\Criteria\Attribute\Standard( $config );
		}

		return $list;
	}


	/**
	 * Checks the backend configuration attributes for validity.
	 *
	 * @param array $attributes Attributes added by the shop owner in the administraton interface
	 * @return array An array with the attribute keys as key and an error message as values for all attributes that are
	 * 	known by the provider but aren't valid
	 */
	public function checkConfigBE( array $attributes )
	{
		return array_merge( parent::checkConfigBE( $attributes ), $this->checkConfig( $this->beConfig, $attributes ) );
	}


	/**
	 * Updates the order status sent by payment gateway notifications
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface Request object
	 * @return \Psr\Http\Message\ResponseInterface Response object
	 */
	public function updatePush( \Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response )
	{
		$params = (array) $request->getAttributes() + (array) $request->getParsedBody() + (array) $request->getQueryParams();

		if( isset( $params['x_MD5_Hash'] ) )
		{
			$url = $this->getConfigValue( array( 'payment.url-success' ) );
			$output = sprintf( $this->getValue( 'body', 'success' ), $url );

			$response = parent::updatePush( $request, $response );
			$response = $response->withBody( $response->createStreamFromString( $output ) );
			$response = $response->withHeader( 'Location', $url );
		}

		return $response;
	}


	/**
	 * Returns the prefix for the configuration definitions
	 *
	 * @return string Prefix without dot
	 */
	protected function getConfigPrefix()
	{
		return 'authorizenet';
	}


	/**
	 * Returns the value for the given configuration key
	 *
	 * @param string $key Configuration key name
	 * @param mixed $default Default value if no configuration is found
	 * @return mixed Configuration value
	 */
	protected function getValue( $key, $default = null )
	{
		switch( $key ) {
			case 'type': return 'AuthorizeNet_SIM';
		}

		return parent::getValue( $key, $default );
	}
}
