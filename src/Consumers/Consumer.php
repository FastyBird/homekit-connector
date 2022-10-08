<?php declare(strict_types = 1);

/**
 * Consumer.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Consumer
 * @since          0.19.0
 *
 * @date           02.10.22
 */

namespace FastyBird\HomeKitConnector\Consumers;

use FastyBird\DevicesModule\Models as DevicesModuleModels;
use FastyBird\Exchange\Consumer as ExchangeConsumer;
use FastyBird\HomeKitConnector\Clients;
use FastyBird\HomeKitConnector\Entities;
use FastyBird\HomeKitConnector\Protocol;
use FastyBird\Metadata;
use FastyBird\Metadata\Entities as MetadataEntities;
use FastyBird\Metadata\Types as MetadataTypes;
use Nette;
use Psr\Log;
use Throwable;
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
		private readonly DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws Metadata\Exceptions\FileNotFound
	 * @throws Throwable
	 */
	public function consume(
		MetadataTypes\ModuleSource|MetadataTypes\PluginSource|MetadataTypes\ConnectorSource $source,
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
