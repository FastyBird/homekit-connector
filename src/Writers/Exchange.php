<?php declare(strict_types = 1);

/**
 * Exchange.php
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
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Helpers;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\DateTimeFactory;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Exchange\Consumers as ExchangeConsumers;
use FastyBird\Library\Exchange\Exceptions as ExchangeExceptions;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Constants as DevicesConstants;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Events as DevicesEvents;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use Psr\EventDispatcher as PsrEventDispatcher;
use React\EventLoop;
use Throwable;
use TypeError;
use ValueError;
use function array_merge;
use function str_starts_with;

/**
 * Exchange based properties writer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Exchange extends Periodic implements Writer, ExchangeConsumers\Consumer
{

	public const NAME = 'exchange';

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
		private readonly ExchangeConsumers\Container $consumer,
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

		$this->consumer->register($this, null, false);
	}

	/**
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws ExchangeExceptions\InvalidArgument
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
		parent::connect();

		$this->consumer->enable(self::class);
	}

	/**
	 * @throws ExchangeExceptions\InvalidArgument
	 */
	public function disconnect(): void
	{
		parent::disconnect();

		$this->consumer->disable(self::class);
	}

	public function consume(
		MetadataTypes\Sources\Source $source,
		string $routingKey,
		MetadataDocuments\Document|null $document,
	): void
	{
		try {
			if (
				$document instanceof DevicesDocuments\States\Devices\Properties\Property
				|| $document instanceof DevicesDocuments\States\Channels\Properties\Property
			) {
				if (str_starts_with($routingKey, DevicesConstants::MESSAGE_BUS_DELETED_ROUTING_KEY)) {
					return;
				}

				if ($document instanceof DevicesDocuments\States\Devices\Properties\Property) {
					$findDeviceQuery = new Queries\Configuration\FindDevices();
					$findDeviceQuery->forConnector($this->connector);
					$findDeviceQuery->byId($document->getDevice());

					$device = $this->devicesConfigurationRepository->findOneBy(
						$findDeviceQuery,
						Documents\Devices\Device::class,
					);

					if ($device === null) {
						return;
					}

					$findPropertyQuery = new Queries\Configuration\FindDeviceProperties();
					$findPropertyQuery->byId($document->getId());
					$findPropertyQuery->forDevice($device);

					$property = $this->devicesPropertiesConfigurationRepository->findOneBy($findPropertyQuery);

					if ($property === null) {
						return;
					}

					if ($property instanceof DevicesDocuments\Devices\Properties\Mapped) {
						$this->queue->append(
							$this->messageBuilder->create(
								Queue\Messages\WriteDevicePropertyState::class,
								[
									'connector' => $device->getConnector(),
									'device' => $device->getId(),
									'property' => $document->getId(),
									'state' => array_merge(
										$document->getRead()->toArray(),
										[
											'id' => $document->getId(),
											'valid' => $document->isValid(),
											'pending' => $document->getPending() instanceof DateTimeInterface
												? $document->getPending()->format(DateTimeInterface::ATOM)
												: $document->getPending(),
										],
									),
								],
							),
						);
					} elseif ($property instanceof DevicesDocuments\Devices\Properties\Dynamic) {
						$this->queue->append(
							$this->messageBuilder->create(
								Queue\Messages\WriteDevicePropertyState::class,
								[
									'connector' => $device->getConnector(),
									'device' => $device->getId(),
									'property' => $document->getId(),
									'state' => array_merge(
										$document->getGet()->toArray(),
										[
											'id' => $document->getId(),
											'valid' => $document->isValid(),
											'pending' => $document->getPending() instanceof DateTimeInterface
												? $document->getPending()->format(DateTimeInterface::ATOM)
												: $document->getPending(),
										],
									),
								],
							),
						);
					}
				} else {
					$findChannelQuery = new Queries\Configuration\FindChannels();
					$findChannelQuery->byId($document->getChannel());

					$channel = $this->channelsConfigurationRepository->findOneBy(
						$findChannelQuery,
						Documents\Channels\Channel::class,
					);

					if ($channel === null) {
						return;
					}

					$findDeviceQuery = new Queries\Configuration\FindDevices();
					$findDeviceQuery->forConnector($this->connector);
					$findDeviceQuery->byId($channel->getDevice());

					$device = $this->devicesConfigurationRepository->findOneBy(
						$findDeviceQuery,
						Documents\Devices\Device::class,
					);

					if ($device === null) {
						return;
					}

					$findPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
					$findPropertyQuery->byId($document->getId());
					$findPropertyQuery->forChannel($channel);

					$property = $this->channelsPropertiesConfigurationRepository->findOneBy($findPropertyQuery);

					if ($property === null) {
						return;
					}

					if ($property instanceof DevicesDocuments\Channels\Properties\Mapped) {
						$this->queue->append(
							$this->messageBuilder->create(
								Queue\Messages\WriteChannelPropertyState::class,
								[
									'connector' => $device->getConnector(),
									'device' => $device->getId(),
									'channel' => $channel->getId(),
									'property' => $document->getId(),
									'state' => array_merge(
										$document->getRead()->toArray(),
										[
											'id' => $document->getId(),
											'valid' => $document->isValid(),
											'pending' => $document->getPending() instanceof DateTimeInterface
												? $document->getPending()->format(DateTimeInterface::ATOM)
												: $document->getPending(),
										],
									),
								],
							),
						);
					} elseif ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
						$this->queue->append(
							$this->messageBuilder->create(
								Queue\Messages\WriteChannelPropertyState::class,
								[
									'connector' => $device->getConnector(),
									'device' => $device->getId(),
									'channel' => $channel->getId(),
									'property' => $document->getId(),
									'state' => array_merge(
										$document->getGet()->toArray(),
										[
											'id' => $document->getId(),
											'valid' => $document->isValid(),
											'pending' => $document->getPending() instanceof DateTimeInterface
												? $document->getPending()->format(DateTimeInterface::ATOM)
												: $document->getPending(),
										],
									),
								],
							),
						);
					}
				}
			} elseif (
				$document instanceof DevicesDocuments\Devices\Properties\Variable
				|| $document instanceof DevicesDocuments\Channels\Properties\Variable
			) {
				if (str_starts_with($routingKey, DevicesConstants::MESSAGE_BUS_DELETED_ROUTING_KEY)) {
					return;
				}

				if ($document instanceof DevicesDocuments\Devices\Properties\Variable) {
					$findDeviceQuery = new Queries\Configuration\FindDevices();
					$findDeviceQuery->forConnector($this->connector);
					$findDeviceQuery->byId($document->getDevice());

					$device = $this->devicesConfigurationRepository->findOneBy(
						$findDeviceQuery,
						Documents\Devices\Device::class,
					);

					if ($device === null) {
						return;
					}

					$this->queue->append(
						$this->messageBuilder->create(
							Queue\Messages\WriteDevicePropertyState::class,
							[
								'connector' => $device->getConnector(),
								'device' => $device->getId(),
								'property' => $document->getId(),
							],
						),
					);

				} else {
					$findChannelQuery = new Queries\Configuration\FindChannels();
					$findChannelQuery->byId($document->getChannel());

					$channel = $this->channelsConfigurationRepository->findOneBy(
						$findChannelQuery,
						Documents\Channels\Channel::class,
					);

					if ($channel === null) {
						return;
					}

					$findDeviceQuery = new Queries\Configuration\FindDevices();
					$findDeviceQuery->forConnector($this->connector);
					$findDeviceQuery->byId($channel->getDevice());

					$device = $this->devicesConfigurationRepository->findOneBy(
						$findDeviceQuery,
						Documents\Devices\Device::class,
					);

					if ($device === null) {
						return;
					}

					$this->queue->append(
						$this->messageBuilder->create(
							Queue\Messages\WriteChannelPropertyState::class,
							[
								'connector' => $device->getConnector(),
								'device' => $device->getId(),
								'channel' => $channel->getId(),
								'property' => $document->getId(),
							],
						),
					);
				}
			} elseif ($document instanceof DevicesDocuments\Connectors\Properties\Variable) {
				if (!$document->getConnector()->equals($this->connector->getId())) {
					return;
				}

				if (
					$document->getIdentifier() === Types\ConnectorPropertyIdentifier::PAIRED->value
					|| $document->getIdentifier() === Types\ConnectorPropertyIdentifier::CONFIG_VERSION->value
				) {
					$this->dispatcher?->dispatch(
						new DevicesEvents\RestartConnector(
							MetadataTypes\Sources\Connector::HOMEKIT,
							'Connector configuration changed, services have to be restarted',
						),
					);
				}

				if ($document->getIdentifier() === Types\ConnectorPropertyIdentifier::SHARED_KEY->value) {
					$this->dispatcher?->dispatch(
						new DevicesEvents\RestartConnector(
							MetadataTypes\Sources\Connector::HOMEKIT,
							'Connector shared key changed, services have to be restarted',
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
					'type' => 'exchange-writer',
					'exception' => ApplicationHelpers\Logger::buildException($ex),
				],
			);
		}
	}

}
