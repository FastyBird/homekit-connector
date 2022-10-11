<?php declare(strict_types = 1);

use Fig\Http\Message\StatusCodeInterface;

return [
	// Valid responses
	//////////////////
	'readAll' => [
		'/characteristics?id=1.6',
		StatusCodeInterface::STATUS_OK,
		__DIR__ . '/responses/characteristics.index.json',
	],
];
