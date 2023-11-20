<?php declare(strict_types = 1);

/**
 * Periodic.php
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

use DateTimeInterface;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use React\EventLoop;
use function array_key_exists;
use function in_array;
use function intval;

/**
 * Periodic properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
abstract class Periodic
{

	private const HANDLER_START_DELAY = 5.0;

	private const HANDLER_DEBOUNCE_INTERVAL = 2_500.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var array<string, MetadataDocuments\DevicesModule\Device>  */
	private array $devices = [];

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface> */
	private array $processedProperties = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	/**
	 * @param DevicesModels\Configuration\Devices\Repository<MetadataDocuments\DevicesModule\Device> $devicesConfigurationRepository
	 * @param DevicesModels\Configuration\Channels\Properties\Repository<MetadataDocuments\DevicesModule\ChannelDynamicProperty|MetadataDocuments\DevicesModule\ChannelVariableProperty|MetadataDocuments\DevicesModule\ChannelMappedProperty> $channelsPropertiesConfigurationRepository
	 */
	public function __construct(
		protected readonly MetadataDocuments\DevicesModule\Connector $connector,
		protected readonly Protocol\Driver $accessoryDriver,
		protected readonly Clients\Subscriber $subscriber,
		protected readonly HomeKit\Logger $logger,
		protected readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		protected readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		protected readonly DevicesUtilities\ChannelPropertiesStates $channelsPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedProperties = [];

		$findDevicesQuery = new DevicesQueries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		foreach ($this->devicesConfigurationRepository->findAllBy($findDevicesQuery) as $device) {
			$this->devices[$device->getId()->toString()] = $device;
		}

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);
	}

	public function disconnect(): void
	{
		if ($this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);

			$this->handlerTimer = null;
		}
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function handleCommunication(): void
	{
		foreach ($this->devices as $device) {
			$accessory = $this->accessoryDriver->findAccessory($device->getId());

			if (!$accessory instanceof Entities\Protocol\Device) {
				continue;
			}

			if (!in_array($device->getId()->toString(), $this->processedDevices, true)) {
				$this->processedDevices[] = $device->getId()->toString();

				if ($this->writeCharacteristic($accessory)) {
					$this->registerLoopHandler();

					return;
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function writeCharacteristic(
		Entities\Protocol\Device $accessory,
	): bool
	{
		$now = $this->dateTimeFactory->getNow();

		foreach ($accessory->getServices() as $service) {
			if ($service->getChannel() !== null) {
				foreach ($service->getCharacteristics() as $characteristic) {
					$property = $characteristic->getProperty();

					if (
						$property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty
						|| $property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
					) {
						$debounce = array_key_exists($property->getId()->toString(), $this->processedProperties)
							? $this->processedProperties[$property->getId()->toString()]
							: false;

						if (
							$debounce !== false
							&& (float) $now->format('Uv') - (float) $debounce->format(
								'Uv',
							) < self::HANDLER_DEBOUNCE_INTERVAL
						) {
							continue;
						}

						$this->processedProperties[$property->getId()->toString()] = $now;

						$characteristicValue = null;

						if ($property instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
							$state = $this->channelsPropertiesStates->readValue($property);

							if ($state === null) {
								continue;
							}

							$characteristicValue = $state->getExpectedValue() ?? $state->getActualValue();
						}

						if ($property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty) {
							$findPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
							$findPropertyQuery->byId($property->getParent());

							$parent = $this->channelsPropertiesConfigurationRepository->findOneBy($findPropertyQuery);

							if (!$parent instanceof MetadataDocuments\DevicesModule\ChannelDynamicProperty) {
								continue;
							}

							$state = $this->channelsPropertiesStates->readValue($property);

							if ($state === null) {
								continue;
							}

							$characteristicValue = $state->getExpectedValue() ?? $state->getActualValue();
						}

						if ($characteristic->getValue() === $characteristicValue) {
							continue;
						}

						$characteristic->setActualValue($characteristicValue);

						if (!$characteristic->isVirtual()) {
							$this->subscriber->publish(
								intval($accessory->getAid()),
								intval($accessory->getIidManager()->getIid($characteristic)),
								Protocol\Transformer::toClient(
									$property,
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
								'type' => 'periodic-writer',
								'connector' => [
									'id' => $accessory->getDevice()->getConnector()->toString(),
								],
								'device' => [
									'id' => $accessory->getDevice()->getId()->toString(),
								],
								'channel' => [
									'id' => $service->getChannel()->getId()->toString(),
								],
								'property' => [
									'id' => $property->getId()->toString(),
								],
								'hap' => $accessory->toHap(),
							],
						);

						return true;
					}
				}
			}
		}

		return false;
	}

	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			function (): void {
				$this->handleCommunication();
			},
		);
	}

}
