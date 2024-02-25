<?php declare(strict_types = 1);

/**
 * CharacteristicPermission.php
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

/**
 * HAP accessory permissions type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum CharacteristicPermission: string
{

	case HIDDEN = 'hd';

	case NOTIFY = 'ev';

	case READ = 'pr';

	case WRITE = 'pw';

	case WRITE_RESPONSE = 'wr';

	case TIMED_WRITE = 'tw';

	case ADDITIONAL_AUTHORIZATION = 'aa';

}
