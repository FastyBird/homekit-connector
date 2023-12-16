<?php declare(strict_types = 1);

/**
 * AccessoryCategory.php
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
use function intval;
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
	public const OTHER = 1;

	public const BRIDGE = 2;

	public const FAN = 3;

	public const GARAGE_DOOR_OPENER = 4;

	public const LIGHT_BULB = 5;

	public const DOOR_LOCK = 6;

	public const OUTLET = 7;

	public const SWITCH = 8;

	public const THERMOSTAT = 9;

	public const SENSOR = 10;

	public const ALARM_SYSTEM = 11;

	public const DOOR = 12;

	public const WINDOW = 13;

	public const WINDOW_COVERING = 14;

	public const PROGRAMMABLE_SWITCH = 15;

	public const RANGE_EXTENDER = 16;

	public const CAMERA = 17;

	public const VIDEO_DOOR_BELL = 18;

	public const AIR_PURIFIER = 19;

	public const HEATER = 20;

	public const AIR_CONDITIONER = 21;

	public const HUMIDIFIER = 22;

	public const DEHUMIDIFIER = 23;

	public const SPEAKER = 26;

	public const SPRINKLER = 28;

	public const FAUCET = 29;

	public const SHOWER_HEAD = 30;

	public const TELEVISION = 31;

	public const TARGET_CONTROLLER = 32; // Remote Controller

	public function getValue(): int
	{
		return intval(parent::getValue());
	}

	/**
	 * @return array<int>
	 */
	public static function getValues(): array
	{
		/** @var iterable<int> $availableValues */
		$availableValues = parent::getAvailableValues();

		return (array) $availableValues;
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
