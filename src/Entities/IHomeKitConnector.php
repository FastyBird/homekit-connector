<?php declare(strict_types = 1);

/**
 * IHomeKitConnector.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 * @since          0.1.0
 *
 * @date           29.03.22
 */

namespace FastyBird\HomeKitConnector\Entities;

use FastyBird\DevicesModule\Entities as DevicesModuleEntities;

/**
 * HomeKit connector entity interface
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Entities
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface IHomeKitConnector extends DevicesModuleEntities\Connectors\IConnector
{

	/**
	 * @return int
	 */
	public function getPort(): int;

}
