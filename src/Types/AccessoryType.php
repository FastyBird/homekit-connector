<?php declare(strict_types = 1);

/**
 * AccessoryType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           12.04.23
 */

namespace FastyBird\Connector\HomeKit\Types;

/**
 * HAP accessory type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum AccessoryType: string
{

	case GENERIC = 'generic';

	case LIGHT_HSB = 'light_hsb';

}
