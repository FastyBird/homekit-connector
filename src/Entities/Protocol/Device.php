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

	/** @var MetadataEntities\Modules\DevicesModule\DeviceEntity */
	private MetadataEntities\Modules\DevicesModule\DeviceEntity $device;

	/**
	 * @param string $name
	 * @param int|null $aid
	 * @param Types\AccessoryCategory $category
	 * @param MetadataEntities\Modules\DevicesModule\DeviceEntity $device
	 */
	public function __construct(
		string $name,
		?int $aid,
		Types\AccessoryCategory $category,
		MetadataEntities\Modules\DevicesModule\DeviceEntity $device
	) {
		parent::__construct(
			$name,
			$aid,
			$category
		);

		$this->device = $device;
	}

	/**
	 * @return MetadataEntities\Modules\DevicesModule\DeviceEntity
	 */
	public function getDevice(): MetadataEntities\Modules\DevicesModule\DeviceEntity
	{
		return $this->device;
	}

}
