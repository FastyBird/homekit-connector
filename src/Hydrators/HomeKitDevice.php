<?php declare(strict_types = 1);

/**
 * HomeKitDevice.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @since          1.0.0
 *
 * @date           29.03.22
 */

namespace FastyBird\Connector\HomeKit\Hydrators;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Module\Devices\Hydrators as DevicesHydrators;

/**
 * HomeKit device entity hydrator
 *
 * @extends DevicesHydrators\Devices\Device<Entities\HomeKitDevice>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitDevice extends DevicesHydrators\Devices\Device
{

	public function getEntityName(): string
	{
		return Entities\HomeKitDevice::class;
	}

}
