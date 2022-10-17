<?php declare(strict_types = 1);

/**
 * AccessoryCategory.php
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
use function strval;

/**
 * HAP accessory category type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class AccessoryCategory extends Consistence\Enum\Enum
{

	/**
	 * Define categories
	 */
	public const CATEGORY_OTHER = 1;

	public const CATEGORY_BRIDGE = 2;

	public const CATEGORY_FAN = 3;

	public const CATEGORY_GARAGE_DOOR_OPENER = 4;

	public const CATEGORY_LIGHT_BULB = 5;

	public const CATEGORY_DOOR_LOCK = 6;

	public const CATEGORY_OUTLET = 7;

	public const CATEGORY_SWITCH = 8;

	public const CATEGORY_THERMOSTAT = 9;

	public const CATEGORY_SENSOR = 10;

	public const CATEGORY_ALARM_SYSTEM = 11;

	public const CATEGORY_DOOR = 12;

	public const CATEGORY_WINDOW = 13;

	public const CATEGORY_WINDOW_COVERING = 14;

	public const CATEGORY_PROGRAMMABLE_SWITCH = 15;

	public const CATEGORY_RANGE_EXTENDER = 16;

	public const CATEGORY_CAMERA = 17;

	public const CATEGORY_VIDEO_DOOR_BELL = 18;

	public const CATEGORY_AIR_PURIFIER = 19;

	public const CATEGORY_HEATER = 20;

	public const CATEGORY_AIR_CONDITIONER = 21;

	public const CATEGORY_HUMIDIFIER = 22;

	public const CATEGORY_DEHUMIDIFIER = 23;

	public const CATEGORY_SPEAKER = 26;

	public const CATEGORY_SPRINKLER = 28;

	public const CATEGORY_FAUCET = 29;

	public const CATEGORY_SHOWER_HEAD = 30;

	public const CATEGORY_TELEVISION = 31;

	public const CATEGORY_TARGET_CONTROLLER = 32; // Remote Controller

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
