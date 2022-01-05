<?php

/**
 * @license LGPLv3, http://opensource.org/licenses/LGPL-3.0
 * @copyright Aimeos (aimeos.org], 2015-2022
 */

return [
	'name' => 'ai-payments',
	'depends' => [
		'aimeos-core',
	],
	'include' => [
		'lib/custom/src',
	],
	'i18n' => [
		'client/code' => 'client/i18n/code',
		'mshop' => 'lib/custom/i18n',
	],
];
