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
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
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
		private readonly DevicesModels\Channels\Properties\PropertiesRepository $channelPropertiesRepository,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
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

	public function connect(Entities\HomeKitConnector $connector, array $servers): void
	{
		$this->connectors[$connector->getPlainId()] = $connector;
	}

	public function disconnect(Entities\HomeKitConnector $connector, array $servers): void
	{
		unset($this->connectors[$connector->getPlainId()]);
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function stateChanged(DevicesEvents\StateEntityCreated|DevicesEvents\StateEntityUpdated $event): void
	{
		$property = $event->getProperty();

		foreach ($this->findChildren($property) as $child) {
			if ($child instanceof DevicesEntities\Channels\Properties\Mapped) {
				if (
					!array_key_exists(
						$child->getChannel()->getDevice()->getConnector()->getPlainId(),
						$this->connectors,
					)
				) {
					continue;
				}

				$accessory = $this->accessoryDriver->findAccessory($child->getChannel()->getDevice()->getId());

			} else {
				continue;
			}

			if (!$accessory instanceof Entities\Protocol\Device) {
				$this->logger->warning(
					'Accessory for received property message was not found in accessory driver',
					[
						'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
						'type' => 'event-writer',
						'state' => $event->getState()->toArray(),
					],
				);

				continue;
			}

			$this->processProperty($child, $event, $accessory);
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		DevicesEntities\Channels\Properties\Mapped $property,
		DevicesEvents\StateEntityCreated|DevicesEvents\StateEntityUpdated $event,
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

	/**
	 * @return array<DevicesEntities\Devices\Properties\Property|DevicesEntities\Channels\Properties\Property>
	 *
	 * @throws DevicesExceptions\InvalidState
	 */
	private function findChildren(
		// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
		MetadataEntities\DevicesModule\DynamicProperty|DevicesEntities\Connectors\Properties\Dynamic|DevicesEntities\Devices\Properties\Dynamic|DevicesEntities\Channels\Properties\Dynamic $property,
	): array
	{
		if ($property instanceof MetadataEntities\DevicesModule\ChannelDynamicProperty) {
			$findPropertyQuery = new DevicesQueries\FindChannelProperties();
			$findPropertyQuery->byParentId($property->getId());

			return $this->channelPropertiesRepository->findAllBy(
				$findPropertyQuery,
				DevicesEntities\Channels\Properties\Mapped::class,
			);
		}

		return [];
	}

}
