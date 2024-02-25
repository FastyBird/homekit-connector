<?php declare(strict_types = 1);

/**
 * Connector.php
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

namespace FastyBird\Connector\HomeKit\Hydrators\Connectors;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Module\Devices\Hydrators as DevicesHydrators;

/**
 * HomeKit connector entity hydrator
 *
 * @extends DevicesHydrators\Connectors\Connector<Entities\Connectors\Connector>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Connector extends DevicesHydrators\Connectors\Connector
{

	public function getEntityName(): string
	{
		return Entities\Connectors\Connector::class;
	}

}
