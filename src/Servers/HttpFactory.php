<?php declare(strict_types = 1);

/**
 * HttpFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          0.19.0
 *
 * @date           19.09.22
 */

namespace FastyBird\HomeKitConnector\Servers;

use FastyBird\Metadata\Entities as MetadataEntities;

/**
 * HTTP connector communication server factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface HttpFactory extends ServerFactory
{

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 *
	 * @return Http
	 */
	public function create(MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector): Http;

}