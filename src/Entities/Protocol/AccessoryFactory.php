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

	/** @var ServiceFactory */
	private ServiceFactory $serviceFactory;

	/** @var CharacteristicsFactory */
	private CharacteristicsFactory $characteristicsFactory;

	/** @var HomeKitConnector\Protocol\Driver */
	private HomeKitConnector\Protocol\Driver $driver;

	/** @var Hashids\Hashids */
	private Hashids\Hashids $hashIds;

	/**
	 * @param ServiceFactory $serviceFactory
	 * @param CharacteristicsFactory $characteristicsFactory
	 * @param HomeKitConnector\Protocol\Driver $driver
	 */
	public function __construct(
		ServiceFactory $serviceFactory,
		CharacteristicsFactory $characteristicsFactory,
		HomeKitConnector\Protocol\Driver $driver
	) {
		$this->serviceFactory = $serviceFactory;
		$this->characteristicsFactory = $characteristicsFactory;

		$this->driver = $driver;
		$this->hashIds = new Hashids\Hashids();
	}

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity|MetadataEntities\Modules\DevicesModule\IDeviceEntity $owner
	 * @param int|null $aid
	 * @param Types\AccessoryCategory|null $category
	 *
	 * @return Accessory
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity|MetadataEntities\Modules\DevicesModule\IDeviceEntity $owner,
		?int $aid = null,
		?Types\AccessoryCategory $category = null
	): Accessory {
		$category = $category ?? Types\AccessoryCategory::get(Types\AccessoryCategory::CATEGORY_OTHER);

		if ($category->equalsValue(Types\AccessoryCategory::CATEGORY_BRIDGE)) {
			if (!$owner instanceof MetadataEntities\Modules\DevicesModule\ConnectorEntity) {
				throw new Exceptions\InvalidArgument('Bridge accessory owner have to be connector item instance');
			}

			$accessory = new Bridge($owner->getName() ?: $owner->getIdentifier(), $owner);

			$accessoryProtocolInformation = new Service(
				Uuid\Uuid::fromString(Service::HAP_PROTOCOL_INFORMATION_SERVICE_UUID),
				'HAPProtocolInformation',
				$accessory,
				null,
				['Version']
			);

			$accessoryProtocolVersion = $this->characteristicsFactory->create('Version', $accessoryProtocolInformation);
			$accessoryProtocolVersion->setActualValue(HomeKitConnector\Constants::HAP_PROTOCOL_VERSION);

			$accessoryProtocolInformation->addCharacteristic($accessoryProtocolVersion);

			$accessory->addService($accessoryProtocolInformation);
		} else {
			if (!$owner instanceof MetadataEntities\Modules\DevicesModule\DeviceEntity) {
				throw new Exceptions\InvalidArgument('Device accessory owner have to be device item instance');
			}

			$accessory = new Device($owner->getName() ?: $owner->getIdentifier(), $aid, $category, $owner);
		}

		$accessoryInformation = $this->serviceFactory->create('AccessoryInformation', $accessory);

		$accessoryName = $this->characteristicsFactory->create('Name', $accessoryInformation);
		$accessoryName->setActualValue($owner->getName() ?: $owner->getIdentifier());

		$accessoryInformation->addCharacteristic($accessoryName);

		$accessorySerialNumber = $this->characteristicsFactory->create('SerialNumber', $accessoryInformation);
		$accessorySerialNumber->setActualValue($this->hashIds->encode((int) $owner->getId()->getInteger()->toString()));

		$accessoryInformation->addCharacteristic($accessorySerialNumber);

		$packageRevision = Composer\InstalledVersions::getVersion(HomeKitConnector\Constants::PACKAGE_NAME);

		$accessoryFirmwareRevision = $this->characteristicsFactory->create('FirmwareRevision', $accessoryInformation);
		$accessoryFirmwareRevision->setActualValue(
			$packageRevision !== null && preg_match(
				HomeKitConnector\Constants::VERSION_REGEXP,
				$packageRevision
			) === 1 ? $packageRevision : '0.0.0'
		);

		$accessoryInformation->addCharacteristic($accessoryFirmwareRevision);

		$accessoryManufacturer = $this->characteristicsFactory->create('Manufacturer', $accessoryInformation);
		$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_MANUFACTURER);

		$accessoryInformation->addCharacteristic($accessoryManufacturer);

		if ($accessory instanceof Bridge) {
			$accessoryManufacturer = $this->characteristicsFactory->create('Model', $accessoryInformation);
			$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_BRIDGE_MODEL);

			$accessoryInformation->addCharacteristic($accessoryManufacturer);
		} else {
			$accessoryManufacturer = $this->characteristicsFactory->create('Model', $accessoryInformation);
			$accessoryManufacturer->setActualValue(HomeKitConnector\Constants::DEFAULT_DEVICE_MODEL);

			$accessoryInformation->addCharacteristic($accessoryManufacturer);
		}

		$accessory->addService($accessoryInformation);

		if ($accessory instanceof Bridge) {
			$this->driver->addBridge($accessory);
		} else {
			$this->driver->addBridgedAccessory($accessory);
		}

		return $accessory;
	}

}
