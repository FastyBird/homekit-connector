<?php declare(strict_types = 1);

/**
 * CharacteristicType.php
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
 * HAP service characteristic type types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum CharacteristicType: string
{

	case ACCESSORY_FLAGS = 'AccessoryFlags';

	case ACTIVE = 'Active';

	case ACTIVE_IDENTIFIER = 'ActiveIdentifier';

	case ADMINISTRATOR_ONLY_ACCESS = 'AdministratorOnlyAccess';

	case AIR_PARTICULATE_DENSITY = 'AirParticulateDensity';

	case AIR_PARTICULATE_SIZE = 'AirParticulateSize';

	case AIR_QUALITY = 'AirQuality';

	case AUDIO_FEEDBACK = 'AudioFeedback';

	case BATTERY_LEVEL = 'BatteryLevel';

	case BRIGHTNESS = 'Brightness';

	case CARBON_DIOXIDE_DETECTED = 'CarbonDioxideDetected';

	case CARBON_DIOXIDE_LEVEL = 'CarbonDioxideLevel';

	case CARBON_DIOXIDE_PEAK_LEVEL = 'CarbonDioxidePeakLevel';

	case CARBON_MONOXIDE_DETECTED = 'CarbonMonoxideDetected';

	case CARBON_MONOXIDE_LEVEL = 'CarbonMonoxideLevel';

	case CARBON_MONOXIDE_PEAK_LEVEL = 'CarbonMonoxidePeakLevel';

	case CHARGING_STATE = 'ChargingState';

	case CLOSED_CAPTIONS = 'ClosedCaptions';

	case COLOR_TEMPERATURE = 'ColorTemperature';

	case CONFIGURED_NAME = 'ConfiguredName';

	case CONTACT_SENSOR_STATE = 'ContactSensorState';

	case COOLING_THRESHOLD_TEMPERATURE = 'CoolingThresholdTemperature';

	case CURRENT_AIR_PURIFIER_STATE = 'CurrentAirPurifierState';

	case CURRENT_AMBIENT_LIGHT_LEVEL = 'CurrentAmbientLightLevel';

	case CURRENT_DOOR_STATE = 'CurrentDoorState';

	case CURRENT_FAN_STATE = 'CurrentFanState';

	case CURRENT_HEATER_COOLER_STATE = 'CurrentHeaterCoolerState';

	case CURRENT_HEATING_COOLING_STATE = 'CurrentHeatingCoolingState';

	case CURRENT_HORIZONTAL_TILT_ANGLE = 'CurrentHorizontalTiltAngle';

	case CURRENT_HUMIDIFIER_DEHUMIDIFIER_STATE = 'CurrentHumidifierDehumidifierState';

	case CURRENT_MEDIA_STATE = 'CurrentMediaState';

	case CURRENT_POSITION = 'CurrentPosition';

	case CURRENT_RELATIVE_HUMIDITY = 'CurrentRelativeHumidity';

	case CURRENT_SLAT_STATE = 'CurrentSlatState';

	case CURRENT_TEMPERATURE = 'CurrentTemperature';

	case CURRENT_TILT_ANGLE = 'CurrentTiltAngle';

	case CURRENT_VERTICAL_TILT_ANGLE = 'CurrentVerticalTiltAngle';

	case CURRENT_VISIBILITY_STATE = 'CurrentVisibilityState';

	case DIGITAL_ZOOM = 'DigitalZoom';

	case DISPLAY_ORDER = 'DisplayOrder';

	case FILTER_CHANGE_INDICATION = 'FilterChangeIndication';

	case FILTER_LIFE_LEVEL = 'FilterLifeLevel';

	case FIRMWARE_REVISION = 'FirmwareRevision';

	case HARDWARE_REVISION = 'HardwareRevision';

	case HEATING_THRESHOLD_TEMPERATURE = 'HeatingThresholdTemperature';

	case HOLD_POSITION = 'HoldPosition';

	case HUE = 'Hue';

	case IDENTIFIER = 'Identifier';

	case IDENTIFY = 'Identify';

	case IMAGE_MIRRORING = 'ImageMirroring';

	case IMAGE_ROTATION = 'ImageRotation';

	case INPUT_SOURCE_TYPE = 'InputSourceType';

	case INPUT_DEVICE_TYPE = 'InputDeviceType';

	case IN_USE = 'InUse';

	case IS_CONFIGURED = 'IsConfigured';

	case LEAK_DETECTED = 'LeakDetected';

	case LOCK_CONTROL_POINT = 'LockControlPoint';

	case LOCK_CURRENT_STATE = 'LockCurrentState';

	case LOCK_LAST_KNOWN_ACTION = 'LockLastKnownAction';

	case LOCK_MANAGEMENT_AUTO_SECURITY_TIMEOUT = 'LockManagementAutoSecurityTimeout';

	case LOCK_PHYSICAL_CONTROLS = 'LockPhysicalControls';

	case LOCK_TARGET_STATE = 'LockTargetState';

	case LOGS = 'Logs';

	case MANUFACTURER = 'Manufacturer';

	case MODEL = 'Model';

	case MOTION_DETECTED = 'MotionDetected';

	case MUTE = 'Mute';

	case NAME = 'Name';

	case NIGHT_VISION = 'NightVision';

	case NITROGEN_DIOXIDE_DENSITY = 'NitrogenDioxideDensity';

	case OBSTRUCTION_DETECTED = 'ObstructionDetected';

	case OCCUPANCY_DETECTED = 'OccupancyDetected';

	case ON = 'On';

	case OPTICAL_ZOOM = 'OpticalZoom';

	case OUTLET_INUSE = 'OutletInUse';

	case OZONE_DENSITY = 'OzoneDensity';

	case PM_10_DENSITY = 'PM10Density';

	case PM_25_DENSITY = 'PM2.5Density';

	case PAIR_SETUP = 'PairSetup';

	case PAIR_VERIFY = 'PairVerify';

	case PAIRING_FEATURES = 'PairingFeatures';

	case PAIRING_PAIRINGS = 'PairingPairings';

	case PICTURE_MODE = 'PictureMode';

	case POSITION_STATE = 'PositionState';

	case POWER_MODE_SELECTION = 'PowerModeSelection';

	case PROGRAM_MODE = 'ProgramMode';

	case PROGRAMMABLE_SWITCH_EVENT = 'ProgrammableSwitchEvent';

	case RELATIVE_HUMIDITY_DEHUMIDIFIER_THRESHOLD = 'RelativeHumidityDehumidifierThreshold';

	case RELATIVE_HUMIDITY_HUMIDIFIER_THRESHOLD = 'RelativeHumidityHumidifierThreshold';

	case REMAINING_DURATION = 'RemainingDuration';

	case REMOTE_KEY = 'RemoteKey';

	case RESET_FILTER_INDICATION = 'ResetFilterIndication';

	case ROTATION_DIRECTION = 'RotationDirection';

	case ROTATION_SPEED = 'RotationSpeed';

	case SATURATION = 'Saturation';

	case SECURITY_SYSTEM_ALARM_TYPE = 'SecuritySystemAlarmType';

	case SECURITY_SYSTEM_CURRENT_STATE = 'SecuritySystemCurrentState';

	case SECURITY_SYSTEM_TARGET_STATE = 'SecuritySystemTargetState';

	case SELECTED_RTP_STREAM_CONFIGURATION = 'SelectedRTPStreamConfiguration';

	case SERIAL_NUMBER = 'SerialNumber';

	case SERVICE_LABEL_INDEX = 'ServiceLabelIndex';

	case SERVICE_LABEL_NAMESPACE = 'ServiceLabelNamespace';

	case SET_DURATION = 'SetDuration';

	case SETUP_ENDPOINTS = 'SetupEndpoints';

	case SLAT_TYPE = 'SlatType';

	case SLEEP_DISCOVERY_MODE = 'SleepDiscoveryMode';

	case SMOKE_DETECTED = 'SmokeDetected';

	case STATUS_ACTIVE = 'StatusActive';

	case STATUS_FAULT = 'StatusFault';

	case STATUS_JAMMED = 'StatusJammed';

	case STATUS_LOW_BATTERY = 'StatusLowBattery';

	case STATUS_TAMPERED = 'StatusTampered';

	case STREAMING_STATUS = 'StreamingStatus';

	case SULPHUR_DIOXIDE_DENSITY = 'SulphurDioxideDensity';

	case SUPPORTED_AUDIO_STREAM_CONFIGURATION = 'SupportedAudioStreamConfiguration';

	case SUPPORTED_RTP_CONFIGURATION = 'SupportedRTPConfiguration';

	case SUPPORTED_VIDEO_STREAM_CONFIGURATION = 'SupportedVideoStreamConfiguration';

	case SWING_MODE = 'SwingMode';

	case TARGET_AIR_PURIFIER_STATE = 'TargetAirPurifierState';

	case TARGET_AIR_QUALITY = 'TargetAirQuality';

	case TARGET_DOOR_STATE = 'TargetDoorState';

	case TARGET_FAN_STATE = 'TargetFanState';

	case TARGET_HEATER_COOLER_STATE = 'TargetHeaterCoolerState';

	case TARGET_HEATING_COOLING_STATE = 'TargetHeatingCoolingState';

	case TARGET_HORIZONTAL_TILT_ANGLE = 'TargetHorizontalTiltAngle';

	case TARGET_HUMIDIFIER_DEHUMIDIFIER_STATE = 'TargetHumidifierDehumidifierState';

	case TARGET_MEDIA_STATE = 'TargetMediaState';

	case TARGET_POSITION = 'TargetPosition';

	case TARGET_RELATIVE_HUMIDITY = 'TargetRelativeHumidity';

	case TARGET_SLAT_STATE = 'TargetSlatState';

	case TARGET_TEMPERATURE = 'TargetTemperature';

	case TARGET_TILT_ANGLE = 'TargetTiltAngle';

	case TARGET_VERTICAL_TILT_ANGLE = 'TargetVerticalTiltAngle';

	case TARGET_VISIBILITY_STATE = 'TargetVisibilityState';

	case TEMPERATURE_DISPLAY_UNITS = 'TemperatureDisplayUnits';

	case VOC_DENSITY = 'VOCDensity';

	case VALVE_TYPE = 'ValveType';

	case VERSION = 'Version';

	case VOLUME = 'Volume';

	case VOLUME_CONTROL_TYPE = 'VolumeControlType';

	case VOLUME_SELECTOR = 'VolumeSelector';

	case WATER_LEVEL = 'WaterLevel';

	case COLOR_RED = 'ColorRed';

	case COLOR_GREEN = 'ColorGreen';

	case COLOR_BLUE = 'ColorBlue';

	case COLOR_WHITE = 'ColorWhite';

}
