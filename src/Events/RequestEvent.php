<?php declare(strict_types = 1);

/**
 * RequestEvent.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Events
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Events;

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
class RequestEvent extends EventDispatcher\Event
{

	/** @var ServerRequestInterface */
	private ServerRequestInterface $request;

	public function __construct(
		ServerRequestInterface $request
	) {
		$this->request = $request;
	}

	/**
	 * @return ServerRequestInterface
	 */
	public function getRequest(): ServerRequestInterface
	{
		return $this->request;
	}

}
