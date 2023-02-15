<?php declare(strict_types = 1);

/**
 * Request.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           19.09.22
 */

namespace FastyBird\Connector\HomeKit\Events;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Contracts\EventDispatcher;

/**
 * Http request event
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Request extends EventDispatcher\Event
{

	public function __construct(private readonly ServerRequestInterface $request)
	{
	}

	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}

}
