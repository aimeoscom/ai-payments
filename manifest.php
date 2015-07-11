<?php

return array(
	'name' => 'ai-payments',
	'depends' => array(
		'aimeos-core',
	),
	'include' => array(
		'lib/custom/src',
	),
	'i18n' => array(
		'client/html/code' => 'client/html/i18n/code',
		'mshop' => 'lib/custom/i18n',
	),
);
