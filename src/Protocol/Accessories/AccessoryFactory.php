<?php declare(strict_types = 1);

/**
 * AccessoryFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           29.01.24
 */

namespace FastyBird\Connector\HomeKit\Protocol\Accessories;

use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Types;

/**
 * HAP accessory factory interface
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface AccessoryFactory
{

	public function create(
		string $name,
		int|null $aid,
		Types\AccessoryCategory $category,
		Documents\Devices\Device $device,
	): Accessory;

	/**
	 * @return class-string<Entities\Devices\Device>
	 */
	public function getEntityClass(): string;

}
