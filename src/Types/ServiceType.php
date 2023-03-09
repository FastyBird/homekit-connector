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
	public const TYPE_ACCESSORY_INFORMATION = 'AccessoryInformation';

	public const TYPE_AIR_PURIFIER = 'AirPurifier';

	public const TYPE_AIR_QUALITY_SENSOR = 'AirQualitySensor';

	public const TYPE_BATTERY_SERVICE = 'BatteryService';

	public const TYPE_CAMERA_RTP_STREAM_MANAGEMENT = 'CameraRTPStreamManagement';

	public const TYPE_CARBON_DIOXIDE_SENSOR = 'CarbonDioxideSensor';

	public const TYPE_CARBON_MONOXIDE_SENSOR = 'CarbonMonoxideSensor';

	public const TYPE_CONTACT_SENSOR = 'ContactSensor';

	public const TYPE_DOOR = 'Door';

	public const TYPE_DOORBELL = 'Doorbell';

	public const TYPE_FAN = 'Fan';

	public const TYPE_FAN_V2 = 'Fanv2';

	public const TYPE_FAUCET = 'Faucet';

	public const TYPE_FILTER_MAINTENANCE = 'FilterMaintenance';

	public const TYPE_GARAGE_DOOR_OPENER = 'GarageDoorOpener';

	public const TYPE_HEATER_COOLER = 'HeaterCooler';

	public const TYPE_HUMIDIFIER_DEHUMIDIFIER = 'HumidifierDehumidifier';

	public const TYPE_HUMIDITY_SENSOR = 'HumiditySensor';

	public const TYPE_INPUT_SOURCE = 'InputSource';

	public const TYPE_IRRIGATION_SYSTEM = 'IrrigationSystem';

	public const TYPE_LEAK_SENSOR = 'LeakSensor';

	public const TYPE_LIGHT_SENSOR = 'LightSensor';

	public const TYPE_LIGHTBULB = 'Lightbulb';

	public const TYPE_LOCK_MANAGEMENT = 'LockManagement';

	public const TYPE_LOCK_MECHANISM = 'LockMechanism';

	public const TYPE_MICROPHONE = 'Microphone';

	public const TYPE_MOTION_SENSOR = 'MotionSensor';

	public const TYPE_OCCUPANCY_SENSOR = 'OccupancySensor';

	public const TYPE_OUTLET = 'Outlet';

	public const TYPE_SECURITY_SYSTEM = 'SecuritySystem';

	public const TYPE_SERVICE_LABEL = 'ServiceLabel';

	public const TYPE_SLAT = 'Slat';

	public const type_smoke_sensor = 'SmokeSensor';

	public const TYPE_SPEAKER = 'Speaker';

	public const TYPE_STATELESS_PROGRAMMABLE_SWITCH = 'StatelessProgrammableSwitch';

	public const TYPE_SWITCH = 'Switch';

	public const TYPE_TELEVISION = 'Television';

	public const TYPE_TELEVISION_SPEAKER = 'TelevisionSpeaker';

	public const TYPE_TEMPERATURE_SENSOR = 'TemperatureSensor';

	public const TYPE_THERMOSTAT = 'Thermostat';

	public const TYPE_VALVE = 'Valve';

	public const TYPE_WINDOW = 'Window';

	public const TYPE_WINDOW_COVERING = 'WindowCovering';

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
