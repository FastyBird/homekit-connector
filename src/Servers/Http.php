<?php declare(strict_types = 1);

/**
 * Http.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Servers;

use Doctrine\DBAL;
use FastyBird\DevicesModule\Exceptions as DevicesModuleExceptions;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Helpers;
use FastyBird\HomeKitConnector\Middleware;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use Nette;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log;
use Ramsey\Uuid;
use React\EventLoop;
use React\Http as ReactHttp;
use React\Socket;
use Throwable;
use function assert;
use function hex2bin;
use function is_string;
use function var_dump;

/**
 * HTTP connector communication server
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Http implements Server
{

	use Nette\SmartObject;

	public const REQUEST_ATTRIBUTE_CONNECTOR = 'connector';

	public const PAIRING_CONTENT_TYPE = 'application/pairing+tlv8';

	public const JSON_CONTENT_TYPE = 'application/hap+json';

	private const LISTENING_ADDRESS = '0.0.0.0';

	/** @var SecureServer|null */
	private SecureServer|null $socket = null;

	/** @var Log\LoggerInterface */
	private Log\LoggerInterface $logger;

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Helpers\Connector $connectorHelper
	 * @param Middleware\RouterMiddleware $routerMiddleware
	 * @param SecureServerFactory $secureServerFactory
	 * @param Clients\Subscriber $subscriber
	 * @param Protocol\Driver $accessoriesDriver
	 * @param Entities\Protocol\AccessoryFactory $accessoryFactory
	 * @param EventLoop\LoopInterface $eventLoop
	 * @param Log\LoggerInterface|null $logger
	 */
	public function __construct(
		private MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		private Helpers\Connector $connectorHelper,
		private Middleware\RouterMiddleware $routerMiddleware,
		private SecureServerFactory $secureServerFactory,
		private Clients\Subscriber $subscriber,
		private Protocol\Driver $accessoriesDriver,
		private Entities\Protocol\AccessoryFactory $accessoryFactory,
		private EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		$this->connectorHelper->on(
			'updated',
			function (
				Uuid\UuidInterface $connectorId,
				HomeKitConnector\Types\ConnectorPropertyIdentifier $type,
				MetadataEntities\Modules\DevicesModule\ConnectorStaticPropertyEntity $property,
			): void {
				if (
					$this->connector->getId()->equals($connectorId)
					&& $type->equalsValue(HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY)
				) {
					$this->logger->debug(
						'Shared key has been changed',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'http-server',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						],
					);

					$this->socket?->setSharedKey(
						is_string($property->getValue()) ? (string) hex2bin($property->getValue()) : null,
					);
				}
			},
		);

		$this->connectorHelper->on(
			'created',
			function (
				Uuid\UuidInterface $connectorId,
				HomeKitConnector\Types\ConnectorPropertyIdentifier $type,
				MetadataEntities\Modules\DevicesModule\ConnectorStaticPropertyEntity $property,
			): void {
				if (
					$this->connector->getId()->equals($connectorId)
					&& $type->equalsValue(HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY)
				) {
					$this->logger->debug(
						'Shared key has been created',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'http-server',
							'connector' => [
								'id' => $this->connector->getId()->toString(),
							],
						],
					);

					$this->socket?->setSharedKey(
						is_string($property->getValue()) ? (string) hex2bin($property->getValue()) : null,
					);
				}
			},
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws DBAL\Exception
	 * @throws DevicesModuleExceptions\TerminateException
	 */
	public function connect(): void
	{
		$bridge = $this->accessoryFactory->create(
			$this->connector,
			null,
			Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_BRIDGE),
		);
		assert($bridge instanceof Entities\Protocol\Bridge);

		$this->accessoriesDriver->reset();
		$this->accessoriesDriver->addBridge($bridge);

		$port = $this->connectorHelper->getConfiguration(
			$this->connector->getId(),
			HomeKitConnector\Types\ConnectorPropertyIdentifier::get(
				HomeKitConnector\Types\ConnectorPropertyIdentifier::IDENTIFIER_PORT,
			),
		);

		try {
			$this->logger->debug(
				'Creating HAP web server',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'http-server',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'server' => [
						'address' => self::LISTENING_ADDRESS,
						'port' => $port,
					],
				],
			);

			$this->socket = $this->secureServerFactory->create(
				$this->connector,
				new Socket\SocketServer(self::LISTENING_ADDRESS . ':' . $port, [], $this->eventLoop),
			);
		} catch (Throwable $ex) {
			$this->logger->error(
				'Socket server could not be created',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'http-server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);

			throw new DevicesModuleExceptions\TerminateException(
				'Socket server could not be created',
				$ex->getCode(),
				$ex,
			);
		}

		$this->socket->on('connection', function (Socket\ConnectionInterface $connection): void {
			$this->logger->debug(
				'New client has connected to server',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'http-server',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'client' => [
						'address' => $connection->getRemoteAddress(),
					],
				],
			);

			$this->subscriber->registerConnection($connection);

			var_dump('CONNECTED CLIENT');
			var_dump($connection->getRemoteAddress());

			$connection->on('close', function () use ($connection): void {
				$this->logger->debug(
					'Connected client has closed connection',
					[
						'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type' => 'http-server',
						'connector' => [
							'id' => $this->connector->getId()->toString(),
						],
						'client' => [
							'address' => $connection->getRemoteAddress(),
						],
					],
				);

				$this->subscriber->unregisterConnection($connection);

				var_dump('DISCONNECTED CLIENT');
				var_dump($connection->getRemoteAddress());
			});
		});

		$this->socket->on('error', function (Throwable $ex): void {
			$this->logger->error(
				'An error occurred during socket handling',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'http-server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);

			throw new DevicesModuleExceptions\TerminateException(
				'HTTP server was terminated',
				$ex->getCode(),
				$ex,
			);
		});

		$this->socket->on('close', function (): void {
			$this->logger->info(
				'Server was closed',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'mdns-server',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);
		});

		$server = new ReactHttp\HttpServer(
			$this->eventLoop,
			function (ServerRequestInterface $request, callable $next): ResponseInterface {
				$request = $request->withAttribute(
					self::REQUEST_ATTRIBUTE_CONNECTOR,
					$this->connector->getId()->toString(),
				);

				return $next($request);
			},
			$this->routerMiddleware,
		);
		$server->listen($this->socket);

		$server->on('error', function (Throwable $ex): void {
			$this->logger->error(
				'An error occurred during server handling',
				[
					'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
					'type' => 'http-server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
				],
			);

			throw new DevicesModuleExceptions\TerminateException(
				'HTTP server was terminated',
				$ex->getCode(),
				$ex,
			);
		});
	}

	/**
	 * {@inheritDoc}
	 */
	public function disconnect(): void
	{
		$this->logger->debug(
			'Closing HAP web server',
			[
				'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
				'type' => 'http-server',
				'connector' => [
					'id' => $this->connector->getId()->toString(),
				],
			],
		);

		$this->socket?->close();
	}

}
