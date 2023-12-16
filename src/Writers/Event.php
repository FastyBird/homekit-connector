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

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Symfony\Component\EventDispatcher;

/**
 * Event based properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Event extends Periodic implements Writer, EventDispatcher\EventSubscriberInterface
{

	public const NAME = 'event';

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\DevicePropertyStateEntityCreated::class => 'stateChanged',
			DevicesEvents\DevicePropertyStateEntityUpdated::class => 'stateChanged',
			DevicesEvents\ChannelPropertyStateEntityCreated::class => 'stateChanged',
			DevicesEvents\ChannelPropertyStateEntityUpdated::class => 'stateChanged',
		];
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 */
	public function stateChanged(
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		DevicesEvents\DevicePropertyStateEntityCreated|DevicesEvents\DevicePropertyStateEntityUpdated|DevicesEvents\ChannelPropertyStateEntityCreated|DevicesEvents\ChannelPropertyStateEntityUpdated $event,
	): void
	{
		if (
			$event instanceof DevicesEvents\DevicePropertyStateEntityCreated
			|| $event instanceof DevicesEvents\DevicePropertyStateEntityUpdated
		) {
			$findPropertiesQuery = new DevicesQueries\Configuration\FindDeviceMappedProperties();
			$findPropertiesQuery->forParent($event->getProperty());

			$properties = $this->devicesPropertiesConfigurationRepository->findAllBy(
				$findPropertiesQuery,
				MetadataDocuments\DevicesModule\DeviceMappedProperty::class,
			);

			foreach ($properties as $property) {
				$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
				$findDeviceQuery->byId($property->getDevice());
				$findDeviceQuery->byType(Entities\HomeKitDevice::TYPE);

				$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

				if ($device === null) {
					return;
				}

				if (!$device->getConnector()->equals($this->connector->getId())) {
					return;
				}

				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\WriteDevicePropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'property' => $property->getId(),
						],
					),
				);
			}
		} else {
			$findPropertiesQuery = new DevicesQueries\Configuration\FindChannelMappedProperties();
			$findPropertiesQuery->forParent($event->getProperty());

			$properties = $this->channelsPropertiesConfigurationRepository->findAllBy(
				$findPropertiesQuery,
				MetadataDocuments\DevicesModule\ChannelMappedProperty::class,
			);

			foreach ($properties as $property) {
				$findChannelQuery = new DevicesQueries\Configuration\FindChannels();
				$findChannelQuery->byId($property->getChannel());
				$findChannelQuery->byType(Entities\HomeKitChannel::TYPE);

				$channel = $this->channelsConfigurationRepository->findOneBy($findChannelQuery);

				if ($channel === null) {
					return;
				}

				$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
				$findDeviceQuery->byId($property->getChannel());
				$findDeviceQuery->byType(Entities\HomeKitDevice::TYPE);

				$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

				if ($device === null) {
					return;
				}

				if (!$device->getConnector()->equals($this->connector->getId())) {
					return;
				}

				$this->queue->append(
					$this->entityHelper->create(
						Entities\Messages\WriteChannelPropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'channel' => $property->getChannel(),
							'property' => $property->getId(),
						],
					),
				);
			}
		}
	}

}
