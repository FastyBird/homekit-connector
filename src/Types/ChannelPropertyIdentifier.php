<?php declare(strict_types = 1);

/**
 * ChannelPropertyIdentifier.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           06.10.22
 */

namespace FastyBird\Connector\HomeKit\Types;

/**
 * Channel property identifier types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
enum ChannelPropertyIdentifier: string
{

	case ACCESSORY_FLAGS = 'accessory_flags';

	case ACTIVE = 'active';

	case ACTIVE_IDENTIFIER = 'active_identifier';

	case ADMINISTRATOR_ONLY_ACCESS = 'administrator_only_access';

	case AIR_PARTICULATE_DENSITY = 'air_particulate_density';

	case AIR_PARTICULATE_SIZE = 'air_particulate_size';

	case AIR_QUALITY = 'air_quality';

	case AUDIO_FEEDBACK = 'audio_feedback';

	case BATTERY_LEVEL = 'battery_level';

	case BRIGHTNESS = 'brightness';

	case CARBON_DIOXIDE_DETECTED = 'carbon_dioxide_detected';

	case CARBON_DIOXIDE_LEVEL = 'carbon_dioxide_level';

	case CARBON_DIOXIDE_PEAK_LEVEL = 'carbon_dioxide_peak_level';

	case CARBON_MONOXIDE_DETECTED = 'carbon_monoxide_detected';

	case CARBON_MONOXIDE_LEVEL = 'carbon_monoxide_level';

	case CARBON_MONOXIDE_PEAK_LEVEL = 'carbon_monoxide_peak_level';

	case CHARGING_STATE = 'charging_state';

	case CLOSED_CAPTIONS = 'closed_captions';

	case COLOR_TEMPERATURE = 'color_temperature';

	case CONFIGURED_NAME = 'configured_name';

	case CONTACT_SENSOR_STATE = 'contact_sensor_state';

	case COOLING_THRESHOLD_TEMPERATURE = 'cooling_threshold_temperature';

	case CURRENT_AIR_PURIFIER_STATE = 'current_air_purifier_state';

	case CURRENT_AMBIENT_LIGHT_LEVEL = 'current_ambient_light_level';

	case CURRENT_DOOR_STATE = 'current_door_state';

	case CURRENT_FAN_STATE = 'current_fan_state';

	case CURRENT_HEATER_COOLER_STATE = 'current_heater_cooler_state';

	case CURRENT_HEATING_COOLING_STATE = 'current_heating_cooling_state';

	case CURRENT_HORIZONTAL_TILT_ANGLE = 'current_horizontal_tilt_angle';

	case CURRENT_HUMIDIFIER_DEHUMIDIFIER_STATE = 'current_humidifier_dehumidifier_state';

	case CURRENT_MEDIA_STATE = 'current_media_state';

	case CURRENT_POSITION = 'current_position';

	case CURRENT_RELATIVE_HUMIDITY = 'current_relative_humidity';

	case CURRENT_SLAT_STATE = 'current_slat_state';

	case CURRENT_TEMPERATURE = 'current_temperature';

	case CURRENT_TILT_ANGLE = 'current_tilt_angle';

	case CURRENT_VERTICAL_TILT_ANGLE = 'current_vertical_tilt_angle';

	case CURRENT_VISIBILITY_STATE = 'current_visibility_state';

	case DIGITAL_ZOOM = 'digital_zoom';

	case DISPLAY_ORDER = 'display_order';

	case FILTER_CHANGE_INDICATION = 'filter_change_indication';

	case FILTER_LIFE_LEVEL = 'filter_life_level';

	case FIRMWARE_REVISION = 'firmware_revision';

	case HARDWARE_REVISION = 'hardware_revision';

	case HEATING_THRESHOLD_TEMPERATURE = 'heating_threshold_temperature';

	case HOLD_POSITION = 'hold_position';

	case HUE = 'hue';

	case IDENTIFIER = 'identifier';

	case IDENTIFY = 'identify';

	case IMAGE_MIRRORING = 'image_mirroring';

	case IMAGE_ROTATION = 'image_rotation';

	case INPUT_SOURCE_TYPE = 'input_source_type';

	case INPUT_DEVICE_TYPE = 'input_device_type';

	case IN_USE = 'in_use';

	case IS_CONFIGURED = 'is_configured';

	case LEAK_DETECTED = 'leak_detected';

	case LOCK_CONTROL_POINT = 'lock_control_point';

	case LOCK_CURRENT_STATE = 'lock_current_state';

	case LOCK_LAST_KNOWN_ACTION = 'lock_last_known_action';

	case LOCK_MANAGEMENT_AUTO_SECURITY_TIMEOUT = 'lock_management_auto_security_timeout';

	case LOCK_PHYSICAL_CONTROLS = 'lock_physical_controls';

	case LOCK_TARGET_STATE = 'lock_target_state';

	case LOGS = 'logs';

	case MANUFACTURER = 'manufacturer';

	case MODEL = 'model';

	case MOTION_DETECTED = 'motion_detected';

	case MUTE = 'mute';

	case NAME = 'name';

	case NIGHT_VISION = 'night_vision';

	case NITROGEN_DIOXIDE_DENSITY = 'nitrogen_dioxide_density';

	case OBSTRUCTION_DETECTED = 'obstruction_detected';

	case OCCUPANCY_DETECTED = 'occupancy_detected';

	case ON = 'on';

	case OPTICAL_ZOOM = 'optical_zoom';

	case OUTLET_INUSE = 'outlet_in_use';

	case OZONE_DENSITY = 'ozone_density';

	case PM_10_DENSITY = 'pm10_density';

	case PM_25_DENSITY = 'pm2.5density';

	case PAIR_SETUP = 'pair_setup';

	case PAIR_VERIFY = 'pair_verify';

	case PAIRING_FEATURES = 'pairing_features';

	case PAIRING_PAIRINGS = 'pairing_pairings';

	case PICTURE_MODE = 'picture_mode';

	case POSITION_STATE = 'position_state';

	case POWER_MODE_SELECTION = 'power_mode_selection';

	case PROGRAM_MODE = 'program_mode';

	case PROGRAMMABLE_SWITCH_EVENT = 'programmable_switch_event';

	case RELATIVE_HUMIDITY_DEHUMIDIFIER_THRESHOLD = 'relative_humidity_dehumidifier_threshold';

	case RELATIVE_HUMIDITY_HUMIDIFIER_THRESHOLD = 'relative_humidity_humidifier_threshold';

	case REMAINING_DURATION = 'remaining_duration';

	case REMOTE_KEY = 'remote_key';

	case RESET_FILTER_INDICATION = 'reset_filter_indication';

	case ROTATION_DIRECTION = 'rotation_direction';

	case ROTATION_SPEED = 'rotation_speed';

	case SATURATION = 'saturation';

	case SECURITY_SYSTEM_ALARM_TYPE = 'security_system_alarm_type';

	case SECURITY_SYSTEM_CURRENT_STATE = 'security_system_current_state';

	case SECURITY_SYSTEM_TARGET_STATE = 'security_system_target_state';

	case SELECTED_RTP_STREAM_CONFIGURATION = 'selected_rtp_stream_configuration';

	case SERIAL_NUMBER = 'serial_number';

	case SERVICE_LABEL_INDEX = 'service_label_index';

	case SERVICE_LABEL_NAMESPACE = 'service_label_namespace';

	case SET_DURATION = 'set_duration';

	case SETUP_ENDPOINTS = 'setup_endpoints';

	case SLAT_TYPE = 'slat_type';

	case SLEEP_DISCOVERY_MODE = 'sleep_discovery_mode';

	case SMOKE_DETECTED = 'smoke_detected';

	case STATUS_ACTIVE = 'status_active';

	case STATUS_FAULT = 'status_fault';

	case STATUS_JAMMED = 'status_jammed';

	case STATUS_LOW_BATTERY = 'status_low_battery';

	case STATUS_TAMPERED = 'status_tampered';

	case STREAMING_STATUS = 'streaming_status';

	case SULPHUR_DIOXIDE_DENSITY = 'sulphur_dioxide_density';

	case SUPPORTED_AUDIO_STREAM_CONFIGURATION = 'supported_audio_stream_configuration';

	case SUPPORTED_RTP_CONFIGURATION = 'supported_rtp_configuration';

	case SUPPORTED_VIDEO_STREAM_CONFIGURATION = 'supported_video_stream_configuration';

	case SWING_MODE = 'swing_mode';

	case TARGET_AIR_PURIFIER_STATE = 'target_air_purifier_state';

	case TARGET_AIR_QUALITY = 'target_air_quality';

	case TARGET_DOOR_STATE = 'target_door_state';

	case TARGET_FAN_STATE = 'target_fan_state';

	case TARGET_HEATER_COOLER_STATE = 'target_heater_cooler_state';

	case TARGET_HEATING_COOLING_STATE = 'target_heating_cooling_state';

	case TARGET_HORIZONTAL_TILT_ANGLE = 'target_horizontal_tilt_angle';

	case TARGET_HUMIDIFIER_DEHUMIDIFIER_STATE = 'target_humidifier_dehumidifier_state';

	case TARGET_MEDIA_STATE = 'target_media_state';

	case TARGET_POSITION = 'target_position';

	case TARGET_RELATIVE_HUMIDITY = 'target_relative_humidity';

	case TARGET_SLAT_STATE = 'target_slat_state';

	case TARGET_TEMPERATURE = 'target_temperature';

	case TARGET_TILT_ANGLE = 'target_tilt_angle';

	case TARGET_VERTICAL_TILT_ANGLE = 'target_vertical_tilt_angle';

	case TARGET_VISIBILITY_STATE = 'target_visibility_state';

	case TEMPERATURE_DISPLAY_UNITS = 'temperature_display_units';

	case VOC_DENSITY = 'voc_density';

	case VALVE_TYPE = 'valve_type';

	case VERSION = 'version';

	case VOLUME = 'volume';

	case VOLUME_CONTROL_TYPE = 'volume_control_type';

	case VOLUME_SELECTOR = 'volume_selector';

	case WATER_LEVEL = 'water_level';

	case COLOR_RED = 'color_red';

	case COLOR_GREEN = 'color_green';

	case COLOR_BLUE = 'color_blue';

	case COLOR_WHITE = 'color_white';

}
