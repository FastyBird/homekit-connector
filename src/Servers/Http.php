<?php declare(strict_types = 1);

/**
 * Http.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          1.0.0
 *
 * @date           19.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use Nette;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log;
use React\EventLoop;
use React\Http as ReactHttp;
use React\Socket;
use Throwable;
use function assert;

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

	private SecureServer|null $socket = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Entities\HomeKitConnector $connector,
		private readonly Middleware\Router $routerMiddleware,
		private readonly SecureServerFactory $secureServerFactory,
		private readonly Clients\Subscriber $subscriber,
		private readonly Protocol\Driver $accessoriesDriver,
		private readonly Entities\Protocol\AccessoryFactory $accessoryFactory,
		private readonly Entities\Protocol\ServiceFactory $serviceFactory,
		private readonly Entities\Protocol\CharacteristicsFactory $characteristicsFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Terminate
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws Nette\IOException
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

		foreach ($this->connector->getDevices() as $device) {
			assert($device instanceof Entities\HomeKitDevice);

			$accessory = $this->accessoryFactory->create($device, null, $device->getCategory());
			assert($accessory instanceof Entities\Protocol\Device);

			foreach ($device->getChannels() as $channel) {
				$service = $this->serviceFactory->create(
					$channel->getIdentifier(),
					$accessory,
					$channel,
				);

				foreach ($channel->getProperties() as $property) {
					if (
						$property instanceof DevicesEntities\Channels\Properties\Mapped
						|| $property instanceof DevicesEntities\Channels\Properties\Variable
					) {
						$characteristic = $this->characteristicsFactory->create(
							$property->getIdentifier(),
							$service,
							$property,
						);

						if ($property instanceof DevicesEntities\Channels\Properties\Variable) {
							$characteristic->setActualValue($property->getValue());
						}

						$service->addCharacteristic($characteristic);
					}
				}

				$accessory->addService($service);
			}

			$this->accessoriesDriver->addBridgedAccessory($accessory);
		}

		try {
			$this->logger->debug(
				'Creating HAP web server',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
					'server' => [
						'address' => self::LISTENING_ADDRESS,
						'port' => $this->connector->getPort(),
					],
				],
			);

			$this->socket = $this->secureServerFactory->create(
				$this->connector,
				new Socket\SocketServer(
					self::LISTENING_ADDRESS . ':' . $this->connector->getPort(),
					[],
					$this->eventLoop,
				),
			);
		} catch (Throwable $ex) {
			$this->logger->error(
				'Socket server could not be created',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);

			throw new DevicesExceptions\Terminate(
				'Socket server could not be created',
				$ex->getCode(),
				$ex,
			);
		}

		$this->socket->on('connection', function (Socket\ConnectionInterface $connection): void {
			$this->logger->debug(
				'New client has connected to server',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
					'client' => [
						'address' => $connection->getRemoteAddress(),
					],
				],
			);

			$this->subscriber->registerConnection($connection);

			$connection->on('close', function () use ($connection): void {
				$this->logger->debug(
					'Connected client has closed connection',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'http-server',
						'group' => 'server',
						'connector' => [
							'id' => $this->connector->getPlainId(),
						],
						'client' => [
							'address' => $connection->getRemoteAddress(),
						],
					],
				);

				$this->subscriber->unregisterConnection($connection);
			});
		});

		$this->socket->on('error', function (Throwable $ex): void {
			$this->logger->error(
				'An error occurred during socket handling',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);

			throw new DevicesExceptions\Terminate(
				'HTTP server was terminated',
				$ex->getCode(),
				$ex,
			);
		});

		$this->socket->on('close', function (): void {
			$this->logger->info(
				'Server was closed',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'mdns-server',
					'group' => 'server',
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);
		});

		$server = new ReactHttp\HttpServer(
			$this->eventLoop,
			function (ServerRequestInterface $request, callable $next): ResponseInterface {
				$request = $request->withAttribute(
					self::REQUEST_ATTRIBUTE_CONNECTOR,
					$this->connector->getPlainId(),
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
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'exception' => [
						'message' => $ex->getMessage(),
						'code' => $ex->getCode(),
					],
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);

			throw new DevicesExceptions\Terminate(
				'HTTP server was terminated',
				$ex->getCode(),
				$ex,
			);
		});
	}

	public function disconnect(): void
	{
		$this->logger->debug(
			'Closing HAP web server',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'http-server',
				'group' => 'server',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);

		$this->socket?->close();
	}

	public function setSharedKey(string|null $sharedKey): void
	{
		$this->logger->debug(
			'Shared key has been changed',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'http-server',
				'group' => 'server',
				'connector' => [
					'id' => $this->connector->getPlainId(),
				],
			],
		);

		$this->socket?->setSharedKey($sharedKey);
	}

}
