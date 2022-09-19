<?php declare(strict_types = 1);

/**
 * RouterMiddleware.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Middleware
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Middleware;

use FastyBird\HomeKitConnector\Events;
use IPub\SlimRouter\Routing;
use Psr\EventDispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Connector HTTP server router middleware
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Middleware
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class RouterMiddleware
{

	/** @var EventDispatcher\EventDispatcherInterface|null */
	private ?EventDispatcher\EventDispatcherInterface $dispatcher;

	/** @var Routing\IRouter */
	private Routing\IRouter $router;

	public function __construct(
		Routing\IRouter $router,
		?EventDispatcher\EventDispatcherInterface $dispatcher = null
	) {
		$this->router = $router;
		$this->dispatcher = $dispatcher;
	}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$this->dispatcher?->dispatch(new Events\RequestEvent($request));

		$response = $this->router->handle($request);

		$this->dispatcher?->dispatch(new Events\ResponseEvent($request, $response));

		return $response;
	}

}
