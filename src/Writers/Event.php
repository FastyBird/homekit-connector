<?php declare(strict_types = 1);

/**
 * Event.php
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

use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Symfony\Component\EventDispatcher;
use function intval;

/**
 * Event based properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Event implements Writer, EventDispatcher\EventSubscriberInterface
{

	use Nette\SmartObject;

	public const NAME = 'event';

	public function __construct(
		private readonly Entities\HomeKitConnector $connector,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelPropertiesRepository,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\ChannelPropertyStateEntityCreated::class => 'stateChanged',
			DevicesEvents\ChannelPropertyStateEntityUpdated::class => 'stateChanged',
		];
	}

	public function connect(): void
	{
		// Nothing to do here
	}

	public function disconnect(): void
	{
		// Nothing to do here
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function stateChanged(
		DevicesEvents\ChannelPropertyStateEntityCreated|DevicesEvents\ChannelPropertyStateEntityUpdated $event,
	): void
	{
		$property = $event->getProperty();

		$findPropertyQuery = new DevicesQueries\Entities\FindChannelMappedProperties();
		$findPropertyQuery->byId($property->getId());

		$property = $this->channelPropertiesRepository->findOneBy(
			$findPropertyQuery,
			DevicesEntities\Channels\Properties\Mapped::class,
		);

		if ($property === null) {
			return;
		}

		if (!$property->getChannel()->getDevice()->getConnector()->getId()->equals($this->connector->getId())) {
			return;
		}

		$accessory = $this->accessoryDriver->findAccessory($property->getChannel()->getDevice()->getId());

		if (!$accessory instanceof Entities\Protocol\Device) {
			$this->logger->warning(
				'Accessory for received property message was not found in accessory driver',
				[
					'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
					'type' => 'event-writer',
					'state' => $event->getState()->toArray(),
				],
			);

			return;
		}

		$this->processProperty($property, $accessory);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		DevicesEntities\Channels\Properties\Mapped $property,
		Entities\Protocol\Device $accessory,
	): void
	{
		$state = $this->channelPropertiesStates->readValue($property);

		if ($state === null) {
			return;
		}

		foreach ($accessory->getServices() as $service) {
			foreach ($service->getCharacteristics() as $characteristic) {
				if (
					$characteristic->getProperty() !== null
					&& $characteristic->getProperty()->getId()->equals($property->getId())
				) {
					try {
						$characteristic->setActualValue(Protocol\Transformer::fromMappedParent(
							$property,
							$state->getActualValue(),
						));
					} catch (Exceptions\InvalidState $ex) {
						$this->logger->warning(
							'State value could not be converted from mapped parent',
							[
								'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
								'type' => 'event-writer',
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
							'type' => 'event-writer',
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
