<?php declare(strict_types = 1);

/**
 * MdnsFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Servers;

use FastyBird\Metadata\Entities as MetadataEntities;

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

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return Mdns
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): Mdns;

}
