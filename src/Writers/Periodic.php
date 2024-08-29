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
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use React\EventLoop;
use TypeError;
use ValueError;
use function array_key_exists;
use function array_merge;
use function assert;
use function in_array;
use function is_bool;
use function React\Async\async;
use function React\Async\await;

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

	/** @var array<string, Documents\Devices\Device>  */
	private array $devices = [];

	/** @var array<string, array<string, DevicesDocuments\Devices\Properties\Property|DevicesDocuments\Channels\Properties\Property>>  */
	private array $properties = [];

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface> */
	private array $processedProperties = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	public function __construct(
		protected readonly Documents\Connectors\Connector $connector,
		protected readonly Helpers\MessageBuilder $messageBuilder,
		protected readonly Queue\Queue $queue,
		protected readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		protected readonly DevicesModels\Configuration\Devices\Properties\Repository $devicesPropertiesConfigurationRepository,
		protected readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		protected readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly DevicesModels\States\Async\DevicePropertiesManager $devicePropertiesStatesManager,
		private readonly DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
		private readonly DateTimeFactory\Clock $clock,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedProperties = [];

		$findDevicesQuery = new Queries\Configuration\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		$devices = $this->devicesConfigurationRepository->findAllBy(
			$findDevicesQuery,
			Documents\Devices\Device::class,
		);

		foreach ($devices as $device) {
			$this->devices[$device->getId()->toString()] = $device;

			if (!array_key_exists($device->getId()->toString(), $this->properties)) {
				$this->properties[$device->getId()->toString()] = [];
			}

			$findDevicePropertiesQuery = new Queries\Configuration\FindDeviceProperties();
			$findDevicePropertiesQuery->forDevice($device);

			$properties = $this->devicesPropertiesConfigurationRepository->findAllBy($findDevicePropertiesQuery);

			foreach ($properties as $property) {
				$this->properties[$device->getId()->toString()][$property->getId()->toString()] = $property;
			}

			$findChannelsQuery = new Queries\Configuration\FindChannels();
			$findChannelsQuery->forDevice($device);

			$channels = $this->channelsConfigurationRepository->findAllBy(
				$findChannelsQuery,
				Documents\Channels\Channel::class,
			);

			foreach ($channels as $channel) {
				$findChannelPropertiesQuery = new DevicesQueries\Configuration\FindChannelProperties();
				$findChannelPropertiesQuery->forChannel($channel);

				$properties = $this->channelsPropertiesConfigurationRepository->findAllBy($findChannelPropertiesQuery);

				foreach ($properties as $property) {
					$this->properties[$device->getId()->toString()][$property->getId()->toString()] = $property;
				}
			}
		}

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			async(function (): void {
				$this->registerLoopHandler();
			}),
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
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
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
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function writeProperty(Documents\Devices\Device $device): bool
	{
		$now = $this->clock->getNow();

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

			$state = null;

			if ($property instanceof DevicesDocuments\Devices\Properties\Mapped) {
				$state = await($this->devicePropertiesStatesManager->read(
					$property,
					MetadataTypes\Sources\Connector::HOMEKIT,
				));

				if (is_bool($state)) {
					// Property state was requested
					if ($state === true) {
						return true;
					}

					// Requesting property state failed
					continue;
				} elseif (
					$state instanceof DevicesDocuments\States\Devices\Properties\Property
					&& $state->isValid()
				) {
					// Property state is set
					$characteristicValue = $state->getRead()->getExpectedValue() ?? $state->getRead()->getActualValue();
				}
			} elseif ($property instanceof DevicesDocuments\Channels\Properties\Mapped) {
				$state = await($this->channelPropertiesStatesManager->read(
					$property,
					MetadataTypes\Sources\Connector::HOMEKIT,
				));

				if (is_bool($state)) {
					// Property state was requested
					if ($state === true) {
						return true;
					}

					// Requesting property state failed
					continue;
				} elseif (
					$state instanceof DevicesDocuments\States\Channels\Properties\Property
					&& $state->isValid()
				) {
					// Property state is set
					$characteristicValue = $state->getRead()->getExpectedValue() ?? $state->getRead()->getActualValue();
				}
			} elseif ($property instanceof DevicesDocuments\Devices\Properties\Dynamic) {
				$state = await($this->devicePropertiesStatesManager->read(
					$property,
					MetadataTypes\Sources\Connector::HOMEKIT,
				));

				if (is_bool($state)) {
					// Property state was requested
					if ($state === true) {
						return true;
					}

					// Requesting property state failed
					continue;
				} elseif (
					$state instanceof DevicesDocuments\States\Devices\Properties\Property
					&& $state->isValid()
				) {
					// Property state is set
					$characteristicValue = $state->getGet()->getExpectedValue() ?? $state->getGet()->getActualValue();
				}
			} elseif ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
				$state = await($this->channelPropertiesStatesManager->read(
					$property,
					MetadataTypes\Sources\Connector::HOMEKIT,
				));

				if (is_bool($state)) {
					// Property state was requested
					if ($state === true) {
						return true;
					}

					// Requesting property state failed
					continue;
				} elseif (
					$state instanceof DevicesDocuments\States\Channels\Properties\Property
					&& $state->isValid()
				) {
					// Property state is set
					$characteristicValue = $state->getGet()->getExpectedValue() ?? $state->getGet()->getActualValue();
				}
			} elseif ($property instanceof DevicesDocuments\Devices\Properties\Variable) {
				$findDevicePropertyQuery = new Queries\Configuration\FindDeviceVariableProperties();
				$findDevicePropertyQuery->byId($property->getId());

				$property = $this->devicesPropertiesConfigurationRepository->findOneBy(
					$findDevicePropertyQuery,
					DevicesDocuments\Devices\Properties\Variable::class,
				);
				assert($property instanceof DevicesDocuments\Devices\Properties\Variable);

				$characteristicValue = $property->getValue();

			} elseif ($property instanceof DevicesDocuments\Channels\Properties\Variable) {
				$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelVariableProperties();
				$findChannelPropertyQuery->byId($property->getId());

				$property = $this->channelsPropertiesConfigurationRepository->findOneBy(
					$findChannelPropertyQuery,
					DevicesDocuments\Channels\Properties\Variable::class,
				);
				assert($property instanceof DevicesDocuments\Channels\Properties\Variable);

				$characteristicValue = $property->getValue();
			}

			if ($characteristicValue === null) {
				continue;
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

							if ($property instanceof DevicesDocuments\Devices\Properties\Variable) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteDevicePropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'property' => $property->getId(),
										],
									),
								);
							} elseif (
								$property instanceof DevicesDocuments\Devices\Properties\Dynamic
								&& $state !== null
							) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteDevicePropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'property' => $property->getId(),
											'state' => array_merge(
												$state->getGet()->toArray(),
												[
													'id' => $state->getId(),
													'valid' => $state->isValid(),
													'pending' => $state->getPending() instanceof DateTimeInterface
														? $state->getPending()->format(DateTimeInterface::ATOM)
														: $state->getPending(),
												],
											),
										],
									),
								);
							} elseif (
								$property instanceof DevicesDocuments\Devices\Properties\Mapped
								&& $state !== null
							) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteDevicePropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'property' => $property->getId(),
											'state' => array_merge(
												$state->getRead()->toArray(),
												[
													'id' => $state->getId(),
													'valid' => $state->isValid(),
													'pending' => $state->getPending() instanceof DateTimeInterface
														? $state->getPending()->format(DateTimeInterface::ATOM)
														: $state->getPending(),
												],
											),
										],
									),
								);
							} elseif ($property instanceof DevicesDocuments\Channels\Properties\Variable) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteChannelPropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'channel' => $property->getChannel(),
											'property' => $property->getId(),
										],
									),
								);
							} elseif (
								$property instanceof DevicesDocuments\Channels\Properties\Dynamic
								&& $state !== null
							) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteChannelPropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'channel' => $property->getChannel(),
											'property' => $property->getId(),
											'state' => array_merge(
												$state->getGet()->toArray(),
												[
													'id' => $state->getId(),
													'valid' => $state->isValid(),
													'pending' => $state->getPending() instanceof DateTimeInterface
														? $state->getPending()->format(DateTimeInterface::ATOM)
														: $state->getPending(),
												],
											),
										],
									),
								);
							} elseif (
								$property instanceof DevicesDocuments\Channels\Properties\Mapped
								&& $state !== null
							) {
								$this->queue->append(
									$this->messageBuilder->create(
										Queue\Messages\WriteChannelPropertyState::class,
										[
											'connector' => $device->getConnector(),
											'device' => $device->getId(),
											'channel' => $property->getChannel(),
											'property' => $property->getId(),
											'state' => array_merge(
												$state->getRead()->toArray(),
												[
													'id' => $state->getId(),
													'valid' => $state->isValid(),
													'pending' => $state->getPending() instanceof DateTimeInterface
														? $state->getPending()->format(DateTimeInterface::ATOM)
														: $state->getPending(),
												],
											),
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

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws Exceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws MetadataExceptions\MalformedInput
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	private function registerLoopHandler(): void
	{
		$this->handlerTimer = $this->eventLoop->addTimer(
			self::HANDLER_PROCESSING_INTERVAL,
			async(function (): void {
				$this->handleCommunication();
			}),
		);
	}

}
