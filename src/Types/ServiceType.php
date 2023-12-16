<?php declare(strict_types = 1);

/**
 * ServiceType.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           04.03.23
 */

namespace FastyBird\Connector\HomeKit\Types;

use Consistence;
use function strval;

/**
 * HAP accessory service type types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ServiceType extends Consistence\Enum\Enum
{

	/**
	 * Define statuses
	 */
	public const ACCESSORY_INFORMATION = 'AccessoryInformation';

	public const AIR_PURIFIER = 'AirPurifier';

	public const AIR_QUALITY_SENSOR = 'AirQualitySensor';

	public const BATTERY_SERVICE = 'BatteryService';

	public const CAMERA_RTP_STREAM_MANAGEMENT = 'CameraRTPStreamManagement';

	public const CARBON_DIOXIDE_SENSOR = 'CarbonDioxideSensor';

	public const CARBON_MONOXIDE_SENSOR = 'CarbonMonoxideSensor';

	public const CONTACT_SENSOR = 'ContactSensor';

	public const DOOR = 'Door';

	public const DOORBELL = 'Doorbell';

	public const FAN = 'Fan';

	public const FAN_V2 = 'Fanv2';

	public const FAUCET = 'Faucet';

	public const FILTER_MAINTENANCE = 'FilterMaintenance';

	public const GARAGE_DOOR_OPENER = 'GarageDoorOpener';

	public const HEATER_COOLER = 'HeaterCooler';

	public const HUMIDIFIER_DEHUMIDIFIER = 'HumidifierDehumidifier';

	public const HUMIDITY_SENSOR = 'HumiditySensor';

	public const INPUT_SOURCE = 'InputSource';

	public const IRRIGATION_SYSTEM = 'IrrigationSystem';

	public const LEAK_SENSOR = 'LeakSensor';

	public const LIGHT_SENSOR = 'LightSensor';

	public const LIGHTBULB = 'Lightbulb';

	public const LOCK_MANAGEMENT = 'LockManagement';

	public const LOCK_MECHANISM = 'LockMechanism';

	public const MICROPHONE = 'Microphone';

	public const MOTION_SENSOR = 'MotionSensor';

	public const OCCUPANCY_SENSOR = 'OccupancySensor';

	public const OUTLET = 'Outlet';

	public const SECURITY_SYSTEM = 'SecuritySystem';

	public const SERVICE_LABEL = 'ServiceLabel';

	public const SLAT = 'Slat';

	public const SMOKE_SENSOR = 'SmokeSensor';

	public const SPEAKER = 'Speaker';

	public const STATELESS_PROGRAMMABLE_SWITCH = 'StatelessProgrammableSwitch';

	public const SWITCH = 'Switch';

	public const TELEVISION = 'Television';

	public const TELEVISION_SPEAKER = 'TelevisionSpeaker';

	public const TEMPERATURE_SENSOR = 'TemperatureSensor';

	public const THERMOSTAT = 'Thermostat';

	public const VALVE = 'Valve';

	public const WINDOW = 'Window';

	public const WINDOW_COVERING = 'WindowCovering';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
