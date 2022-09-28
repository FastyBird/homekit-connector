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

use FastyBird\HomeKitConnector\Servers;
use FastyBird\HomeKitConnector\Events;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\Metadata;
use Fig\Http\Message\StatusCodeInterface;
use IPub\SlimRouter;
use IPub\SlimRouter\Exceptions as SlimRouterExceptions;
use IPub\SlimRouter\Http as SlimRouterHttp;
use IPub\SlimRouter\Routing as SlimRouterRouting;
use Nette\Utils;
use Psr\EventDispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log;
use Throwable;

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

	/** @var SlimRouterRouting\IRouter */
	private SlimRouterRouting\IRouter $router;

	/** @var SlimRouterHttp\ResponseFactory */
	private SlimRouterHttp\ResponseFactory $responseFactory;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param SlimRouterRouting\IRouter $router
	 * @param EventDispatcher\EventDispatcherInterface|null $dispatcher
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		SlimRouterRouting\IRouter $router,
		?EventDispatcher\EventDispatcherInterface $dispatcher = null,
		?Log\LoggerInterface $logger = null
	) {
		$this->router = $router;
		$this->dispatcher = $dispatcher;

		$this->responseFactory = new SlimRouterHttp\ResponseFactory();

		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$this->dispatcher?->dispatch(new Events\RequestEvent($request));

		try {
			$response = $this->router->handle($request);
		} catch (Exceptions\HapRequestError $ex) {
			$this->logger->warning(
				'Request ended with error',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'request'   => [
						'method' => $request->getMethod(),
						'path'   => $request->getUri()->getPath(),
					],
				]
			);

			$response = $this->responseFactory->createResponse($ex->getCode());

			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode([
				'status' => $ex->getError()->getValue(),
			])));
		} catch (SlimRouterExceptions\HttpException $ex) {
			$this->logger->warning(
				'Received invalid HTTP request',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
					'request'   => [
						'method' => $request->getMethod(),
						'path'   => $request->getUri()->getPath(),
					],
				]
			);

			$response = $this->responseFactory->createResponse($ex->getCode());
		} catch (Throwable $ex) {
			$this->logger->error(
				'An unhandled error occurred during handling server HTTP request',
				[
					'source'    => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type'      => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code'    => $ex->getCode(),
					],
				]
			);

			$response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);
		}

		$this->dispatcher?->dispatch(new Events\ResponseEvent($request, $response));

		return $response;
	}

}
