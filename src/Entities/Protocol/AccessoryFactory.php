<?php declare(strict_types = 1);

/**
 * AccessoryFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.19.0
 *
 * @date           13.09.22
 */

namespace FastyBird\HomeKitConnector\Entities\Protocol;

use Composer;
use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use Hashids;
use Ramsey\Uuid;
use function preg_match;

/**
 * HAP accessory factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class AccessoryFactory
{

	private Hashids\Hashids $hashIds;

	public function __construct(
		private readonly ServiceFactory $serviceFactory,
		private readonly CharacteristicsFactory $characteristicsFactory,
	)
	{
		$this->hashIds = new Hashids\Hashids();
	}

	public function create(
		MetadataEntities\DevicesModule\Connector|MetadataEntities\DevicesModule\Device $owner,
		int|null $aid = null,
		Types\AccessoryCategory|null $category = null,
	): Accessory
	{
		$category ??= Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_OTHER);

		if ($category->equalsValue(Types\AccessoryCategory::CATEGORY_BRIDGE)) {
			if (!$owner instanceof MetadataEntities\DevicesModule\Connector) {
				throw new Exceptions\InvalidArgument('Bridge accessory owner have to be connector item instance');
			}

			$accessory = new Bridge($owner->getName() ?? $owner->getIdentifier(), $owner);

			$accessoryProtocolInformation = new Service(
				Uuid\Uuid::fromString(Service::HAP_PROTOCOL_INFORMATION_SERVICE_UUID),
				'HAPProtocolInformation',
				$accessory,
				null,
				['Version'],
			);

			$accessoryProtocolVersion = $this->characteristicsFactory->create(
				Types\ChannelPropertyIdentifier::IDENTIFIER_VERSION,
				$accessoryProtocolInformation,
			);
			$accessoryProtocolVersion->setActualValue(HomeKitConnector\Constants::HAP_PROTOCOL_VERSION);

			$accessoryProtocolInformation->addCharacteristic($accessoryProtocolVersion);

			$accessory->addService($accessoryProtocolInformation);
		} else {
			if (!$owner instanceof MetadataEntities\DevicesModule\Device) {
				throw new Exceptions\InvalidArgument('Device accessory owner have to be device item instance');
			}

			$accessory = new Device($owner->getName() ?? $owner->getIdentifier(), $aid, $category, $owner);
		}

		$accessoryInformation = $this->serviceFactory->create(
			Types\ChannelIdentifier::IDENTIFIER_ACCESSORY_INFORMATION,
			$accessory,
		);

		$accessoryName = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFIER_NAME,
			$accessoryInformation,
		);
		$accessoryName->setActualValue($owner->getName() ?? $owner->getIdentifier());

		$accessoryInformation->addCharacteristic($accessoryName);

		$accessorySerialNumber = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFIER_SERIAL_NUMBER,
			$accessoryInformation,
		);
		$accessorySerialNumber->setActualValue($this->hashIds->encode((int) $owner->getId()->getInteger()->toString()));

		$accessoryInformation->addCharacteristic($accessorySerialNumber);

		$packageRevision = Composer\InstalledVersions::getVersion(HomeKitConnector\Constants::PACKAGE_NAME);

		$accessoryFirmwareRevision = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFIER_FIRMWARE_REVISION,
			$accessoryInformation,
		);
		$accessoryFirmwareRevision->setActualValue(
			$packageRevision !== null && preg_match(
				HomeKitConnector\Constants::VERSION_REGEXP,
				$packageRevision,
			) === 1 ? $packageRevision : '0.0.0',
		);

		$accessoryInformation->addCharacteristic($accessoryFirmwareRevision);

		$accessoryManufacturer = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFIER_MANUFACTURER,
			$accessoryInformation,
		);
		$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_MANUFACTURER);

		$accessoryInformation->addCharacteristic($accessoryManufacturer);

		$accessoryManufacturer = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFIER_MODEL,
			$accessoryInformation,
		);

		if ($accessory instanceof Bridge) {
			$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_BRIDGE_MODEL);
		} else {
			$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_DEVICE_MODEL);
		}

		$accessoryInformation->addCharacteristic($accessoryManufacturer);

		$accessory->addService($accessoryInformation);

		return $accessory;
	}

}
