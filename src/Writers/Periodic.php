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
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use React\EventLoop;
use function array_key_exists;
use function assert;
use function in_array;

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
	// phpcs:ignore SlevomatCodingStandard.Files.LineLength.LineTooLong
	/** @var array<string, array<string, MetadataDocuments\DevicesModule\DeviceVariableProperty|MetadataDocuments\DevicesModule\DeviceMappedProperty|MetadataDocuments\DevicesModule\ChannelVariableProperty|MetadataDocuments\DevicesModule\ChannelMappedProperty>>  */
	private array $properties = [];

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface> */
	private array $processedProperties = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	public function __construct(
		protected readonly MetadataDocuments\DevicesModule\Connector $connector,
		protected readonly Helpers\Entity $entityHelper,
		protected readonly Queue\Queue $queue,
		protected readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		protected readonly DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		protected readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		protected readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly DevicesUtilities\DevicePropertiesStates $devicePropertiesStatesManager,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStatesManager,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidState
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedProperties = [];

		$findDevicesQuery = new DevicesQueries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);
		$findDevicesQuery->byType(Entities\HomeKitDevice::TYPE);

		foreach ($this->devicesConfigurationRepository->findAllBy($findDevicesQuery) as $device) {
			$this->devices[$device->getId()->toString()] = $device;

			if (!array_key_exists($device->getId()->toString(), $this->properties)) {
				$this->properties[$device->getId()->toString()] = [];
			}

			$findDevicePropertiesQuery = new DevicesQueries\Configuration\FindDeviceProperties();
			$findDevicePropertiesQuery->forDevice($device);

			$properties = $this->devicesPropertiesConfigurationRepository->findAllBy($findDevicePropertiesQuery);

			foreach ($properties as $property) {
				if (
					$property instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty
					|| $property instanceof MetadataDocuments\DevicesModule\DeviceMappedProperty
				) {
					$this->properties[$device->getId()->toString()][$property->getId()->toString()] = $property;
				}
			}

			$findChannelsQuery = new DevicesQueries\Configuration\FindChannels();
			$findChannelsQuery->forDevice($device);
			$findChannelsQuery->byType(Entities\HomeKitChannel::TYPE);

			$channels = $this->channelsConfigurationRepository->findAllBy($findChannelsQuery);

			foreach ($channels as $channel) {
				$findChannelPropertiesQuery = new DevicesQueries\Configuration\FindChannelProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				$properties = $this->channelsPropertiesConfigurationRepository->findAllBy($findChannelPropertiesQuery);

				foreach ($properties as $property) {
					if (
						$property instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty
						|| $property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty
					) {
						$this->properties[$device->getId()->toString()][$property->getId()->toString()] = $property;
					}
				}
			}
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
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function handleCommunication(): void
	{
		foreach ($this->devices as $device) {
			if (!in_array($device->getId()->toString(), $this->processedDevices, true)) {
				$this->processedDevices[] = $device->getId()->toString();

				if ($this->writeProperty($device)) {
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
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 */
	private function writeProperty(MetadataDocuments\DevicesModule\Device $device): bool
	{
		$now = $this->dateTimeFactory->getNow();

		$accessory = $this->accessoryDriver->findAccessory($device->getId());

		if ($accessory === null) {
			return true;
		}

		foreach ($this->properties[$device->getId()->toString()] as $property) {
			$debounce = array_key_exists($property->getId()->toString(), $this->processedProperties)
				? $this->processedProperties[$property->getId()->toString()]
				: false;

			if (
				$debounce !== false
				&& (float) $now->format('Uv') - (float) $debounce->format('Uv') < self::HANDLER_DEBOUNCE_INTERVAL
			) {
				continue;
			}

			$this->processedProperties[$property->getId()->toString()] = $now;

			$characteristicValue = null;

			if ($property instanceof MetadataDocuments\DevicesModule\DeviceMappedProperty) {
				$state = $this->devicePropertiesStatesManager->readValue($property);

				if ($state === null) {
					continue;
				}

				$characteristicValue = $state->getExpectedValue() ?? $state->getActualValue();

			} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelMappedProperty) {
				$state = $this->channelPropertiesStatesManager->readValue($property);

				if ($state === null) {
					continue;
				}

				$characteristicValue = $state->getExpectedValue() ?? $state->getActualValue();

			} elseif ($property instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty) {
				$findDevicePropertyQuery = new DevicesQueries\Configuration\FindDeviceVariableProperties();
				$findDevicePropertyQuery->byId($property->getId());

				$property = $this->devicesPropertiesConfigurationRepository->findOneBy(
					$findDevicePropertyQuery,
					MetadataDocuments\DevicesModule\DeviceVariableProperty::class,
				);
				assert($property instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty);

				$characteristicValue = $property->getValue();

			} elseif ($property instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty) {
				$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelVariableProperties();
				$findChannelPropertyQuery->byId($property->getId());

				$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
					$findChannelPropertyQuery,
					MetadataDocuments\DevicesModule\ChannelVariableProperty::class,
				);
				assert($property instanceof MetadataDocuments\DevicesModule\ChannelVariableProperty);

				$characteristicValue = $property->getValue();
			}

			foreach ($accessory->getServices() as $service) {
				if ($service->getChannel() !== null) {
					foreach ($service->getCharacteristics() as $characteristic) {
						if (
							$characteristic->getProperty() !== null
							&& $characteristic->getProperty()->getId()->equals($property->getId())
						) {
							if ($characteristic->getValue() === $characteristicValue) {
								return true;
							}

							if (
								$property instanceof MetadataDocuments\DevicesModule\DeviceVariableProperty
								|| $property instanceof MetadataDocuments\DevicesModule\DeviceMappedProperty
							) {
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
							} else {
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

							return true;
						}
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
