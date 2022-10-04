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

use FastyBird\HomeKitConnector\Types;
use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * HAP bridge accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Device extends Accessory
{

	/**
	 * @param string $name
	 * @param int|null $aid
	 * @param Types\AccessoryCategory $category
	 * @param MetadataEntities\Modules\DevicesModule\IDeviceEntity $device
	 */
	public function __construct(
		string $name,
		int|null $aid,
		Types\AccessoryCategory $category,
		private MetadataEntities\Modules\DevicesModule\IDeviceEntity $device,
	) {
		parent::__construct($name, $aid, $category);
	}

	/**
	 * @return MetadataEntities\Modules\DevicesModule\IDeviceEntity
	 */
	public function getDevice(): MetadataEntities\Modules\DevicesModule\IDeviceEntity
	{
		return $this->device;
	}

}
