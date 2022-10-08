<?php declare(strict_types = 1);

/**
 * HomeKitDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Hydrators;

use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;
use FastyBird\HomeKitConnector\Entities;

/**
 * HomeKit device entity hydrator
 *
 * @extends DevicesModuleHydrators\Devices\Device<Entities\HomeKitDevice>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitDevice extends DevicesModuleHydrators\Devices\Device
{

	public function getEntityName(): string
	{
		return Entities\HomeKitDevice::class;
	}

}
