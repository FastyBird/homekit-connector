<?php declare(strict_types = 1);

/**
 * MdnsFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use FastyBird\Library\Metadata\Entities as MetadataEntities;

/**
 * mDNS connector discovery server factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface MdnsFactory extends ServerFactory
{

	public function create(MetadataEntities\DevicesModule\Connector $connector): Mdns;

}
