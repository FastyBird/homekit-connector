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

use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
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
			$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
			$findDeviceQuery->forConnector($this->connector);
			$findDeviceQuery->byId($event->getProperty()->getDevice());

			$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

			if ($device === null) {
				return;
			}

			if ($event->getProperty() instanceof DevicesDocuments\Devices\Properties\Mapped) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\WriteDevicePropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'property' => $event->getProperty()->getId(),
							'state' => $event->getRead()->toArray(),
						],
					),
				);
			} elseif ($event->getProperty() instanceof DevicesDocuments\Devices\Properties\Dynamic) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\WriteDevicePropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'property' => $event->getProperty()->getId(),
							'state' => $event->getGet()->toArray(),
						],
					),
				);
			}
		} else {
			$findChannelQuery = new DevicesQueries\Configuration\FindChannels();
			$findChannelQuery->byId($event->getProperty()->getChannel());

			$channel = $this->channelsConfigurationRepository->findOneBy($findChannelQuery);

			if ($channel === null) {
				return;
			}

			$findDeviceQuery = new DevicesQueries\Configuration\FindDevices();
			$findDeviceQuery->forConnector($this->connector);
			$findDeviceQuery->byId($channel->getDevice());

			$device = $this->devicesConfigurationRepository->findOneBy($findDeviceQuery);

			if ($device === null) {
				return;
			}

			if ($event->getProperty() instanceof DevicesDocuments\Channels\Properties\Mapped) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\WriteChannelPropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'channel' => $channel->getId(),
							'property' => $event->getProperty()->getId(),
							'state' => $event->getRead()->toArray(),
						],
					),
				);
			} elseif ($event->getProperty() instanceof DevicesDocuments\Channels\Properties\Dynamic) {
				$this->queue->append(
					$this->messageBuilder->create(
						Queue\Messages\WriteChannelPropertyState::class,
						[
							'connector' => $device->getConnector(),
							'device' => $device->getId(),
							'channel' => $channel->getId(),
							'property' => $event->getProperty()->getId(),
							'state' => $event->getGet()->toArray(),
						],
					),
				);
			}
		}
	}

}
