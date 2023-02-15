<?php declare(strict_types = 1);

/**
 * Constants.php
 *
 * @license        More in LICENSE.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     common
 * @since          1.0.0
 *
 * @date           13.09.22
 */

namespace FastyBird\Connector\HomeKit;

/**
 * Connector constants
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     common
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class Constants
{

	public const PACKAGE_NAME = 'fastybird/homekit-connector';

	public const DEFAULT_MANUFACTURER = 'FastyBird';

	public const DEFAULT_BRIDGE_MODEL = 'Virtual bridge';

	public const DEFAULT_DEVICE_MODEL = 'Virtual device';

	public const BASE_UUID = '-0000-1000-8000-0026BB765291';

	public const RESOURCES_FOLDER = __DIR__ . '/../resources';

	public const DEFAULT_CONFIG_VERSION = 1;

	public const DEFAULT_PORT = 51_827;

	public const MAX_CONFIG_VERSION = 65_535;

	public const STANDALONE_AID = 1; // Standalone accessory ID (i.e. not bridged)

	public const HAP_PROTOCOL_VERSION = '01.01.00';

	public const HAP_PROTOCOL_SHORT_VERSION = '1.1';

	public const VERSION_REGEXP = '/^(?:[0-9]*).(?:[0-9]*).(?:[0-9]*)$/';

}
