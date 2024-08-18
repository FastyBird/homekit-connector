<?php declare(strict_types = 1);

/**
 * StoreChannelPropertyState.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 * @since          1.0.0
 *
 * @date           30.11.23
 */

namespace FastyBird\Connector\HomeKit\Queue\Consumers;

use Doctrine\DBAL;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Queries;
use FastyBird\Connector\HomeKit\Queue;
use FastyBird\Library\Application\Exceptions as ApplicationExceptions;
use FastyBird\Library\Application\Helpers as ApplicationHelpers;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Library\Tools\Exceptions as ToolsExceptions;
use FastyBird\Module\Devices\Documents as DevicesDocuments;
use FastyBird\Module\Devices\Entities as DevicesEntities;
use FastyBird\Module\Devices\Exceptions as DevicesExceptions;
use FastyBird\Module\Devices\Models as DevicesModels;
use FastyBird\Module\Devices\Queries as DevicesQueries;
use FastyBird\Module\Devices\States as DevicesStates;
use Nette;
use Nette\Utils;
use TypeError;
use ValueError;
use function assert;
use function React\Async\await;

/**
 * Store channel property state message consumer
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Queue
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class StoreChannelPropertyState implements Queue\Consumer
{

	use Nette\SmartObject;

	public function __construct(
		private readonly HomeKit\Logger $logger,
		private readonly DevicesModels\Configuration\Devices\Repository $devicesConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Repository $channelsConfigurationRepository,
		private readonly DevicesModels\Configuration\Channels\Properties\Repository $channelsPropertiesConfigurationRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesRepository $channelsPropertiesRepository,
		private readonly DevicesModels\Entities\Channels\Properties\PropertiesManager $channelsPropertiesManager,
		private readonly DevicesModels\States\Async\ChannelPropertiesManager $channelPropertiesStatesManager,
		private readonly ApplicationHelpers\Database $databaseHelper,
	)
	{
	}

	/**
	 * @throws ApplicationExceptions\InvalidState
	 * @throws ApplicationExceptions\Runtime
	 * @throws DBAL\Exception
	 * @throws DevicesExceptions\InvalidArgument
	 * @throws DevicesExceptions\InvalidState
	 * @throws DevicesExceptions\Runtime
	 * @throws MetadataExceptions\InvalidArgument
	 * @throws MetadataExceptions\InvalidState
	 * @throws ToolsExceptions\InvalidArgument
	 * @throws TypeError
	 * @throws ValueError
	 */
	public function consume(Queue\Messages\Message $message): bool
	{
		if (!$message instanceof Queue\Messages\StoreChannelPropertyState) {
			return false;
		}

		$findDeviceQuery = new Queries\Configuration\FindDevices();
		$findDeviceQuery->byConnectorId($message->getConnector());
		$findDeviceQuery->byId($message->getDevice());

		$device = $this->devicesConfigurationRepository->findOneBy(
			$findDeviceQuery,
			Documents\Devices\Device::class,
		);

		if ($device === null) {
			$this->logger->error(
				'Device could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $message->getDevice()->toString(),
					],
					'channel' => [
						'id' => $message->getChannel()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$findChannelQuery = new Queries\Configuration\FindChannels();
		$findChannelQuery->forDevice($device);
		$findChannelQuery->byId($message->getChannel());

		$channel = $this->channelsConfigurationRepository->findOneBy(
			$findChannelQuery,
			Documents\Channels\Channel::class,
		);

		if ($channel === null) {
			$this->logger->error(
				'Device channel could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $message->getChannel()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
		$findChannelPropertyQuery->forChannel($channel);
		$findChannelPropertyQuery->byId($message->getProperty());

		$property = $this->channelsPropertiesConfigurationRepository->findOneBy($findChannelPropertyQuery);

		if ($property === null) {
			$this->logger->error(
				'Device channel property could not be loaded',
				[
					'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
					'type' => 'store-channel-property-state-message-consumer',
					'connector' => [
						'id' => $message->getConnector()->toString(),
					],
					'device' => [
						'id' => $device->getId()->toString(),
					],
					'channel' => [
						'id' => $channel->getId()->toString(),
					],
					'property' => [
						'id' => $message->getProperty()->toString(),
					],
					'data' => $message->toArray(),
				],
			);

			return true;
		}

		if ($property instanceof DevicesDocuments\Channels\Properties\Variable) {
			$this->databaseHelper->transaction(
				function () use ($message, $property): void {
					$property = $this->channelsPropertiesRepository->find(
						$property->getId(),
						DevicesEntities\Channels\Properties\Variable::class,
					);
					assert($property instanceof DevicesEntities\Channels\Properties\Variable);

					$this->channelsPropertiesManager->update(
						$property,
						Utils\ArrayHash::from([
							'value' => $message->getValue(),
						]),
					);
				},
			);

		} elseif ($property instanceof DevicesDocuments\Channels\Properties\Dynamic) {
			await($this->channelPropertiesStatesManager->set(
				$property,
				Utils\ArrayHash::from([
					DevicesStates\Property::ACTUAL_VALUE_FIELD => $message->getValue(),
				]),
				MetadataTypes\Sources\Connector::HOMEKIT,
			));
			await($this->channelPropertiesStatesManager->setValidState(
				$property,
				true,
				MetadataTypes\Sources\Connector::HOMEKIT,
			));
		} elseif ($property instanceof DevicesDocuments\Channels\Properties\Mapped) {
			$findChannelPropertyQuery = new DevicesQueries\Configuration\FindChannelProperties();
			$findChannelPropertyQuery->byId($property->getParent());

			$parent = $this->channelsPropertiesConfigurationRepository->findOneBy($findChannelPropertyQuery);

			if ($parent instanceof DevicesDocuments\Channels\Properties\Dynamic) {
				await($this->channelPropertiesStatesManager->write(
					$property,
					Utils\ArrayHash::from([
						DevicesStates\Property::EXPECTED_VALUE_FIELD => $message->getValue(),
					]),
					MetadataTypes\Sources\Connector::HOMEKIT,
				));
			} elseif ($parent instanceof DevicesDocuments\Channels\Properties\Variable) {
				$this->databaseHelper->transaction(
					function () use ($message, $parent, $device, $channel, $property): void {
						$toUpdate = $this->channelsPropertiesRepository->find(
							$parent->getId(),
							DevicesEntities\Channels\Properties\Variable::class,
						);

						if ($toUpdate !== null) {
							$this->channelsPropertiesManager->update(
								$toUpdate,
								Utils\ArrayHash::from([
									'value' => $message->getValue(),
								]),
							);
						} else {
							$this->logger->error(
								'Mapped variable property could not be updated',
								[
									'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
									'type' => 'store-channel-property-state-message-consumer',
									'connector' => [
										'id' => $message->getConnector()->toString(),
									],
									'device' => [
										'id' => $device->getId()->toString(),
									],
									'channel' => [
										'id' => $channel->getId()->toString(),
									],
									'property' => [
										'id' => $property->getId()->toString(),
									],
									'data' => $message->toArray(),
								],
							);
						}
					},
				);
			}
		}

		$this->logger->debug(
			'Consumed store device state message',
			[
				'source' => MetadataTypes\Sources\Connector::HOMEKIT->value,
				'type' => 'store-channel-property-state-message-consumer',
				'connector' => [
					'id' => $message->getConnector()->toString(),
				],
				'device' => [
					'id' => $device->getId()->toString(),
				],
				'channel' => [
					'id' => $channel->getId()->toString(),
				],
				'property' => [
					'id' => $property->getId()->toString(),
				],
				'data' => $message->toArray(),
			],
		);

		return true;
	}

}
