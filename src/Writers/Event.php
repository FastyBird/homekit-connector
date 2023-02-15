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

use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use Nette;
use Psr\Log;
use Symfony\Component\EventDispatcher;
use function array_key_exists;
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

	/** @var array<string, Entities\HomeKitConnector> */
	private array $connectors = [];

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public static function getSubscribedEvents(): array
	{
		return [
			DevicesEvents\StateEntityCreated::class => 'stateChanged',
			DevicesEvents\StateEntityUpdated::class => 'stateChanged',
		];
	}

	public function connect(Entities\HomeKitConnector $connector): void
	{
		$this->connectors[$connector->getPlainId()] = $connector;
	}

	public function disconnect(Entities\HomeKitConnector $connector): void
	{
		unset($this->connectors[$connector->getPlainId()]);
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function stateChanged(DevicesEvents\StateEntityCreated|DevicesEvents\StateEntityUpdated $event): void
	{
		$property = $event->getProperty();

		if (
			$property instanceof DevicesEntities\Devices\Properties\Mapped
			|| $property instanceof DevicesEntities\Channels\Properties\Mapped
		) {
			if ($property instanceof DevicesEntities\Devices\Properties\Mapped) {
				if (!array_key_exists($property->getDevice()->getConnector()->getPlainId(), $this->connectors)) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($property->getDevice()->getId());
			} else {
				if (!array_key_exists(
					$property->getChannel()->getDevice()->getConnector()->getPlainId(),
					$this->connectors,
				)) {
					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($property->getChannel()->getDevice()->getId());
			}

			if (!$accessory instanceof Entities\Protocol\Device) {
				$this->logger->warning(
					'Accessory for received property message was not found in accessory driver',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'event-writer',
						'group' => 'writer',
						'state' => $event->getState()->toArray(),
					],
				);

				return;
			}

			$this->processProperty($property, $event, $accessory);
		}
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		DevicesEntities\Devices\Properties\Mapped|DevicesEntities\Channels\Properties\Mapped $property,
		DevicesEvents\StateEntityCreated|DevicesEvents\StateEntityUpdated $event,
		Entities\Protocol\Device $accessory,
	): void
	{
		foreach ($accessory->getServices() as $service) {
			foreach ($service->getCharacteristics() as $characteristic) {
				if (
					$characteristic->getProperty() !== null
					&& $characteristic->getProperty()->getId()->equals($property->getId())
				) {
					$characteristic->setActualValue($event->getState()->getActualValue());

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
							$characteristic->getActualValue(),
						),
						$characteristic->immediateNotify(),
					);

					$this->logger->debug(
						'Processed received property message',
						[
							'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
							'type' => 'event-writer',
							'group' => 'writer',
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
							'hap' => [
								'accessory' => $service->toHap(),
								'service' => $service->toHap(),
								'characteristic' => $characteristic->toHap(),
							],
						],
					);

					return;
				}
			}
		}
	}

}
