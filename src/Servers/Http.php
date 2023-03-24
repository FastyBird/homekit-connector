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
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Constants as DevicesConstants;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log;
use React\EventLoop;
use React\Http as ReactHttp;
use React\Socket;
use Throwable;
use function assert;
use function hex2bin;
use function intval;
use function is_string;
use function usort;

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
		private readonly DevicesUtilities\ChannelPropertiesStates $channelsPropertiesStates,
		private readonly DevicesModels\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
		private readonly DevicesModels\Devices\Properties\PropertiesRepository $devicesPropertiesRepository,
		private readonly DevicesModels\Devices\Properties\PropertiesManager $devicesPropertiesManager,
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

		$bridgedAccessories = [];

		foreach ($this->connector->getDevices() as $device) {
			assert($device instanceof Entities\HomeKitDevice);

			$findDeviceProperty = new DevicesQueries\FindDeviceProperties();
			$findDeviceProperty->forDevice($device);
			$findDeviceProperty->byIdentifier(Types\DevicePropertyIdentifier::IDENTIFIER_AID);

			$aidProperty = $this->devicesPropertiesRepository->findOneBy(
				$findDeviceProperty,
				DevicesEntities\Devices\Properties\Variable::class,
			);

			$aid = $aidProperty?->getValue() ?? null;

			if ($aid !== null) {
				$aid = intval($aid);
			}

			$accessory = $this->accessoryFactory->create($device, $aid, $device->getCategory());
			assert($accessory instanceof Entities\Protocol\Device);

			foreach ($device->getChannels() as $channel) {
				$service = $this->serviceFactory->create(
					$channel->getServiceType(),
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
						} else {
							try {
								$state = $this->channelsPropertiesStates->getValue($property);

								if ($state !== null) {
									$characteristic->setActualValue(Protocol\Transformer::fromMappedParent(
										$property,
										$property->getParent(),
										$state->getActualValue(),
									));
								}
							} catch (DevicesExceptions\NotImplemented) {
								// Ignore error
							}
						}

						$service->addCharacteristic($characteristic);
					}
				}

				$accessory->addService($service);
			}

			$bridgedAccessories[] = $accessory;
		}

		usort($bridgedAccessories, static function (Entities\Protocol\Device $a, Entities\Protocol\Device $b) {
			if ($a->getAid() === null) {
				return 1;
			}

			if ($b->getAid() === null) {
				return -1;
			}

			if ($a->getAid() === $b->getAid()) {
				return 0;
			}

			return $a->getAid() <=> $b->getAid();
		});

		foreach ($bridgedAccessories as $accessory) {
			$this->accessoriesDriver->addBridgedAccessory($accessory);

			$findDeviceProperty = new DevicesQueries\FindDeviceProperties();
			$findDeviceProperty->forDevice($accessory->getDevice());
			$findDeviceProperty->byIdentifier(Types\DevicePropertyIdentifier::IDENTIFIER_AID);

			$aidProperty = $this->devicesPropertiesRepository->findOneBy(
				$findDeviceProperty,
				DevicesEntities\Devices\Properties\Variable::class,
			);

			if ($aidProperty === null) {
				$this->devicesPropertiesManager->create(Nette\Utils\ArrayHash::from([
					'entity' => DevicesEntities\Devices\Properties\Variable::class,
					'identifier' => Types\DevicePropertyIdentifier::IDENTIFIER_AID,
					'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
					'value' => $accessory->getAid(),
					'device' => $accessory->getDevice(),
				]));
			}
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

		$this->connectorsPropertiesManager->on(
			DevicesConstants::EVENT_ENTITY_CREATED,
			[$this, 'setSharedKey'],
		);

		$this->connectorsPropertiesManager->on(
			DevicesConstants::EVENT_ENTITY_UPDATED,
			[$this, 'setSharedKey'],
		);
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

		$this->connectorsPropertiesManager->removeListener(
			DevicesConstants::EVENT_ENTITY_CREATED,
			[$this, 'setSharedKey'],
		);

		$this->connectorsPropertiesManager->removeListener(
			DevicesConstants::EVENT_ENTITY_UPDATED,
			[$this, 'setSharedKey'],
		);

		$this->socket?->close();

		$this->socket = null;
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function setSharedKey(
		DevicesEntities\Connectors\Properties\Property|MetadataEntities\DevicesModule\ConnectorVariableProperty $property,
	): void
	{
		if (
			(
				(
					$property instanceof DevicesEntities\Connectors\Properties\Variable
					&& $property->getConnector()->getId()->equals($this->connector->getId())
				) || (
					$property instanceof MetadataEntities\DevicesModule\ConnectorVariableProperty
					&& $property->getConnector()->equals($this->connector->getId())
				)
			)
			&& $property->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY
		) {
			$this->logger->debug(
				'Shared key has been updated',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'group' => 'server',
					'connector' => [
						'id' => $this->connector->getPlainId(),
					],
				],
			);

			$this->socket?->setSharedKey(
				is_string($property->getValue()) ? (string) hex2bin($property->getValue()) : null,
			);
		}
	}

}
