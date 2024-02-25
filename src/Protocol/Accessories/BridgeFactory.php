<?php declare(strict_types = 1);

/**
 * BridgeFactory.php
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

/**
 * HAP bridge accessory factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Protocol
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class BridgeFactory
{

	public function create(string $name, Documents\Connectors\Connector $connector): Bridge
	{
		return new Bridge($name, $connector);
	}

	public function getEntityClass(): string
	{
		return Entities\Connectors\Connector::class;
	}

}
