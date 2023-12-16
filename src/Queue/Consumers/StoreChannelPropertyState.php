<?php declare(strict_types = 1);

/**
 * StoreChannelPropertyState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           30.11.23
 */

namespace FastyBird\Connector\HomeKit\Queue\Consumers;

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Exchange\Documents as ExchangeEntities;
use FastyBird\Library\Exchange\Exceptions as ExchangeExceptions;
use FastyBird\Library\Exchange\Publisher as ExchangePublisher;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Metadata\Utilities as MetadataUtilities;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Nette\Utils;
use function assert;

/**
 * Store channel property state message consumer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreChannelPropertyState implements Queue\Consumer
{

	use Nette\SmartObject;

	public function __construct(
		private readonly bool $useExchange,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesUtilities\Database $databaseHelper,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStatesManager,
		private readonly ExchangeEntities\DocumentFactory $entityFactory,
		private readonly ExchangePublisher\Publisher $publisher,
	)
	{
	}

	/**
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws ExchangeExceptions\InvalidArgument
	 * @throws ExchangeExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	public function consume(Entities\Messages\Entity $entity): bool
	{
		if (!$entity instanceof Entities\Messages\StoreChannelPropertyState) {
			return false;
		}

		$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
		$findDeviceQuery->byConnectorId($entity->getConnector());
		$findDeviceQuery->byId($entity->getDevice());
		$findDeviceQuery->byType(Entities\HomeKitDevice::TYPE);

		$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

		if ($device === null) {
			$this->logger->error(
				'Device could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelQuery = new DevicesQueries\Configuration\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byId($entity->getChannel());
		$findChannelQuery->byType(Entities\HomeKitChannel::TYPE);

		$channel = $this->channelsConfigurationRepository->findOneBy($findChannelQuery);

		if ($channel === null) {
			$this->logger->error(
				'Device channel could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byId($entity->getProperty());

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy($findChannelPropertyQuery);

		if ($property === null) {
			$this->logger->error(
				'Device channel property could not be loaded',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $entity->getConnector()->toString(),
					],
					'device' => [
						'id' => $entity->getDevice()->toString(),
					],
					'channel' => [
						'id' => $entity->getChannel()->toString(),
					],
					'property' => [
						'id' => $entity->getProperty()->toString(),
					],
					'data' => $entity->toArray(),
				],
			);

			return true;
		}

		$valueToStore = $entity->getValue();
		$valueToStore = MetadataUtilities\ValueHelper::normalizeValue(
			$property->getDataType(),
			$valueToStore,
			$property->getFormat(),
			$property->getInvalid(),
		);

		if ($property instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
			$this->databaseHelper->transaction(
				function () use ($valueToStore, $property): void {
					$findPropertyQuery = new DevicesQueries\Entities\FindChannelVariableProperties();
					$findPropertyQuery->byId($property->getId());

					$property = $this->channelsPropertiesRepository->findOneBy(
						$findPropertyQuery,
						DevicesEntities\Channels\Properties\Variable::class,
					);
					assert($property instanceof DevicesEntities\Channels\Properties\Variable);

					$this->channelsPropertiesManager->update(
						$property,
						Utils\ArrayHash::from([
							'value' => MetadataUtilities\ValueHelper::flattenValue($valueToStore),
						]),
					);
				},
			);

		} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
			$this->channelPropertiesStatesManager->setValue($property, Utils\ArrayHash::from([
				DevicesStates\Property::ACTUAL_VALUE_FIELD => MetadataUtilities\ValueHelper::flattenValue(
					$valueToStore,
				),
				DevicesStates\Property::VALID_FIELD => true,
			]));
		} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty) {
			$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
			$findChannelPropertyQuery->byId($property->getParent());

			$parent = $this->channelsPropertiesConfigurationRepository->findOneBy($findChannelPropertyQuery);

			if ($parent instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
				try {
					if ($this->useExchange) {
						$this->publisher->publish(
							MetadataTypes\ConnectorSource::get(
								MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							),
							MetadataTypes\RoutingKey::get(
								MetadataTypes\RoutingKey::CHANNEL_PROPERTY_ACTION,
							),
							$this->entityFactory->create(
								Utils\Json::encode([
									'action' => MetadataTypes\PropertyAction::ACTION_SET,
									'device' => $device->getId()->toString(),
									'channel' => $channel->getId()->toString(),
									'property' => $property->getId()->toString(),
									'expected_value' => MetadataUtilities\ValueHelper::flattenValue($valueToStore),
								]),
								MetadataTypes\RoutingKey::get(
									MetadataTypes\RoutingKey::CHANNEL_PROPERTY_ACTION,
								),
							),
						);
					} else {
						$this->channelPropertiesStatesManager->writeValue($property, Utils\ArrayHash::from([
							DevicesStates\Property::EXPECTED_VALUE_FIELD => $entity->getValue(),
							DevicesStates\Property::PENDING_FIELD => true,
						]));
					}
				} catch (DevicesExceptions\InvalidState | Utils\JsonException $ex) {
					$this->logger->warning(
						'State value could not be converted to mapped parent',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'store-channel-property-state-message-consumer',
							'exception' => BootstrapHelpers\Logger::buildException($ex),
							'connector' => [
								'id' => $entity->getConnector()->toString(),
							],
							'device' => [
								'id' => $entity->getDevice()->toString(),
							],
							'channel' => [
								'id' => $entity->getChannel()->toString(),
							],
							'property' => [
								'id' => $entity->getProperty()->toString(),
							],
							'data' => $entity->toArray(),
						],
					);

					return true;
				}
			} elseif ($parent instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
				$this->databaseHelper->transaction(function () use ($entity, $parent): void {
					$findPropertyQuery = new DevicesQueries\Entities\FindChannelVariableProperties();
					$findPropertyQuery->byId($parent->getId());

					$property = $this->channelsPropertiesRepository->findOneBy(
						$findPropertyQuery,
						DevicesEntities\Channels\Properties\Variable::class,
					);

					if ($property !== null) {
						$this->channelsPropertiesManager->update(
							$property,
							Utils\ArrayHash::from([
								'value' => $entity->getValue(),
							]),
						);
					} else {
						$this->logger->error(
							'Mapped variable property could not be updated',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
								'type' => 'store-channel-property-state-message-consumer',
								'connector' => [
									'id' => $entity->getConnector()->toString(),
								],
								'device' => [
									'id' => $entity->getDevice()->toString(),
								],
								'channel' => [
									'id' => $entity->getChannel()->toString(),
								],
								'property' => [
									'id' => $entity->getProperty()->toString(),
								],
								'data' => $entity->toArray(),
							],
						);
					}
				});
			}
		}

		$this->logger->debug(
			'Consumed store device state message',
			[
				'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
				'type' => 'store-channel-property-state-message-consumer',
				'connector' => [
					'id' => $entity->getConnector()->toString(),
				],
				'device' => [
					'id' => $entity->getDevice()->toString(),
				],
				'channel' => [
					'id' => $entity->getChannel()->toString(),
				],
				'property' => [
					'id' => $entity->getProperty()->toString(),
				],
				'data' => $entity->toArray(),
			],
		);

		return true;
	}

}
