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

/**
 * HAP accessory service type types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ServiceType: string
{

	case ACCESSORY_INFORMATION = 'AccessoryInformation';

	case AIR_PURIFIER = 'AirPurifier';

	case AIR_QUALITY_SENSOR = 'AirQualitySensor';

	case BATTERY = 'BatteryService';

	case CAMERA_RTP_STREAM_MANAGEMENT = 'CameraRTPStreamManagement';

	case CARBON_DIOXIDE_SENSOR = 'CarbonDioxideSensor';

	case CARBON_MONOXIDE_SENSOR = 'CarbonMonoxideSensor';

	case CONTACT_SENSOR = 'ContactSensor';

	case DOOR = 'Door';

	case DOORBELL = 'Doorbell';

	case FAN = 'Fan';

	case FAN_V2 = 'Fanv2';

	case FAUCET = 'Faucet';

	case FILTER_MAINTENANCE = 'FilterMaintenance';

	case GARAGE_DOOR_OPENER = 'GarageDoorOpener';

	case HEATER_COOLER = 'HeaterCooler';

	case HUMIDIFIER_DEHUMIDIFIER = 'HumidifierDehumidifier';

	case HUMIDITY_SENSOR = 'HumiditySensor';

	case INPUT_SOURCE = 'InputSource';

	case IRRIGATION_SYSTEM = 'IrrigationSystem';

	case LEAK_SENSOR = 'LeakSensor';

	case LIGHT_SENSOR = 'LightSensor';

	case LIGHT_BULB = 'Lightbulb';

	case LOCK_MANAGEMENT = 'LockManagement';

	case LOCK_MECHANISM = 'LockMechanism';

	case MICROPHONE = 'Microphone';

	case MOTION_SENSOR = 'MotionSensor';

	case OCCUPANCY_SENSOR = 'OccupancySensor';

	case OUTLET = 'Outlet';

	case SECURITY_SYSTEM = 'SecuritySystem';

	case SERVICE_LABEL = 'ServiceLabel';

	case SLAT = 'Slat';

	case SMOKE_SENSOR = 'SmokeSensor';

	case SPEAKER = 'Speaker';

	case STATELESS_PROGRAMMABLE_SWITCH = 'StatelessProgrammableSwitch';

	case SWITCH = 'Switch';

	case TELEVISION = 'Television';

	case TELEVISION_SPEAKER = 'TelevisionSpeaker';

	case TEMPERATURE_SENSOR = 'TemperatureSensor';

	case THERMOSTAT = 'Thermostat';

	case VALVE = 'Valve';

	case WINDOW = 'Window';

	case WINDOW_COVERING = 'WindowCovering';

	case PROTOCOL_INFORMATION = 'HAPProtocolInformation';

}
