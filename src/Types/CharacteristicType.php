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
use function strval;

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
	public const BRIGHTNESS = 'Brightness';

	public const HUE = 'Hue';

	public const SATURATION = 'Saturation';

	public const COLOR_TEMPERATURE = 'ColorTemperature';

	public const NAME = 'Name';

	public const ON = 'On';

	public const COLOR_RED = 'ColorRed';

	public const COLOR_GREEN = 'ColorGreen';

	public const COLOR_BLUE = 'ColorBlue';

	public const COLOR_WHITE = 'ColorWhite';

	public function getValue(): string
	{
		return strval(parent::getValue());
	}

	public function __toString(): string
	{
		return strval(self::getValue());
	}

}
