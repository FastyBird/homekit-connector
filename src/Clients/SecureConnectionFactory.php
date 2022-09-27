<?php declare(strict_types = 1);

/**
 * SecureConnectionFactory.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 * @since          0.19.0
 *
 * @date           17.09.22
 */

namespace FastyBird\HomeKitConnector\Clients;

use FastyBird\Metadata\Entities as MetadataEntities;
use React\Socket;

/**
 * HTTP secured server connection wrapper factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Clients
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface SecureConnectionFactory
{

	/**
	 * @param MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector
	 * @param Socket\ConnectionInterface $connection
	 *
	 * @return SecureConnection
	 */
	public function create(
		MetadataEntities\Modules\DevicesModule\IConnectorEntity $connector,
		Socket\ConnectionInterface $connection
	): SecureConnection;

}