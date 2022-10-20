<?php declare(strict_types = 1);

/**
 * Consumer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Consumer
 * @since          0.19.0
 *
 * @date           02.10.22
 */

namespace FastyBird\Connector\HomeKit\Consumers;

use Exception;
use FastyBird\Connector\HomeKit\Clients;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Protocol;
use FastyBird\Library\Exchange\Consumer as ExchangeConsumer;
use FastyBird\Library\Metadata;
use FastyBird\Library\Metadata\Entities as MetadataEntities;
use FastyBird\Library\Metadata\Exceptions as MetadataExceptions;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use FastyBird\Module\Devices\Models as DevicesModels;
use Nette;
use Psr\Log;
use function intval;

/**
 * Websockets exchange publisher
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Consumer
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Consumer implements ExchangeConsumer\Consumer
{

	use Nette\SmartObject;

	private Log\LoggerInterface $logger;

	public function __construct(
		private readonly Protocol\Driver $accessoryDriver,
		private readonly Clients\Subscriber $subscriber,
		private readonly DevicesModels\DataStorage\ChannelsRepository $channelsRepository,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws Metadata\Exceptions\FileNotFound
	 * @throws Metadata\Exceptions\InvalidArgument
	 * @throws Metadata\Exceptions\InvalidData
	 * @throws Metadata\Exceptions\InvalidState
	 * @throws Metadata\Exceptions\Logic
	 * @throws Metadata\Exceptions\MalformedInput
	 * @throws Exception
	 */
	public function consume(
		MetadataTypes\ModuleSource|MetadataTypes\PluginSource|MetadataTypes\ConnectorSource|MetadataTypes\TriggerSource $source,
		MetadataTypes\RoutingKey $routingKey,
		MetadataEntities\Entity|null $entity,
	): void
	{
		if (
			$entity instanceof MetadataEntities\DevicesModule\DeviceMappedProperty
			|| $entity instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
			|| $entity instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
			|| $entity instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
		) {
			if (
				$entity instanceof MetadataEntities\DevicesModule\DeviceMappedProperty
				|| $entity instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
			) {
				$accessory = $this->accessoryDriver->findAccessory($entity->getDevice());
			} else {
				$channel = $this->channelsRepository->findById($entity->getChannel());

				if ($channel === null) {
					$this->logger->error(
						'Channel for received channel property message could not be loaded',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'consumer',
							'message' => [
								'source' => $source->getValue(),
								'routing_key' => $routingKey->getValue(),
								'entity' => $entity->toArray(),
							],
							'property' => $entity->toArray(),
						],
					);

					return;
				}

				$accessory = $this->accessoryDriver->findAccessory($channel->getDevice());
			}

			if ($accessory === null) {
				$this->logger->warning(
					'Accessory for received property message was not found in accessory driver',
					[
						'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
						'type' => 'consumer',
						'message' => [
							'source' => $source->getValue(),
							'routing_key' => $routingKey->getValue(),
							'entity' => $entity->toArray(),
						],
						'property' => $entity->toArray(),
					],
				);

				return;
			}

			$this->processProperty($entity, $accessory);
		}
	}

	/**
	 * @throws MetadataExceptions\InvalidState
	 */
	private function processProperty(
		MetadataEntities\DevicesModule\Property $entity,
		Entities\Protocol\Accessory $accessory,
	): void
	{
		foreach ($accessory->getServices() as $service) {
			foreach ($service->getCharacteristics() as $characteristic) {
				if (
					$characteristic->getProperty() !== null
					&& $characteristic->getProperty()->getId()->equals($entity->getId())
				) {
					if (
						$entity instanceof MetadataEntities\DevicesModule\DeviceMappedProperty
						|| $entity instanceof MetadataEntities\DevicesModule\ChannelMappedProperty
					) {
						$characteristic->setActualValue($entity->getActualValue());
					} elseif (
						$entity instanceof MetadataEntities\DevicesModule\DeviceVariableProperty
						|| $entity instanceof MetadataEntities\DevicesModule\ChannelVariableProperty
					) {
						$characteristic->setActualValue($entity->getValue());
					}

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
						null,
					);

					$this->logger->debug(
						'Processed received property message',
						[
							'source' => Metadata\Constants::CONNECTOR_HOMEKIT_SOURCE,
							'type' => 'consumer',
							'property' => $entity->toArray(),
						],
					);

					return;
				}
			}
		}
	}

}
