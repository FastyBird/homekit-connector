<?php declare(strict_types = 1);

/**
 * Bridge.php
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

use FastyBird\HomeKitConnector;
use FastyBird\HomeKitConnector\Exceptions;
use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;
use SplObjectStorage;

/**
 * HAP bridge accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Bridge extends Accessory
{

	/** @var SplObjectStorage<Accessory, null> */
	private SplObjectStorage $accessories;

	/**
	 * @param string $name
	 * @param MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner
	 */
	public function __construct(
		string $name,
		MetadataEntities\Modules\DevicesModule\ConnectorEntity|MetadataEntities\Modules\DevicesModule\DeviceEntity|null $owner = null
	) {
		parent::__construct(
			$name,
			HomeKitConnector\Constants::STANDALONE_AID,
			Types\Category::get(Types\Category::CATEGORY_BRIDGE),
			$owner
		);

		$this->accessories = new SplObjectStorage();
	}

	/**
	 * @param Accessory $accessory
	 *
	 * @return void
	 */
	public function addAccessory(Accessory $accessory): void
	{
		if ($accessory->getCategory()->equalsValue(Types\Category::CATEGORY_BRIDGE)) {
			throw new Exceptions\InvalidArgument('Bridges cannot be bridged');
		}

		$this->accessories->attach($accessory);
	}

	/**
	 * @return Accessory[]
	 */
	public function getAccessories(): array
	{
		$this->accessories->rewind();

		$accessories = [];

		foreach ($this->accessories as $accessory) {
			$accessories[] = $accessory;
		}

		return $accessories;
	}

}
