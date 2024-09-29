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

/**
 * HAP accessory category type
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum AccessoryCategory: int
{

	case OTHER = 1;

	case BRIDGE = 2;

	case FAN = 3;

	case GARAGE_DOOR_OPENER = 4;

	case LIGHT_BULB = 5;

	case DOOR_LOCK = 6;

	case OUTLET = 7;

	case SWITCH = 8;

	case THERMOSTAT = 9;

	case SENSOR = 10;

	case ALARM_SYSTEM = 11;

	case DOOR = 12;

	case WINDOW = 13;

	case WINDOW_COVERING = 14;

	case PROGRAMMABLE_SWITCH = 15;

	case RANGE_EXTENDER = 16;

	case CAMERA = 17;

	case VIDEO_DOOR_BELL = 18;

	case AIR_PURIFIER = 19;

	case HEATER = 20;

	case AIR_CONDITIONER = 21;

	case HUMIDIFIER = 22;

	case DEHUMIDIFIER = 23;

	case APPLE_TV = 24;

	case HOMEPOD = 25;

	case SPEAKER = 26;

	case AIRPORT = 27;

	case SPRINKLER = 28;

	case FAUCET = 29;

	case SHOWER_HEAD = 30;

	case TELEVISION = 31;

	case TARGET_CONTROLLER = 32; // Remote Controller

	case ROUTER = 33;

	case AUDIO_RECEIVER = 34;

	case TV_SET_TOP_BOX = 35;

	case TV_STREAMING_STICK = 36;

}
