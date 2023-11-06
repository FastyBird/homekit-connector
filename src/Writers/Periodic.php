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
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Bootstrap\Helpers as BootstrapHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
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
class Periodic implements Writer
{

	use Nette\SmartObject;

	public const NAME = 'periodic';

	private const HANDLER_START_DELAY = 5.0;

	private const HANDLER_DEBOUNCE_INTERVAL = 500.0;

	private const HANDLER_PROCESSING_INTERVAL = 0.01;

	/** @var array<string> */
	private array $processedDevices = [];

	/** @var array<string, DateTimeInterface> */
	private array $processedProperties = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	public function __construct(
		private readonly Entities\HomeKitConnector $connector,
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Entities\Devices\DevicesRepository $devicesRepository,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelsPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
	)
	{
	}

	public function connect(): void
	{
		$this->processedDevices = [];
		$this->processedProperties = [];

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
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function handleCommunication(): void
	{
		$findDevicesQuery = new Queries\FindDevices();
		$findDevicesQuery->forConnector($this->connector);

		foreach ($this->devicesRepository->findAllBy($findDevicesQuery, Entities\HomeKitDevice::class) as $device) {
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
	 * @throws DevicesExceptions\InvalidState
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function writeCharacteristic(
		Entities\Protocol\Device $accessory,
	): bool
	{
		$now = $this->dateTimeFactory->getNow();

		foreach ($accessory->getServices() as $service) {
			if ($service->getChannel() !== null) {
				foreach ($service->getCharacteristics() as $characteristic) {
					if ($characteristic->getProperty() instanceof DevicesEntities\Channels\Properties\Mapped) {
						$debounce = array_key_exists(
							$characteristic->getProperty()->getId()->toString(),
							$this->processedProperties,
						)
							? $this->processedProperties[$characteristic->getProperty()->getId()->toString()]
							: false;

						if (
							$debounce !== false
							&& (float) $now->format('Uv') - (float) $debounce->format(
								'Uv',
							) < self::HANDLER_DEBOUNCE_INTERVAL
						) {
							continue;
						}

						$this->processedProperties[$characteristic->getProperty()->getId()->toString()] = $now;

						$state = $this->channelsPropertiesStates->readValue($characteristic->getProperty());

						if ($state === null) {
							continue;
						}

						$propertyValue = $state->getExpectedValue() ?? $state->getActualValue();

						try {
							$characteristicValue = Protocol\Transformer::fromMappedParent(
								$characteristic->getProperty(),
								$propertyValue,
							);
						} catch (Exceptions\InvalidState $ex) {
							$this->logger->warning(
								'State value could not be converted from mapped parent',
								[
									'source' => MetadataTypes\ConnectorSource::SOURCE_CONNECTOR_HOMEKIT,
									'type' => 'periodic-writer',
									'exception' => BootstrapHelpers\Logger::buildException($ex),
									'connector' => [
										'id' => $accessory->getDevice()->getConnector()->getId()->toString(),
									],
									'device' => [
										'id' => $accessory->getDevice()->getId()->toString(),
									],
									'channel' => [
										'id' => $service->getChannel()->getId()->toString(),
									],
									'property' => [
										'id' => $characteristic->getProperty()->getId()->toString(),
									],
									'hap' => $accessory->toHap(),
								],
							);

							continue;
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
								'type' => 'periodic-writer',
								'connector' => [
									'id' => $accessory->getDevice()->getConnector()->getId()->toString(),
								],
								'device' => [
									'id' => $accessory->getDevice()->getId()->toString(),
								],
								'channel' => [
									'id' => $service->getChannel()->getId()->toString(),
								],
								'property' => [
									'id' => $characteristic->getProperty()?->getId()->toString(),
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
