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

use FastyBird\Module\Devices\Types as DevicesTypes;

/**
 * Connector property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ConnectorPropertyIdentifier: string
{

	case PORT = DevicesTypes\ConnectorPropertyIdentifier::PORT->value;

	case PIN_CODE = 'pin_code';

	case MAC_ADDRESS = 'mac_address';

	case SETUP_ID = 'setup_id';

	case CONFIG_VERSION = 'configuration_version';

	case PAIRED = 'paired';

	case SERVER_SECRET = 'server_secret';

	case CLIENT_PUBLIC_KEY = 'client_public_key';

	case SHARED_KEY = 'shared_key';

	case HASHING_KEY = 'hashing_key';

	case XHM_URI = 'xhm_uri';

}
