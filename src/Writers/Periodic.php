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
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Utilities as DevicesUtilities;
use Nette;
use Psr\Log;
use React\EventLoop;
use function array_key_exists;
use function assert;
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

	/** @var array<string, Entities\HomeKitConnector> */
	private array $connectors = [];

	private EventLoop\TimerInterface|null $handlerTimer = null;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly DevicesUtilities\ChannelPropertiesStates $channelPropertiesStates,
		private readonly DateTimeFactory\Factory $dateTimeFactory,
		private readonly EventLoop\LoopInterface $eventLoop,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	public function connect(Entities\HomeKitConnector $connector): void
	{
		$this->connectors[$connector->getPlainId()] = $connector;

		$this->processedDevices = [];
		$this->processedProperties = [];

		$this->eventLoop->addTimer(
			self::HANDLER_START_DELAY,
			function (): void {
				$this->registerLoopHandler();
			},
		);
	}

	public function disconnect(Entities\HomeKitConnector $connector): void
	{
		unset($this->connectors[$connector->getPlainId()]);

		if ($this->connectors === [] && $this->handlerTimer !== null) {
			$this->eventLoop->cancelTimer($this->handlerTimer);

			$this->handlerTimer = null;
		}
	}

	/**
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 */
	private function handleCommunication(): void
	{
		foreach ($this->connectors as $connector) {
			foreach ($connector->getDevices() as $device) {
				assert($device instanceof Entities\HomeKitDevice);

				$accessory = $this->accessoryDriver->findAccessory($device->getId());

				if (!$accessory instanceof Entities\Protocol\Device) {
					continue;
				}

				if (!in_array($device->getPlainId(), $this->processedDevices, true)) {
					$this->processedDevices[] = $device->getPlainId();

					if ($this->writeCharacteristic($accessory)) {
						$this->registerLoopHandler();

						return;
					}
				}
			}
		}

		$this->processedDevices = [];

		$this->registerLoopHandler();
	}

	/**
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
							$characteristic->getProperty()->getPlainId(),
							$this->processedProperties,
						)
							? $this->processedProperties[$characteristic->getProperty()->getPlainId()]
							: false;

						if (
							$debounce !== false
							&& (float) $now->format('Uv') - (float) $debounce->format(
								'Uv',
							) < self::HANDLER_DEBOUNCE_INTERVAL
						) {
							continue;
						}

						$this->processedProperties[$characteristic->getProperty()->getPlainId()] = $now;

						$state = $this->channelPropertiesStates->getValue($characteristic->getProperty());

						if ($state === null) {
							continue;
						}

						if ($characteristic->getActualValue() === $state->getActualValue()) {
							continue;
						}

						$characteristic->setActualValue($state->getActualValue());

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
								'type' => 'periodic-writer',
								'group' => 'writer',
								'connector' => [
									'id' => $accessory->getDevice()->getConnector()->getPlainId(),
								],
								'device' => [
									'id' => $accessory->getDevice()->getPlainId(),
								],
								'channel' => [
									'id' => $service->getChannel()->getPlainId(),
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
