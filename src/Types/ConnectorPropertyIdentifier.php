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

namespace FastyBird\HomeKitConnector\Types;

use Consistence;
use FastyBird\Metadata\Types as MetadataTypes;

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
	public const IDENTIFIER_PORT = MetadataTypes\ConnectorPropertyIdentifierType::IDENTIFIER_PORT;
	public const IDENTIFIER_PIN_CODE = 'pin_code';
	public const IDENTIFIER_XHM_URI = 'xhm_uri';
	public const IDENTIFIER_MAC_ADDRESS = 'mac_address';
	public const IDENTIFIER_SETUP_ID = 'setup_id';
	public const IDENTIFIER_CONFIG_VERSION = 'configuration_version';
	public const IDENTIFIER_PAIRED = 'paired';
	public const IDENTIFIER_PAIRING_ATTEMPTS = 'pairing_attempts';
	public const IDENTIFIER_PAIRING_SETUP_MODE = 'pairing_setup_mode';

	/**
	 * @return string
	 */
	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
