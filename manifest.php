<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org), 2015-2016
 */

return array(
	'name' => 'ai-payments',
	'depends' => array(
		'aimeos-core',
	),
	'include' => array(
		'lib/custom/src',
	),
	'i18n' => array(
		'client/code' => 'client/i18n/code',
		'mshop' => 'lib/custom/i18n',
	),
);
