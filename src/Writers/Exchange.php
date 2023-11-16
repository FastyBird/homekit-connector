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
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Nette;
use Psr\EventDispatcher as PsrEventDispatcher;
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

	public function __construct(
		private readonly Entities\HomeKitConnector $connector,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesModels\Entities\Channels\ChannelsRepository $channelsRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly ExchangeConsumers\Container $consumer,
		private readonly PsrEventDispatcher\EventDispatcherInterface|null $dispatcher = null,
	)
	{
	}

	public function connect(): void
	{
		$this->consumer->enable(self::class);
	}

	public function disconnect(): void
	{
		$this->consumer->disable(self::class);
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
				$findDeviceQuery = new Queries\Entities\FindDevices();
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

				if (!$device->getConnector()->getId()->equals($this->connector->getId())) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($entity->getDevice());
			} else {
				$findChannelQuery = new Queries\Entities\FindChannels();
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

				if (!$channel->getDevice()->getConnector()->getId()->equals($this->connector->getId())) {
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
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		MetadataDocuments\DevicesModule\Property $entity,
		Entities\Protocol\Device $accessory,
	): void
	{
		if (
			!$entity instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
			&& !$entity instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty
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
					$findPropertyQuery = new DevicesQueries\Entities\FindChannelProperties();
					$findPropertyQuery->byId($entity->getId());

					$property = $this->channelsPropertiesRepository->findOneBy($findPropertyQuery);

					if (
						$entity instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
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
									'type' => 'exchange-writer',
									'exception' => BootstrapHelpers\Logger::buildException($ex),
									'connector' => [
										'id' => $accessory->getDevice()->getConnector()->getId()->toString(),
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
					} elseif (
						$entity instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
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
								'id' => $accessory->getDevice()->getConnector()->getId()->toString(),
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
