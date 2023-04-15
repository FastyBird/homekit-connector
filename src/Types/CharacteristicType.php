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

use Consistence;

/**
 * HAP service characteristic type types
 *
 * @package        FastyBird:HomeKitConnector!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class CharacteristicType extends Consistence\Enum\Enum
{

	/**
	 * Define statuses
	 */
	public const TYPE_BRIGHTNESS = 'Brightness';

	public const TYPE_HUE = 'Hue';

	public const TYPE_SATURATION = 'Saturation';

	public const TYPE_COLOR_TEMPERATURE = 'ColorTemperature';

	public const TYPE_NAME = 'Name';

	public const TYPE_ON = 'On';

	public const TYPE_COLOR_RED = 'ColorRed';

	public const TYPE_COLOR_GREEN = 'ColorGreen';

	public const TYPE_COLOR_BLUE = 'ColorBlue';

	public const TYPE_COLOR_WHITE = 'ColorWhite';

}
