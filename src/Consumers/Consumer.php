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
use function intval;

/**
 * Websockets exchange publisher
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Consumer
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Consumer implements ExchangeConsumer\IConsumer
{

	use Nette\SmartObject;

	private Log\LoggerInterface $logger;

	public function __construct(
		private Protocol\Driver $accessoryDriver,
		private Clients\Subscriber $subscriber,
		private DevicesModuleModels\DataStorage\ChannelsRepository $channelsRepository,
		Log\LoggerInterface|null $logger = null,
	)
	{
		$this->logger = $logger ?? new Log\NullLogger();
	}

	/**
	 * @throws Metadata\Exceptions\FileNotFoundException
	 */
	public function consume(
		MetadataTypes\ModuleSourceType|MetadataTypes\PluginSourceType|MetadataTypes\ConnectorSourceType $source,
		MetadataTypes\RoutingKeyType $routingKey,
		MetadataEntities\IEntity|null $entity,
	): void
	{
		if (
			$entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceMappedPropertyEntity
			|| $entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
			|| $entity instanceof MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity
			|| $entity instanceof MetadataEntities\Modules\DevicesModule\IChannelStaticPropertyEntity
		) {
			if (
				$entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceMappedPropertyEntity
				|| $entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
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
		MetadataEntities\Modules\DevicesModule\IPropertyEntity $entity,
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
						$entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceMappedPropertyEntity
						|| $entity instanceof MetadataEntities\Modules\DevicesModule\IChannelMappedPropertyEntity
					) {
						$characteristic->setActualValue($entity->getActualValue());
					} elseif (
						$entity instanceof MetadataEntities\Modules\DevicesModule\IDeviceStaticPropertyEntity
						|| $entity instanceof MetadataEntities\Modules\DevicesModule\IChannelStaticPropertyEntity
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
