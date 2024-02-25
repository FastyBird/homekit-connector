<?php declare(strict_types = 1);

/**
 * DevicePropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           13.02.23
 */

namespace FastyBird\Connector\HomeKit\Types;

use FastyBird\Module\Devices\Types as DevicesTypes;

/**
 * Device property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum DevicePropertyIdentifier: string
{

	case CATEGORY = 'category';

	case TYPE = 'type';

	case AID = 'aid';

	case MANUFACTURER = DevicesTypes\DevicePropertyIdentifier::FIRMWARE_MANUFACTURER->value;

	case VERSION = DevicesTypes\DevicePropertyIdentifier::FIRMWARE_VERSION->value;

	case SERIAL_NUMBER = DevicesTypes\DevicePropertyIdentifier::SERIAL_NUMBER->value;

	case MODEL = DevicesTypes\DevicePropertyIdentifier::HARDWARE_MODEL->value;

}
