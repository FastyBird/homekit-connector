<?php declare(strict_types = 1);

/**
 * Writer.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 * @since          1.0.0
 *
 * @date           11.02.23
 */

namespace FastyBird\Connector\HomeKit\Writers;

use FastyBird\Connector\HomeKit\Entities;
use FastyBird\Connector\HomeKit\Servers;

/**
 * Properties writer interface
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Writers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface Writer
{

	/**
	 * @param array<Servers\Server> $servers
	 */
	public function connect(Entities\HomeKitConnector $connector, array $servers): void;

	/**
	 * @param array<Servers\Server> $servers
	 */
	public function disconnect(Entities\HomeKitConnector $connector, array $servers): void;

}
