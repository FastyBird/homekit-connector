<?php declare(strict_types = 1);

use Fig\Http\Message\StatusCodeInterface;

return [
	// Valid responses
	//////////////////
	'readAll' => [
		'/accessories',
		StatusCodeInterface::STATUS_OK,
		__DIR__ . '/responses/accessories.index.json',
	],
];
