<?php declare(strict_types = 1);

/**
 * SecureServerFactory.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 * @since          1.0.0
 *
 * @date           26.09.22
 */

namespace FastyBird\Connector\HomeKit\Servers;

use FastyBird\Connector\HomeKit\Documents;
use React\Socket;

/**
 * HTTP secured server wrapper factory
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Servers
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
interface SecureServerFactory
{

	public function create(
		Documents\Connectors\Connector $connector,
		Socket\ServerInterface $server,
		string|null $sharedKey = null,
	): SecureServer;

}
