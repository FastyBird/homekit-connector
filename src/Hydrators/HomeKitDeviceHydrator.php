<?php declare(strict_types = 1);

/**
 * HomeKitDeviceHydrator.php
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
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 *
 * @phpstan-extends DevicesModuleHydrators\Devices\DeviceHydrator<Entities\IHomeKitDevice>
 */
final class HomeKitDeviceHydrator extends DevicesModuleHydrators\Devices\DeviceHydrator
{

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\HomeKitDevice::class;
	}

}
