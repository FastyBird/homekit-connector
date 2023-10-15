<?php declare(strict_types = 1);

/**
 * ConnectorPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use FastyBird\Library\Metadata\Types as MetadataTypes;
use function strval;

/**
 * Connector property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ConnectorPropertyIdentifier extends Consistence\Enum\Enum
{

	/**
	 * Define connector properties identifiers
	 */
	public const PORT = MetadataTypes\ConnectorPropertyIdentifier::IDENTIFIER_PORT;

	public const PIN_CODE = 'pin_code';

	public const MAC_ADDRESS = 'mac_address';

	public const SETUP_ID = 'setup_id';

	public const CONFIG_VERSION = 'configuration_version';

	public const PAIRED = 'paired';

	public const SERVER_SECRET = 'server_secret';

	public const CLIENT_PUBLIC_KEY = 'client_public_key';

	public const SHARED_KEY = 'shared_key';

	public const HASHING_KEY = 'hashing_key';

	public const XHM_URI = 'xhm_uri';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
