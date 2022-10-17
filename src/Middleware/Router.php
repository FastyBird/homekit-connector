<?php declare(strict_types = 1);

/**
 * Router.php
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

namespace FastyBird\Connector\HomeKit\Middleware;

use FastyBird\Connector\HomeKit\Events;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Metadata;
use Fig\Http\Message\StatusCodeInterface;
use InvalidArgumentException;
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
final class Router
{

	private SlimRouterHttp\ResponseFactory $responseFactory;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly SlimRouterRouting\IRouter $router,
		private readonly EventDispatcher\EventDispatcherInterface|null $dispatcher = null,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->responseFactory = new SlimRouterHttp\ResponseFactory();

		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws InvalidArgumentException
	 * @throws Utils\JsonException
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$this->dispatcher?->dispatch(new Events\Request($request));

		try {
			$response = $this->router->handle($request);
			$response = $response->withHeader('Server', 'FastyBird HomeKit Connector');

		} catch (Exceptions\HapRequestError $ex) {
			$this->logger->warning(
				'Request ended with error',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'request' => [
						'method' => $request->getMethod(),
						'path' => $request->getUri()->getPath(),
					],
				],
			);

			$response = $this->responseFactory->createResponse($ex->getCode());

			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode([
				Types\Representation::REPR_STATUS => $ex->getError()->getValue(),
			])));
		} catch (SlimRouterExceptions\HttpException $ex) {
			$this->logger->warning(
				'Received invalid HTTP request',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'request' => [
						'method' => $request->getMethod(),
						'path' => $request->getUri()->getPath(),
					],
				],
			);

			$response = $this->responseFactory->createResponse($ex->getCode());

			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode([
				Types\Representation::REPR_STATUS => Types\ServerStatus::STATUS_SERVICE_COMMUNICATION_FAILURE,
			])));
		} catch (Throwable $ex) {
			$this->logger->error(
				'An unhandled error occurred during handling server HTTP request',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'router-middleware',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
				],
			);

			$response = $this->responseFactory->createResponse(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);

			$response = $response->withHeader('Content-Type', Servers\Http::JSON_CONTENT_TYPE);
			$response = $response->withBody(SlimRouter\Http\Stream::fromBodyString(Utils\Json::encode([
				Types\Representation::REPR_STATUS => Types\ServerStatus::STATUS_SERVICE_COMMUNICATION_FAILURE,
			])));
		}

		$this->dispatcher?->dispatch(new Events\Response($request, $response));

		return $response;
	}

}
