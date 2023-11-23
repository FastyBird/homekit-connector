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

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Middleware;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Library\Metadata\ValueObjects as MetadataValueObjects;
use FastyBird\Module\Devices\Constants as DevicesConstants;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop;
use React\Http as ReactHttp;
use React\Socket;
use Throwable;
use function array_map;
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

	/**
	 * @param DevicesModels\Configuration\Devices\Repository<MetadataDocuments\DevicesModule\Device> $devicesConfigurationRepository
	 * @param DevicesModels\Configuration\Devices\Properties\Repository<MetadataDocuments\DevicesModule\DeviceVariableProperty> $devicesPropertiesConfigurationRepository
	 * @param DevicesModels\Configuration\Channels\Repository<MetadataDocuments\DevicesModule\Channel> $channelsConfigurationRepository
	 * @param DevicesModels\Configuration\Channels\Properties\Repository<MetadataDocuments\DevicesModule\ChannelDynamicProperty|MetadataDocuments\DevicesModule\ChannelVariableProperty|MetadataDocuments\DevicesModule\ChannelMappedProperty> $channelsPropertiesConfigurationRepository
	 */
	public function __construct(
		private readonly MetadataDocuments\DevicesModule\Connector $connector,
		private readonly Middleware\Router $routerMiddleware,
		private readonly SecureServerFactory $secureServerFactory,
		private readonly Clients\Subscriber $subscriber,
		private readonly Protocol\Driver $accessoriesDriver,
		private readonly Entities\Protocol\AccessoryFactory $accessoryFactory,
		private readonly Entities\Protocol\ServiceFactory $serviceFactory,
		private readonly Entities\Protocol\CharacteristicsFactory $characteristicsFactory,
		private readonly Helpers\Connector $connectorHelper,
		private readonly Helpers\Device $deviceHelper,
		private readonly Helpers\Channel $channelHelper,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelsPropertiesStatesManager,
		private readonly DevicesUtilities\DeviceConnection $deviceConnectionManager,
		private readonly DevicesUtilities\Database $databaseHelper,
		private readonly DevicesModels\Entities\Connectors\Properties\PropertiesManager $connectorsPropertiesManager,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Devices\Properties\PropertiesManager $devicesPropertiesManager,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws DevicesExceptions\Terminate
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws Nette\IOException
	 */
	public function connect(): void
	{
		$bridge = $this->accessoryFactory->create(
			$this->connector,
			null,
			Types\AccessoryCategory::get(Types\AccessoryCategory::BRIDGE),
		);
		assert($bridge instanceof Entities\Protocol\Bridge);

		$this->accessoriesDriver->reset();
		$this->accessoriesDriver->addBridge($bridge);

		$bridgedAccessories = [];

		$findDevicesQuery = new DevicesQueries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		foreach ($this->devicesConfigurationRepository->findAllBy($findDevicesQuery) as $device) {
			$findDevicePropertyQuery = new DevicesQueries\Configuration\FindDeviceVariableProperties();
			$findDevicePropertyQuery->forDevice($device);
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::AID);

			$aidProperty = $this->devicesPropertiesConfigurationRepository->findOneBy(
				$findDevicePropertyQuery,
				MetadataDocuments\DevicesModule\DeviceVariableProperty::class,
			);

			$aid = $aidProperty?->getValue() ?? null;

			if ($aid !== null) {
				$aid = intval(MetadataUtilities\ValueHelper::flattenValue($aid));
			}

			$accessory = $this->accessoryFactory->create(
				$device,
				$aid,
				$this->deviceHelper->getAccessoryCategory($device),
			);
			assert($accessory instanceof Entities\Protocol\Device);

			$findChannelsQuery = new DevicesQueries\Configuration\FindChannels();
			$findChannelsQuery->forDevice($device);

			foreach ($this->channelsConfigurationRepository->findAllBy($findChannelsQuery) as $channel) {
				$service = $this->serviceFactory->create(
					$this->channelHelper->getServiceType($channel),
					$accessory,
					$channel,
				);

				$findChannelPropertiesQuery = new DevicesQueries\Configuration\FindChannelProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				foreach ($this->channelsPropertiesConfigurationRepository->findAllBy(
					$findChannelPropertiesQuery,
				) as $property) {
					$format = $property->getFormat();

					$characteristic = $this->characteristicsFactory->create(
						$property->getIdentifier(),
						$service,
						$property,
						$format instanceof MetadataValueObjects\StringEnumFormat
							? array_map(static fn (string $item): int => intval($item), $format->toArray())
							: null,
						null,
						$format instanceof MetadataValueObjects\NumberRangeFormat ? $format->getMin() : null,
						$format instanceof MetadataValueObjects\NumberRangeFormat ? $format->getMax() : null,
						$property->getStep(),
						$property->getUnit() !== null && Types\CharacteristicUnit::isValidValue($property->getUnit())
							? Types\CharacteristicUnit::get($property->getUnit())
							: null,
					);

					$service->addCharacteristic($characteristic);
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

			$findDevicePropertyQuery = new DevicesQueries\Configuration\FindDeviceVariableProperties();
			$findDevicePropertyQuery->forDevice($accessory->getDevice());
			$findDevicePropertyQuery->byIdentifier(Types\DevicePropertyIdentifier::AID);

			$aidProperty = $this->devicesPropertiesConfigurationRepository->findOneBy(
				$findDevicePropertyQuery,
				MetadataDocuments\DevicesModule\DeviceVariableProperty::class,
			);

			if ($aidProperty === null) {
				$this->databaseHelper->transaction(
					function () use ($accessory): void {
						$findDeviceQuery = new Queries\Entities\FindDevices();
						$findDeviceQuery->byId($accessory->getDevice()->getId());

						$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class);
						assert($device instanceof Entities\HomeKitDevice);

						$this->devicesPropertiesManager->create(Nette\Utils\ArrayHash::from([
							'entity' => DevicesEntities\Devices\Properties\Variable::class,
							'identifier' => Types\DevicePropertyIdentifier::AID,
							'dataType' => MetadataTypes\DataType::get(MetadataTypes\DataType::DATA_TYPE_UCHAR),
							'value' => $accessory->getAid(),
							'device' => $device,
						]));
					},
				);
			}

			foreach ($accessory->getServices() as $service) {
				foreach ($service->getCharacteristics() as $characteristic) {
					$property = $characteristic->getProperty();

					if ($property instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
						$characteristic->setActualValue($property->getValue());
					} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
						try {
							$state = $this->channelsPropertiesStatesManager->readValue($property);

							if ($state !== null) {
								$characteristic->setActualValue($state->getExpectedValue() ?? $state->getActualValue());
							}
						} catch (Exceptions\InvalidState $ex) {
							$this->logger->warning(
								'State value could not be set to characteristic',
								[
									'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
									'type' => 'http-server',
									'exception' => BootstrapHelpers\Logger::buildException($ex),
									'connector' => [
										'id' => $this->connector->getId()->toString(),
									],
									'device' => [
										'id' => $accessory->getDevice()->getId()->toString(),
									],
									'channel' => [
										'id' => $service->getChannel()?->getId()->toString(),
									],
									'property' => [
										'id' => $property->getId()->toString(),
									],
								],
							);
						} catch (DevicesExceptions\NotImplemented) {
							// Ignore error
						}
					} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty) {
						$findParentPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
						$findParentPropertyQuery->byId($property->getParent());

						$parent = $this->channelsPropertiesConfigurationRepository->findOneBy($findParentPropertyQuery);

						if ($parent instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
							try {
								$state = $this->channelsPropertiesStatesManager->readValue($property);

								if ($state !== null) {
									$characteristic->setActualValue(
										$state->getExpectedValue() ?? $state->getActualValue(),
									);
								}
							} catch (Exceptions\InvalidState $ex) {
								$this->logger->warning(
									'State value could not be set to characteristic',
									[
										'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
										'type' => 'http-server',
										'exception' => BootstrapHelpers\Logger::buildException($ex),
										'connector' => [
											'id' => $this->connector->getId()->toString(),
										],
										'device' => [
											'id' => $accessory->getDevice()->getId()->toString(),
										],
										'channel' => [
											'id' => $service->getChannel()?->getId()->toString(),
										],
										'property' => [
											'id' => $property->getId()->toString(),
										],
									],
								);
							} catch (DevicesExceptions\NotImplemented) {
								// Ignore error
							}
						} elseif ($parent instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
							$characteristic->setActualValue($parent->getValue());
						}
					}
				}
			}

			$this->deviceConnectionManager->setState(
				$accessory->getDevice(),
				MetadataTypes\ConnectionState::get(MetadataTypes\ConnectionState::STATE_CONNECTED),
			);
		}

		try {
			$this->logger->debug(
				'Creating HAP web server',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'connector' => [
						'id' => $this->connector->getId()->toString(),
					],
					'server' => [
						'address' => self::LISTENING_ADDRESS,
						'port' => $this->connectorHelper->getPort($this->connector),
					],
				],
			);

			$this->socket = $this->secureServerFactory->create(
				$this->connector,
				new Socket\SocketServer(
					self::LISTENING_ADDRESS . ':' . $this->connectorHelper->getPort($this->connector),
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
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getId()->toString(),
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
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
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
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getId()->toString(),
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
					'type' => 'http-server',
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
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'http-server',
					'exception' => BootstrapHelpers\Logger::buildException($ex),
					'connector' => [
						'id' => $this->connector->getId()->toString(),
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
				'connector' => [
					'id' => $this->connector->getId()->toString(),
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
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function setSharedKey(
		DevicesEntities\Connectors\Properties\Property $property,
	): void
	{
		if (
			(
				$property instanceof DevicesEntities\Connectors\Properties\Variable
				&& $property->getConnector()->getId()->equals($this->connector->getId())
			)
			&& $property->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY
		) {
			$this->logger->debug(
				'Shared key has been updated',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
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
	}

}
