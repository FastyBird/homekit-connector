<?php declare(strict_types = 1);

/**
 * AccessoryFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Entities\Protocol;

use Composer;
use FastyBird\Connector\HomeKit;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Exceptions;
use FastyBird\Connector\HomeKit\Types;
use FastyBird\Library\Metadata\Documents as MetadataDocuments;
use Hashids;
use Nette;
use Ramsey\Uuid;
use function array_map;
use function intval;
use function preg_match;
use function str_split;

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

	/**
	 * @throws Hashids\HashidsException
	 */
	public function __construct(
		private readonly ServiceFactory $serviceFactory,
		private readonly CharacteristicsFactory $characteristicsFactory,
	)
	{
		$this->hashIds = new Hashids\Hashids();
	}

	/**
	 * @throws Exceptions\InvalidArgument
	 * @throws Exceptions\InvalidState
	 * @throws Nette\IOException
	 */
	public function create(
		MetadataDocuments\DevicesModule\Connector|MetadataDocuments\DevicesModule\Device $owner,
		int|null $aid = null,
		Types\AccessoryCategory|null $category = null,
	): Accessory
	{
		$category ??= Types\AccessoryCategory::get(Types\AccessoryCategory::OTHER);

		if ($category->equalsValue(Types\AccessoryCategory::BRIDGE)) {
			if (!$owner instanceof MetadataDocuments\DevicesModule\Connector) {
				throw new Exceptions\InvalidArgument('Bridge accessory owner have to be connector item instance');
			}

			$accessory = new Bridge($owner->getName() ?? $owner->getIdentifier(), $owner);
		} else {
			if (!$owner instanceof MetadataDocuments\DevicesModule\Device) {
				throw new Exceptions\InvalidArgument('Device accessory owner have to be device item instance');
			}

			$accessoryClassName = $this->getDeviceClass($category);
			$accessory = new $accessoryClassName($owner->getName() ?? $owner->getIdentifier(), $aid, $category, $owner);
		}

		$accessoryInformation = $this->serviceFactory->create(
			Types\ServiceType::get(Types\ServiceType::ACCESSORY_INFORMATION),
			$accessory,
		);

		$accessoryName = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::NAME,
			$accessoryInformation,
		);
		$accessoryName->setValue($owner->getName() ?? $owner->getIdentifier());

		$accessoryInformation->addCharacteristic($accessoryName);

		$accessorySerialNumber = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::SERIAL_NUMBER,
			$accessoryInformation,
		);

		$accessorySerialNumber->setValue(
			$this->hashIds->encode(
				...array_map(
					static fn (string $part): int => intval($part),
					str_split($owner->getId()->getInteger()->toString(), 5),
				),
			),
		);

		$accessoryInformation->addCharacteristic($accessorySerialNumber);

		$packageRevision = Composer\InstalledVersions::getVersion(HomeKit\Constants::PACKAGE_NAME);

		$accessoryFirmwareRevision = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::FIRMWARE_REVISION,
			$accessoryInformation,
		);
		$accessoryFirmwareRevision->setValue(
			$packageRevision !== null && preg_match(
				HomeKit\Constants::VERSION_REGEXP,
				$packageRevision,
			) === 1 ? $packageRevision : '0.0.0',
		);

		$accessoryInformation->addCharacteristic($accessoryFirmwareRevision);

		$accessoryManufacturer = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::MANUFACTURER,
			$accessoryInformation,
		);
		$accessoryManufacturer->setValue(HomeKit\Constants::DEFAULT_MANUFACTURER);

		$accessoryInformation->addCharacteristic($accessoryManufacturer);

		$accessoryModel = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::MODEL,
			$accessoryInformation,
		);

		if ($accessory instanceof Bridge) {
			$accessoryModel->setValue(HomeKit\Constants::DEFAULT_BRIDGE_MODEL);
		} else {
			$accessoryModel->setValue(HomeKit\Constants::DEFAULT_DEVICE_MODEL);
		}

		$accessoryInformation->addCharacteristic($accessoryModel);

		$accessoryIdentify = $this->characteristicsFactory->create(
			Types\ChannelPropertyIdentifier::IDENTIFY,
			$accessoryInformation,
		);
		$accessoryIdentify->setValue(false);

		$accessoryInformation->addCharacteristic($accessoryIdentify);

		$accessory->addService($accessoryInformation);

		if ($accessory instanceof Bridge) {
			$accessoryProtocolInformation = new Service(
				Uuid\Uuid::fromString(Service::HAP_PROTOCOL_INFORMATION_SERVICE_UUID),
				'HAPProtocolInformation',
				$accessory,
				null,
				['Version'],
			);

			$accessoryProtocolVersion = $this->characteristicsFactory->create(
				Types\ChannelPropertyIdentifier::VERSION,
				$accessoryProtocolInformation,
			);
			$accessoryProtocolVersion->setValue(HomeKit\Constants::HAP_PROTOCOL_VERSION);

			$accessoryProtocolInformation->addCharacteristic($accessoryProtocolVersion);

			$accessory->addService($accessoryProtocolInformation);
		}

		return $accessory;
	}

	/**
	 * @return class-string<Device>
	 */
	private function getDeviceClass(Types\AccessoryCategory $category): string
	{
		if ($category->equalsValue(Types\AccessoryCategory::LIGHT_BULB)) {
			return Entities\Protocol\Devices\LightBulb::class;
		}

		return Entities\Protocol\Devices\Generic::class;
	}

}
