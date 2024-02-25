<?php declare(strict_types = 1);

/**
 * SecureConnectionFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          1.0.0
 *
 * @date           17.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use FastyBird\Connector\HomeKit\Documents;
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
		Documents\Connectors\Connector $connector,
		string|null $sharedKey,
		Socket\ConnectionInterface $connection,
	): SecureConnection;

}
