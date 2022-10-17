<?php declare(strict_types = 1);

/**
 * HomeKit.php
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

namespace FastyBird\Connector\HomeKit\Hydrators;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\DevicesModule\Hydrators as DevicesModuleHydrators;

/**
 * HomeKit connector entity hydrator
 *
 * @extends DevicesModuleHydrators\Connectors\Connector<Entities\HomeKitConnector>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitConnector extends DevicesModuleHydrators\Connectors\Connector
{

	public function getEntityName(): string
	{
		return Entities\HomeKitConnector::class;
	}

}
