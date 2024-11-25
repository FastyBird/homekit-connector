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
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Core\Tools\Helpers as ToolsHelpers;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Psr\EventDispatcher as PsrEventDispatcher;
use React\EventLoop;
use Symfony\Component\EventDispatcher;
use Throwable;

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

	public function __construct(
		Documents\Connectors\Connector $connector,
		Helpers\MessageBuilder $messageBuilder,
		Queue\Queue $queue,
		Protocol\Driver $accessoryDriver,
		HomeKit\Logger $logger,
		DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		DevicesModels\States\Async\DevicePropertiesManager $devicePropertiesStatesManager,
		DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
		DateTimeFactory\Clock $clock,
		EventLoop\LoopInterface $eventLoop,
		private readonly PsrEventDispatcher\EventDispatcherInterface|null $dispatcher = null,
	)
	{
		parent::__construct(
			$connector,
			$messageBuilder,
			$queue,
			$logger,
			$devicesConfigurationRepository,
			$devicesPropertiesConfigurationRepository,
			$channelsConfigurationRepository,
			$channelsPropertiesConfigurationRepository,
			$accessoryDriver,
			$devicePropertiesStatesManager,
			$channelPropertiesStatesManager,
			$clock,
			$eventLoop,
		);
	}

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\DevicePropertyStateEntityCreated::class => 'stateChanged',
			DevicesEvents\DevicePropertyStateEntityUpdated::class => 'stateChanged',
			DevicesEvents\ChannelPropertyStateEntityCreated::class => 'stateChanged',
			DevicesEvents\ChannelPropertyStateEntityUpdated::class => 'stateChanged',
			DevicesEvents\EntityUpdated::class => 'entityUpdated',
		];
	}

	public function stateChanged(
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		DevicesEvents\DevicePropertyStateEntityCreated|DevicesEvents\DevicePropertyStateEntityUpdated|DevicesEvents\ChannelPropertyStateEntityCreated|DevicesEvents\ChannelPropertyStateEntityUpdated $event,
	): void
	{
		try {
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
		} catch (Throwable $ex) {
			// Log caught exception
			$this->logger->error(
				'Characteristic value could not be prepared for writing',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'event-writer',
					'exception' => ToolsHelpers\Logger::buildException($ex),
				],
			);
		}
	}

	public function entityUpdated(DevicesEvents\EntityUpdated $event): void
	{
		$entity = $event->getEntity();

		if ($entity instanceof DevicesEntities\Connectors\Properties\Variable) {
			if (!$entity->getConnector()->getId()->equals($this->connector->getId())) {
				return;
			}

			if (
				$entity->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED->value
				|| $entity->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value
			) {
				$this->dispatcher?->dispatch(
					new DevicesEvents\RestartConnector(
						MetadataTypes\Sources\Connector::HOMEKIT,
						'Connector configuration changed, services have to be restarted',
					),
				);
			}

			if ($entity->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY->value) {
				$this->dispatcher?->dispatch(
					new DevicesEvents\RestartConnector(
						MetadataTypes\Sources\Connector::HOMEKIT,
						'Connector shared key changed, services have to be restarted',
					),
				);
			}
		}
	}

}
