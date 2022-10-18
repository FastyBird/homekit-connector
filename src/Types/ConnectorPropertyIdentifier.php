<?php declare(strict_types = 1);

/**
 * ConnectorPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          0.19.0
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
	public const IDENTIFIER_PORT = MetadataTypes\ConnectorPropertyIdentifier::IDENTIFIER_PORT;

	public const IDENTIFIER_PIN_CODE = 'pin_code';

	public const IDENTIFIER_XHM_URI = 'xhm_uri';

	public const IDENTIFIER_MAC_ADDRESS = 'mac_address';

	public const IDENTIFIER_SETUP_ID = 'setup_id';

	public const IDENTIFIER_CONFIG_VERSION = 'configuration_version';

	public const IDENTIFIER_PAIRED = 'paired';

	public const IDENTIFIER_SERVER_SECRET = 'server_secret';

	public const IDENTIFIER_CLIENT_PUBLIC_KEY = 'client_public_key';

	public const IDENTIFIER_SHARED_KEY = 'shared_key';

	public const IDENTIFIER_HASHING_KEY = 'hashing_key';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
