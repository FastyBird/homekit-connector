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

use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;

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

	/**
	 * @param ServiceFactory $serviceFactory
	 * @param CharacteristicsFactory $characteristicsFactory
	 */
	public function __construct(
		ServiceFactory $serviceFactory,
		CharacteristicsFactory $characteristicsFactory
	) {
		$this->serviceFactory = $serviceFactory;
		$this->characteristicsFactory = $characteristicsFactory;
	}

	/**
	 * @param string $name
	 * @param int|null $aid
	 * @param Types\Category|null $category
	 * @param MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner
	 *
	 * @return Accessory
	 */
	public function create(
		string $name,
		?int $aid = null,
		?Types\Category $category = null,
		MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner = null
	): Accessory {
		$category = $category ?? Types\Category::get(Types\Category::CATEGORY_OTHER);

		if ($category->equalsValue(Types\Category::CATEGORY_BRIDGE)) {
			$accessory = new Bridge($name);
		} else {
			$accessory = new Accessory($name, $aid, $category);
		}

		$accessoryInformation = $this->serviceFactory->create('AccessoryInformation', $accessory);

		$accessoryName = $this->characteristicsFactory->create('Name', $accessoryInformation);
		$accessoryName->setValue($name);

		$accessoryInformation->addCharacteristic($accessoryName);

		$accessorySerialNumber = $this->characteristicsFactory->create('SerialNumber', $accessoryInformation);
		$accessorySerialNumber->setValue('default');

		$accessoryInformation->addCharacteristic($accessorySerialNumber);

		$accessory->addService($accessoryInformation);

		return $accessory;
	}

}
