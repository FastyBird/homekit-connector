<?php declare(strict_types = 1);

/**
 * SecureConnectionFactory.php
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
use React\Socket;

/**
 * HTTP secured server connection wrapper factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface SecureConnectionFactory
{

	public function create(
		MetadataEntities\DevicesModule\Connector $connector,
		string|null $sharedKey,
		Socket\ConnectionInterface $connection,
	): SecureConnection;

}
