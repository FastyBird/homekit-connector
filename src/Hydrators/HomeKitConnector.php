<?php declare(strict_types = 1);

/**
 * HomeKitConnector.php
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
 * HomeKit connector entity hydrator
 *
 * @phpstan-extends DevicesModuleHydrators\Connectors\ConnectorHydrator<Entities\HomeKitConnector>
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Hydrators
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class HomeKitConnector extends DevicesModuleHydrators\Connectors\ConnectorHydrator
{

	/**
	 * {@inheritDoc}
	 */
	public function getEntityName(): string
	{
		return Entities\HomeKitConnector::class;
	}

}
