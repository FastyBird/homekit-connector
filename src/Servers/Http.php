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
use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Exceptions;
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
		private readonly MetadataEntities\DevicesModule\Connector $connector,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Middleware\Router $routerMiddleware,
		private readonly SecureServerFactory $secureServerFactory,
		private readonly Clients\Subscriber $subscriber,
		private readonly Protocol\Driver $accessoriesDriver,
		private readonly Entities\Protocol\AccessoryFactory $accessoryFactory,
		private readonly Entities\Protocol\ServiceFactory $serviceFactory,
		private readonly Entities\Protocol\CharacteristicsFactory $characteristicsFactory,
		private readonly DevicesModuleModels\DataStorage\DevicesRepository $devicesRepository,
		private readonly DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository,
		private readonly DevicesModuleModels\DataStorage\ChannelPropertiesRepository $channelPropertiesRepository,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();

		$this->connectorHelper->on(
			'updated',
			function (
				Uuid\UuidInterface $connectorId,
				HomeKitConnector\Types\ConnectorPropertyIdentifier $type,
				MetadataEntities\DevicesModule\ConnectorVariableProperty $property,
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
				MetadataEntities\DevicesModule\ConnectorVariableProperty $property,
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
	 * @throws DBAL\Exception
	 * @throws DevicesModuleExceptions\Terminate
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws Metadata\Exceptions\FileNotFound
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

		foreach ($this->devicesRepository->findAllByConnector($this->connector->getId()) as $device) {
			$accessory = $this->accessoryFactory->create(
				$device,
				null,
				Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_OUTLET),
			);
			assert($accessory instanceof Entities\Protocol\Device);

			foreach ($this->channelsRepository->findAllByDevice($device->getId()) as $channel) {
				$service = $this->serviceFactory->create(
					$channel->getIdentifier(),
					$accessory,
					$channel,
				);

				foreach ($this->channelPropertiesRepository->findAllByChannel($channel->getId()) as $property) {
					if (
						$property instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
						|| $property instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
					) {
						$characteristic = $this->characteristicsFactory->create(
							$property->getIdentifier(),
							$service,
							$property,
						);

						if ($property instanceof MetadataEntities\DevicesModule\ChannelVariableProperty) {
							$characteristic->setActualValue($property->getValue());
						}

						$service->addCharacteristic($characteristic);
					}
				}

				$characteristic = $this->characteristicsFactory->create(
					'OutletInUse',
					$service,
				);
				$characteristic->setActualValue(true);

				$service->addCharacteristic($characteristic);

				$accessory->addService($service);
			}

			$this->accessoriesDriver->addBridgedAccessory($accessory);
		}

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

			throw new DevicesModuleExceptions\Terminate(
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

			throw new DevicesModuleExceptions\Terminate(
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

			throw new DevicesModuleExceptions\Terminate(
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
