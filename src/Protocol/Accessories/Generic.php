<?php declare(strict_types = 1);

/**
 * Generic.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 * @since          1.0.0
 *
 * @date           12.04.23
 */

namespace FastyBird\Connector\HomeKit\Protocol\Accessories;

use FastyBird\Connector\HomeKit\Documents;
use FastyBird\Connector\HomeKit\Types;
use Ramsey\Uuid;

/**
 * HAP generic device accessory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Generic extends Accessory
{

	public function __construct(
		string $name,
		int|null $aid,
		Types\AccessoryCategory $category,
		private readonly Documents\Devices\Device $device,
	)
	{
		parent::__construct($name, $aid, $category);
	}

	public function getId(): Uuid\UuidInterface
	{
		return $this->device->getId();
	}

	public function getDevice(): Documents\Devices\Device
	{
		return $this->device;
	}

}
