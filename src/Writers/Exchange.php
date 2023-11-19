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
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Psr\EventDispatcher as PsrEventDispatcher;
use React\EventLoop;
use function intval;

/**
 * Exchange based properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Exchange extends Periodic implements Writer, ExchangeConsumers\Consumer
{

	public const NAME = 'exchange';

	/**
	 * @param DevicesModels\Configuration\Devices\Repository<MetadataDocuments\DevicesModule\Device> $devicesConfigurationRepository
	 * @param DevicesModels\Configuration\Channels\Repository<MetadataDocuments\DevicesModule\Channel> $channelsConfigurationRepository
	 * @param DevicesModels\Configuration\Channels\Properties\Repository<MetadataDocuments\DevicesModule\ChannelDynamicProperty|MetadataDocuments\DevicesModule\ChannelVariableProperty|MetadataDocuments\DevicesModule\ChannelMappedProperty> $channelsPropertiesConfigurationRepository
	 */
	public function __construct(
		MetadataDocuments\DevicesModule\Connector $connector,
		Protocol\Driver $accessoryDriver,
		Clients\Subscriber $subscriber,
		HomeKit\Logger $logger,
		DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		DevicesUtilities\ChannelPropertiesStates $channelsPropertiesStates,
		DateTimeFactory\Factory $dateTimeFactory,
		EventLoop\LoopInterface $eventLoop,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly ExchangeConsumers\Container $consumer,
		private readonly PsrEventDispatcher\EventDispatcherInterface|null $dispatcher = null,
	)
	{
		parent::__construct(
			$connector,
			$accessoryDriver,
			$subscriber,
			$logger,
			$devicesConfigurationRepository,
			$channelsPropertiesConfigurationRepository,
			$channelsPropertiesStates,
			$dateTimeFactory,
			$eventLoop,
		);
	}

	public function connect(): void
	{
		parent::connect();

		$this->consumer->enable(self::class);
	}

	public function disconnect(): void
	{
		parent::disconnect();

		$this->consumer->disable(self::class);
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
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
		MetadataDocuments\Document|null $entity,
	): void
	{
		if (
			$entity instanceof MetadataDocuments\DevicesModule\DeviceMappedProperty
			|| $entity instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty
			|| $entity instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
			|| $entity instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
		) {
			if (
				$entity instanceof MetadataDocuments\DevicesModule\DeviceMappedProperty
				|| $entity instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty
			) {
				$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
				$findDeviceQuery->byId($entity->getDevice());

				$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

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

				if (!$device->getConnector()->equals($this->connector->getId())) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($device->getId());
			} else {
				$findChannelQuery = new DevicesQueries\Configuration\FindChannels();
				$findChannelQuery->byId($entity->getChannel());

				$channel = $this->channelsConfigurationRepository->findOneBy($findChannelQuery);

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

				$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
				$findDeviceQuery->byId($channel->getDevice());

				$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

				if ($device === null) {
					$this->logger->error(
						'Device for received channel property message could not be loaded',
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

				if (!$device->getConnector()->equals($this->connector->getId())) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($device->getId());
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
		} elseif ($entity instanceof MetadataDocuments\DevicesModule\ConnectorVariableProperty) {
			if (!$entity->getConnector()->equals($this->connector->getId())) {
				return;
			}

			if (
				$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED
				|| $entity->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION
			) {
				$this->dispatcher?->dispatch(
					new DevicesEvents\TerminateConnector(
						MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT),
						'Connector configuration changed, services have to restarted',
					),
				);
			}

			if ($entity->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY) {
				$this->dispatcher?->dispatch(
					new DevicesEvents\TerminateConnector(
						MetadataTypes\ConnectorSource::get(MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT),
						'Connector shared key changed, services have to restarted',
					),
				);
			}
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function processProperty(
		MetadataDocuments\DevicesModule\Property $entity,
		Entities\Protocol\Device $accessory,
	): void
	{
		if (
			!$entity instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
			&& !$entity instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
		) {
			return;
		}

		foreach ($accessory->getServices() as $service) {
			foreach ($service->getCharacteristics() as $characteristic) {
				if (
					$characteristic->getProperty() !== null
					&& $characteristic->getProperty()->getId()->equals($entity->getId())
				) {
					if ($entity instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty) {
						$findPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
						$findPropertyQuery->byId($entity->getParent());

						$parent = $this->channelsPropertiesConfigurationRepository->findOneBy($findPropertyQuery);

						if ($parent instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
							try {
								$characteristic->setActualValue(DevicesUtilities\ValueHelper::normalizeValue(
									$entity->getDataType(),
									$entity->getExpectedValue() ?? $entity->getActualValue(),
									$entity->getFormat(),
									$entity->getInvalid(),
								));
							} catch (Exceptions\InvalidState $ex) {
								$this->logger->warning(
									'State value could not be converted from mapped parent',
									[
										'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
										'type' => 'exchange-writer',
										'exception' => BootstrapHelpers\Logger::buildException($ex),
										'connector' => [
											'id' => $accessory->getDevice()->getConnector()->toString(),
										],
										'device' => [
											'id' => $accessory->getDevice()->getId()->toString(),
										],
										'channel' => [
											'id' => $service->getChannel()?->getId()->toString(),
										],
										'property' => [
											'id' => $characteristic->getProperty()->getId()->toString(),
										],
										'hap' => $accessory->toHap(),
									],
								);

								return;
							}
						} elseif ($parent instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
							$characteristic->setValue($entity->getValue());
						}
					} elseif ($entity instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
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
								'id' => $accessory->getDevice()->getConnector()->toString(),
							],
							'device' => [
								'id' => $accessory->getDevice()->getId()->toString(),
							],
							'channel' => [
								'id' => $service->getChannel()?->getId()->toString(),
							],
							'property' => [
								'id' => $characteristic->getProperty()?->getId()->toString(),
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
