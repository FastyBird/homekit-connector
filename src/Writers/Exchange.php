<?php declare(strict_types = 1);

/**
 * Exchange.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 * @since          1.0.0
 *
 * @date           11.02.23
 */

namespace FastyBird\Connector\HomeKit\Writers;

use Exception;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Servers;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette;
use Psr\Log;
use function array_key_exists;
use function intval;

/**
 * Exchange based properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Exchange implements Writer, ExchangeConsumers\Consumer
{

	use Nette\SmartObject;

	public const NAME = 'exchange';

	/** @var array<string, array<Servers\Server>> */
	private array $servers = [];

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly DevicesModels\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly ExchangeConsumers\Container $consumer,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function connect(Entities\HomeKitConnector $connector, array $servers): void
	{
		$this->servers[$connector->getPlainId()] = $servers;

		$this->consumer->enable(self::class);
	}

	public function disconnect(Entities\HomeKitConnector $connector, array $servers): void
	{
		unset($this->servers[$connector->getPlainId()]);

		if ($this->servers === []) {
			$this->consumer->disable(self::class);
		}
	}

	/**
	 * @throws Exceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidData
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\Logic
	 * @throws MetadataExceptions\MalformedInput
	 * @throws Exception
	 */
	public function consume(
		MetadataTypes\ModuleSource|MetadataTypes\PluginSource|MetadataTypes\ConnectorSource|MetadataTypes\AutomatorSource $source,
		MetadataTypes\RoutingKey $routingKey,
		MetadataEntities\Entity|null $entity,
	): void
	{
		if (
			$entity instanceof MetadataEntities\DevicesModule\DeviceMappedProperty
			|| $entity instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
			|| $entity instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
			|| $entity instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
		) {
			if (
				$entity instanceof MetadataEntities\DevicesModule\DeviceMappedProperty
				|| $entity instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
			) {
				$findDeviceQuery = new DevicesQueries\FindDevices();
				$findDeviceQuery->byId($entity->getDevice());

				$device = $this->devicesRepository->findOneBy($findDeviceQuery, Entities\HomeKitDevice::class);

				if ($device === null) {
					$this->logger->error(
						'Device for received device property message could not be loaded',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'exchange-writer',
							'message' => [
								'source' => $source->getValue(),
								'routing_key' => $routingKey->getValue(),
								'entity' => $entity->toArray(),
							],
							'property' => $entity->toArray(),
						],
					);

					return;
				}

				if (!array_key_exists($device->getConnector()->getPlainId(), $this->servers)) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($entity->getDevice());
			} else {
				$findChannelQuery = new DevicesQueries\FindChannels();
				$findChannelQuery->byId($entity->getChannel());

				$channel = $this->channelsRepository->findOneBy($findChannelQuery, Entities\HomeKitChannel::class);

				if ($channel === null) {
					$this->logger->error(
						'Channel for received channel property message could not be loaded',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'exchange-writer',
							'message' => [
								'source' => $source->getValue(),
								'routing_key' => $routingKey->getValue(),
								'entity' => $entity->toArray(),
							],
							'property' => $entity->toArray(),
						],
					);

					return;
				}

				if (!array_key_exists($channel->getDevice()->getConnector()->getPlainId(), $this->servers)) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($channel->getDevice()->getId());
			}

			if (!$accessory instanceof Entities\Protocol\Device) {
				$this->logger->warning(
					'Accessory for received property message was not found in accessory driver',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'exchange-writer',
						'message' => [
							'source' => $source->getValue(),
							'routing_key' => $routingKey->getValue(),
							'entity' => $entity->toArray(),
						],
						'property' => $entity->toArray(),
					],
				);

				return;
			}

			$this->processProperty($entity, $accessory);
		} elseif ($entity instanceof MetadataEntities\DevicesModule\ConnectorVariableProperty) {
			if (!array_key_exists($entity->getConnector()->toString(), $this->servers)) {
				return;
			}

			if (
				$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_PAIRED
				|| $entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_CONFIG_VERSION
			) {
				foreach ($this->servers[$entity->getConnector()->toString()] as $server) {
					if ($server instanceof Servers\Mdns) {
						$server->refresh($entity);
					}
				}
			}

			if ($entity->getIdentifier() === Types\ConnectorPropertyIdentifier::IDENTIFIER_SHARED_KEY) {
				foreach ($this->servers[$entity->getConnector()->toString()] as $server) {
					if ($server instanceof Servers\Http) {
						$server->setSharedKey($entity);
					}
				}
			}
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		MetadataEntities\DevicesModule\Property $entity,
		Entities\Protocol\Device $accessory,
	): void
	{
		if (
			!$entity instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
			&& !$entity instanceof MetadataEntities\DevicesModule\ChannelDynamicProperty
			&& !$entity instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
		) {
			return;
		}

		foreach ($accessory->getServices() as $service) {
			foreach ($service->getCharacteristics() as $characteristic) {
				if (
					$characteristic->getProperty() !== null
					&& $characteristic->getProperty()->getId()->equals($entity->getId())
				) {
					$findPropertyQuery = new DevicesQueries\FindChannelProperties();
					$findPropertyQuery->byId($entity->getId());

					$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

					if (
						$entity instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
						&& $property instanceof DevicesEntities\Channels\Properties\Mapped
					) {
						try {
							$characteristic->setActualValue(Protocol\Transformer::fromMappedParent(
								$property,
								$entity->getActualValue(),
							));
						} catch (Exceptions\InvalidState $ex) {
							$this->logger->warning(
								'State value could not be converted from mapped parent',
								[
									'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
									'type' => 'event-writer',
									'exception' => BootstrapHelpers\Logger::buildException($ex),
									'connector' => [
										'id' => $accessory->getDevice()->getConnector()->getPlainId(),
									],
									'device' => [
										'id' => $accessory->getDevice()->getPlainId(),
									],
									'channel' => [
										'id' => $service->getChannel()?->getPlainId(),
									],
									'property' => [
										'id' => $characteristic->getProperty()->getPlainId(),
									],
									'hap' => $accessory->toHap(),
								],
							);

							return;
						}
					} elseif (
						$entity instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
						&& $property instanceof DevicesEntities\Channels\Properties\Variable
					) {
						$characteristic->setValue($entity->getValue());
					}

					if (!$characteristic->isVirtual()) {
						$this->subscriber->publish(
							intval($accessory->getAid()),
							intval($accessory->getIidManager()->getIid($characteristic)),
							Protocol\Transformer::toClient(
								$characteristic->getProperty(),
								$characteristic->getDataType(),
								$characteristic->getValidValues(),
								$characteristic->getMaxLength(),
								$characteristic->getMinValue(),
								$characteristic->getMaxValue(),
								$characteristic->getMinStep(),
								$characteristic->getValue(),
							),
							$characteristic->immediateNotify(),
						);
					} else {
						foreach ($service->getCharacteristics() as $serviceCharacteristic) {
							$this->subscriber->publish(
								intval($accessory->getAid()),
								intval($accessory->getIidManager()->getIid($serviceCharacteristic)),
								Protocol\Transformer::toClient(
									$serviceCharacteristic->getProperty(),
									$serviceCharacteristic->getDataType(),
									$serviceCharacteristic->getValidValues(),
									$serviceCharacteristic->getMaxLength(),
									$serviceCharacteristic->getMinValue(),
									$serviceCharacteristic->getMaxValue(),
									$serviceCharacteristic->getMinStep(),
									$serviceCharacteristic->getValue(),
								),
								$serviceCharacteristic->immediateNotify(),
							);
						}
					}

					$this->logger->debug(
						'Processed received property message',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'exchange-writer',
							'connector' => [
								'id' => $accessory->getDevice()->getConnector()->getPlainId(),
							],
							'device' => [
								'id' => $accessory->getDevice()->getPlainId(),
							],
							'channel' => [
								'id' => $service->getChannel()?->getPlainId(),
							],
							'property' => [
								'id' => $characteristic->getProperty()?->getPlainId(),
							],
							'hap' => $accessory->toHap(),
						],
					);

					return;
				}
			}
		}
	}

}
